<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $admin = \App\Models\User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('password'),
        ]);
        $client = \App\Models\User::factory()->create([
            'name' => 'Cliente',
            'email' => 'cliente@gmail.com',
            'password' => bcrypt('password'),
        ]);
        $agent = \App\Models\User::factory()->create([
            'name' => 'Agente',
            'email' => 'agente@gmail.com',
            'password' => bcrypt('password'),
        ]);

        Role::create(['name' => 'Administrator', 'guard_name' => 'web']);
        Role::create(['name' => 'Advisor', 'guard_name' => 'web']);
        Role::create(['name' => 'Customer', 'guard_name' => 'web']);

        $admin->assignRole('Administrator');
        $client->assignRole('Customer');
        $agent->assignRole('Advisor');

        $this->call([
            BillsSeeder::class,
            MainActivitiesSeeder::class,
            StatusesSeeder::class,
        ]);
    }
}
