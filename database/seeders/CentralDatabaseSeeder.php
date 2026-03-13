<?php

namespace Database\Seeders;

use App\Models\Central\Ministry;
use App\Models\Central\Province;
use App\Models\Central\SubDivision;
use Illuminate\Database\Seeder;

class CentralDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds for platform_db.
     */
    public function run(): void
    {
        // 1. Create Ministry
        $ministry = Ministry::on('central')->updateOrCreate(
            ['code' => 'MOE_IND'],
            [
                'name' => 'Ministry of Education (India)',
                'status' => 'active'
            ]
        );

        // 2. Create Provinces
        $provinces = [
            ['name' => 'Delhi', 'code' => 'DL'],
            ['name' => 'Maharashtra', 'code' => 'MH'],
            ['name' => 'Karnataka', 'code' => 'KA'],
            ['name' => 'Uttar Pradesh', 'code' => 'UP'],
        ];

        foreach ($provinces as $prov) {
            $province = Province::on('central')->updateOrCreate(
                ['code' => $prov['code']],
                [
                    'name' => $prov['name'],
                    'ministry_id' => $ministry->id
                ]
            );

            // 3. Create Sub-Divisions for each Province
            if ($prov['code'] === 'DL') {
                $subDivs = ['New Delhi', 'South Delhi', 'North Delhi'];
            } elseif ($prov['code'] === 'MH') {
                $subDivs = ['Mumbai City', 'Pune', 'Nagpur'];
            } elseif ($prov['code'] === 'KA') {
                $subDivs = ['Bangalore Urban', 'Mysuru', 'Hubli'];
            } else {
                $subDivs = ['Lucknow', 'Kanpur', 'Varanasi'];
            }

            foreach ($subDivs as $index => $sdName) {
                SubDivision::on('central')->updateOrCreate(
                    ['code' => $prov['code'] . '_SD_' . ($index + 1)],
                    [
                        'name' => $sdName,
                        'province_id' => $province->id
                    ]
                );
            }
        }
    }
}
