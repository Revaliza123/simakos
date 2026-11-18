<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminName = config('simakos.admin.name');
        $adminEmail = config('simakos.admin.email');
        $adminPassword = config('simakos.admin.password');

        if ($adminName && $adminEmail && $adminPassword) {
            User::updateOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => $adminName,
                    'password' => $adminPassword,
                    'role' => 'admin',
                ],
            );
        }

        $this->call(FinanceDemoSeeder::class);
    }
}
