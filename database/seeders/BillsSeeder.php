<?php

namespace Database\Seeders;

use App\Models\Bill;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BillsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Bill::create(['name' => 'food_health_clothing']);
        Bill::create(['name' => 'transportation_gasoline']);
        Bill::create(['name' => 'entertainment']);
        Bill::create(['name' => 'insurance']);
    }
}
