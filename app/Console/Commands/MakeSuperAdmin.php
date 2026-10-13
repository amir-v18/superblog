<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

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

        // Privilege fields are intentionally excluded from HTTP mass assignment.
        $user->is_super_admin = true;
        $user->approval_status = 'approved';
        $user->save();

        $this->info('The existing account is now an approved super admin.');

        return self::SUCCESS;
    }
}
