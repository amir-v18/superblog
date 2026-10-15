<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdmin extends Command
{
    protected $signature = 'superblog:create-super-admin';

    protected $description = 'Create the first approved Superblog super admin';

    public function handle(): int
    {
        $name = $this->ask('Name');
        $email = $this->ask('Email');
        $password = $this->secret('Password');
        $passwordConfirmation = $this->secret('Confirm password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            $created = DB::transaction(function () use ($name, $email, $password): bool {
                // Serialize bootstrap attempts using a stable row created by the migrations.
                DB::table('migrations')->orderBy('id')->lockForUpdate()->first();

                if (User::where('is_super_admin', true)->exists()) {
                    return false;
                }

                $user = new User;
                $user->name = $name;
                $user->email = $email;
                $user->password = Hash::make($password);
                $user->is_super_admin = true;
                $user->approval_status = 'approved';
                $user->save();

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            $this->error('An account with that email already exists. No account was created.');

            return self::FAILURE;
        }

        if (! $created) {
            $this->error('A super admin already exists. No account was created.');

            return self::FAILURE;
        }

        $this->info('The approved super-admin account was created.');

        return self::SUCCESS;
    }
}
