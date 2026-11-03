<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MapGoogleIdentity extends Command
{
    protected $signature = 'superblog:map-google-identity
        {user_id : Existing user database ID}
        {google_subject : Verified Google OpenID Connect subject}
        {--initial-super-admin : Confirm the target is the single approved bootstrap super-admin}';

    protected $description = 'Explicitly map a verified Google identity to an existing user';

    public function handle(): int
    {
        $validator = Validator::make([
            'user_id' => $this->argument('user_id'),
            'google_subject' => $this->argument('google_subject'),
        ], [
            'user_id' => ['required', 'integer', 'min:1'],
            'google_subject' => ['required', 'string', 'max:255', 'not_regex:/\s/'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $validated = $validator->validated();

        $user = User::find($validated['user_id']);
        if (! $user) {
            $this->error('No user exists with that database ID. No mapping was changed.');

            return self::FAILURE;
        }

        $isBootstrapAdmin = $this->option('initial-super-admin');
        if ($user->is_super_admin) {
            if (! $isBootstrapAdmin || $user->approval_status !== 'approved'
                || User::where('is_super_admin', true)->count() !== 1) {
                $this->error('A super-admin mapping requires --initial-super-admin and a single approved super admin.');

                return self::FAILURE;
            }
        } elseif ($isBootstrapAdmin) {
            $this->error('The selected user is not an approved super admin. No mapping was changed.');

            return self::FAILURE;
        }

        if ($user->google_subject !== null) {
            $this->error('This user already has a Google identity mapping. Existing mappings are never overwritten.');

            return self::FAILURE;
        }

        if (User::where('google_subject', $validated['google_subject'])->exists()) {
            $this->error('That Google subject is already mapped to another user. No mapping was changed.');

            return self::FAILURE;
        }

        $role = $user->is_super_admin ? 'approved bootstrap super-admin' : 'staff account';
        if (! $this->confirm("Map Google identity to {$role} #{$user->id} ({$user->email})? The subject itself will not be displayed.")) {
            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($user, $validated, $isBootstrapAdmin): void {
                $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());

                if ($isBootstrapAdmin) {
                    if (! $locked->is_super_admin || $locked->approval_status !== 'approved'
                        || User::where('is_super_admin', true)->count() !== 1) {
                        throw new GoogleBootstrapTargetChanged;
                    }
                } elseif ($locked->is_super_admin) {
                    throw new GoogleBootstrapTargetChanged;
                }

                if ($locked->google_subject !== null) {
                    throw new GoogleIdentityAlreadyMapped;
                }

                if (User::where('google_subject', $validated['google_subject'])->exists()) {
                    throw new GoogleSubjectAlreadyMapped;
                }

                $locked->google_subject = $validated['google_subject'];
                $locked->save();
            });
        } catch (GoogleIdentityAlreadyMapped|GoogleSubjectAlreadyMapped|GoogleBootstrapTargetChanged|UniqueConstraintViolationException $exception) {
            $message = match (true) {
                $exception instanceof GoogleIdentityAlreadyMapped => 'This user was mapped by another operation. No mapping was changed.',
                $exception instanceof GoogleBootstrapTargetChanged => 'The selected account no longer meets the required role checks. No mapping was changed.',
                default => 'That Google subject is already mapped. No mapping was changed.',
            };
            $this->error($message);

            return self::FAILURE;
        }

        $this->info("Google identity mapped to user #{$user->id}. Approval, roles, flags, and assignments were preserved.");

        return self::SUCCESS;
    }
}

class GoogleIdentityAlreadyMapped extends \RuntimeException {}

class GoogleSubjectAlreadyMapped extends \RuntimeException {}

class GoogleBootstrapTargetChanged extends \RuntimeException {}
