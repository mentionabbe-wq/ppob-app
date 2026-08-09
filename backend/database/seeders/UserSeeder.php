<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['name' => 'Super Admin', 'email' => 'admin@ppob.test', 'role' => 'super-admin'],
            ['name' => 'Petugas Keuangan', 'email' => 'finance@ppob.test', 'role' => 'finance'],
            ['name' => 'Operator', 'email' => 'operator@ppob.test', 'role' => 'operator'],
            ['name' => 'Pengguna Demo', 'email' => 'user@ppob.test', 'role' => 'user', 'balance' => 500_000],
        ];

        foreach ($accounts as $account) {
            $user = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => 'password',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$account['role']]);

            if (isset($account['balance'])) {
                $user->wallet()->update(['balance' => $account['balance']]);
            }
        }

        $this->command->warn('Akun seeder memakai kata sandi "password" — WAJIB diganti sebelum produksi.');
    }
}
