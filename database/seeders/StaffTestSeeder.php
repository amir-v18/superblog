<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class StaffTestSeeder extends Seeder
{
    private const EMAIL = 'staff@example.com';

    private const PASSWORD = 'staff';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('The staff test account can only be seeded in local or testing environments.');
        }

        $user = User::firstOrNew(['email' => self::EMAIL]);

        if ($user->exists) {
            return;
        }

        $user->name = 'Development Staff';
        $user->password = Hash::make(self::PASSWORD);
        $user->is_super_admin = false;
        $user->has_all_superblogs_access = false;
        $user->approval_status = 'pending';
        $user->save();
    }
}
