<?php

namespace Database\Factories;

use App\Models\PersonnelRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonnelRecord>
 */
class PersonnelRecordFactory extends Factory
{
    protected $model = PersonnelRecord::class;

    public function definition(): array
    {
        return [
            'service_number' => (string) $this->faker->unique()->numberBetween(100000, 999999),
            'surname' => $this->faker->lastName(),
            'first_name' => $this->faker->firstName(),
            'other_name' => null,
            'rank' => 'Assistant Superintendent of Immigration',
            'directorate' => 'ICT/Cyber Security',
            'department' => 'Software & Data',
            'zone' => 'Zone A',
            'command' => 'Service Headquarters',
            'formation' => 'Headquarters',
            'unit' => 'Applications Unit',
            'posting' => 'Service Headquarters, Abuja',
            'official_email' => $this->faker->safeEmail(),
            'status' => 'active',
            'source' => 'demo',
            'source_synced_at' => now(),
        ];
    }
}
