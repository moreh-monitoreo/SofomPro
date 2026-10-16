<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StatusesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Status::create(['name' => 'NEW USER', 'step' => 0]);
        Status::create(['name' => 'PROFILE REGISTERED', 'step' => 1]);
        Status::create(['name' => 'PROFILE 2 REGISTERED', 'step' => 2]);
        Status::create(['name' => 'ADDRESS REGISTERED', 'step' => 3]);
        Status::create(['name' => 'PROFILE COMPLETED', 'step' => 4]);
        Status::create(['name' => 'EMPLOYMENT REGISTERED', 'step' => 5]);
        Status::create(['name' => 'EMPLOYMENT COMPLETED', 'step' => 6]);
        Status::create(['name' => 'LOAN CREATED', 'step' => 7]);
    }
}
