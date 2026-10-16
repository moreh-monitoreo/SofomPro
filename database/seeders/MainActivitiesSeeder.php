<?php

namespace Database\Seeders;

use App\Models\Activity;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MainActivitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Activity::create(['name' => 'Actividad principal']);
        Activity::create(['name' => 'Agua']);
        Activity::create(['name' => 'Armas y productos militares']);
        Activity::create(['name' => 'Automotriz / autopartes']);
        Activity::create(['name' => 'Cómputo y electrónica']);
        Activity::create(['name' => 'Cosméticos']);
        Activity::create(['name' => 'Cuero y calzado']);
        Activity::create(['name' => 'Electricidad']);
        Activity::create(['name' => 'Electrodomésticos']);
        Activity::create(['name' => 'Farmacéutico']);
        Activity::create(['name' => 'Gas']);
        Activity::create(['name' => 'Joyas']);
        Activity::create(['name' => 'Juguetes']);
        Activity::create(['name' => 'Maquinaria y equipo']);
        Activity::create(['name' => 'Metalúrgica']);
        Activity::create(['name' => 'Minería']);
        Activity::create(['name' => 'Muebles']);
        Activity::create(['name' => 'Piedras y metales preciosos']);
        Activity::create(['name' => 'Tabaco']);
        Activity::create(['name' => 'Vehículos de transporte aéreos']);
        Activity::create(['name' => 'Vehículos de transporte marítimos']);
        Activity::create(['name' => 'Vehículos de transporte terrestres']);
    }
}
