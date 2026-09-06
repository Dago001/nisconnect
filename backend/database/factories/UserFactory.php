<?php

namespace Database\Factories;

use App\Models\PersonnelRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        $serviceNumber = (string) $this->faker->unique()->numberBetween(100000, 999999);

        return [
            'personnel_record_id' => PersonnelRecord::factory()->state(['service_number' => $serviceNumber]),
            'service_number' => $serviceNumber,
            'phone' => '+234'.$this->faker->numberBetween(7000000000, 9099999999),
            'phone_verified_at' => now(),
            'display_name' => $this->faker->name(),
            'pin_hash' => Hash::make('1234'),
            'account_state' => User::STATE_ACTIVE,
            'presence' => 'offline',
            'privacy' => User::defaultPrivacy(),
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['account_state' => User::STATE_SUSPENDED]);
    }
}
