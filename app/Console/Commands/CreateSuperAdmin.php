<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateSuperAdmin extends Command
{
    protected $signature = 'superblog:create-super-admin';

    protected $description = 'Create an approved Superblog admin account';

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
            DB::transaction(function () use ($name, $email, $password): void {
                $user = new User;
                $user->name = $name;
                $user->email = $email;
                $user->password = Hash::make($password);
                $user->is_super_admin = true;
                $user->approval_status = 'approved';
                $user->save();

            });
        } catch (UniqueConstraintViolationException) {
            $this->error('An account with that email already exists. No account was created.');

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Could not create the admin account. No account was created.');

            return self::FAILURE;
        }

        $this->info('The approved admin account was created.');

        return self::SUCCESS;
    }
}
