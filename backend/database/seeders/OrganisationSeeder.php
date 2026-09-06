<?php

namespace Database\Seeders;

use App\Models\Command;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Organisation;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class OrganisationSeeder extends Seeder
{
    public function run(): void
    {
        $nis = Organisation::updateOrCreate(
            ['code' => 'NIS'],
            ['name' => 'Nigeria Immigration Service'],
        );

        $directorates = [
            'ICT/Cyber Security' => 'ICT',
            'Border Management' => 'BM',
            'Administration' => 'ADMIN',
            'Operations' => 'OPS',
        ];
        foreach ($directorates as $name => $code) {
            $dir = Directorate::updateOrCreate(
                ['code' => $code],
                ['organisation_id' => $nis->id, 'name' => $name],
            );
            Department::updateOrCreate(
                ['directorate_id' => $dir->id, 'name' => 'General'],
                ['code' => $code.'-GEN'],
            );
        }

        $zone = Zone::updateOrCreate(
            ['code' => 'ZA'],
            ['organisation_id' => $nis->id, 'name' => 'Zone A'],
        );
        Command::updateOrCreate(
            ['zone_id' => $zone->id, 'name' => 'Service Headquarters'],
            ['code' => 'SHQ'],
        );
    }
}
