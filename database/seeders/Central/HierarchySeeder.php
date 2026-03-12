<?php

namespace Database\Seeders\Central;

use App\Models\Central\Ministry;
use App\Models\Central\Province;
use App\Models\Central\SubDivision;
use Illuminate\Database\Seeder;

class HierarchySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ministry = Ministry::on('central')->updateOrCreate(
            ['code' => 'EDU-N'],
            ['name' => 'National Ministry of Education', 'status' => 'active']
        );

        $province = Province::on('central')->updateOrCreate(
            ['code' => 'PROV-1', 'ministry_id' => $ministry->id],
            ['name' => 'Central Province']
        );

        SubDivision::on('central')->updateOrCreate(
            ['code' => 'SUB-01', 'province_id' => $province->id],
            ['name' => 'District 1']
        );
    }
}
