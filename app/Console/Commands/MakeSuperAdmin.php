<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\SuperAdminPromotionLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class MakeSuperAdmin extends Command
{
    protected $signature = 'superblog:make-super-admin {email : Email of an existing account}';

    protected $description = 'Explicitly approve and promote an existing account to super admin';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No existing account has that email address.');

            return self::FAILURE;
        }

        if (! $this->confirm("Approve {$user->email} and grant access to every Superblog?")) {
            return self::FAILURE;
        }

        try {
            $result = DB::transaction(function () use ($user): string {
                app(SuperAdminPromotionLock::class)->acquire();

                if (User::where('is_super_admin', true)->exists()) {
                    return 'already_exists';
                }

                $lockedUser = User::query()->lockForUpdate()->find($user->getKey());
                if (! $lockedUser || $lockedUser->email !== $user->email) {
                    return 'target_missing';
                }

                // Privilege fields are intentionally excluded from HTTP mass assignment.
                $lockedUser->is_super_admin = true;
                $lockedUser->approval_status = 'approved';
                $lockedUser->save();

                return 'promoted';
            });
        } catch (Throwable) {
            $this->error('Could not safely verify the super-admin lock. No account was promoted.');

            return self::FAILURE;
        }

        if ($result === 'already_exists') {
            $this->error('A super admin already exists. The account was not changed.');

            return self::FAILURE;
        }

        if ($result !== 'promoted') {
            $this->error('The selected account changed or no longer exists. No account was promoted.');

            return self::FAILURE;
        }

        $this->info('The existing account is now an approved super admin.');

        return self::SUCCESS;
    }
}
