<?php

namespace Tests\Unit;

use App\Rules\ServiceNumber;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ServiceNumberRuleTest extends TestCase
{
    private function fails(string $value): bool
    {
        $failed = false;
        (new ServiceNumber)->validate('service_number', $value, function () use (&$failed) {
            $failed = true;
        });

        return $failed;
    }

    public function test_accepts_digits_and_preserves_leading_zeroes(): void
    {
        Config::set('personnel.service_number', ['length' => null, 'min' => 4, 'max' => 12]);
        $this->assertFalse($this->fails('123456'));
        $this->assertFalse($this->fails('001234'));
    }

    public function test_rejects_non_digits(): void
    {
        Config::set('personnel.service_number', ['length' => null, 'min' => 4, 'max' => 12]);
        $this->assertTrue($this->fails('NIS/123456'));
        $this->assertTrue($this->fails('NIS-123456'));
        $this->assertTrue($this->fails('12 345'));
        $this->assertTrue($this->fails('12a345'));
    }

    public function test_enforces_exact_length_when_configured(): void
    {
        Config::set('personnel.service_number', ['length' => 6, 'min' => 4, 'max' => 12]);
        $this->assertFalse($this->fails('123456'));
        $this->assertTrue($this->fails('12345'));
        $this->assertTrue($this->fails('1234567'));
    }

    public function test_enforces_min_max_range(): void
    {
        Config::set('personnel.service_number', ['length' => null, 'min' => 4, 'max' => 8]);
        $this->assertTrue($this->fails('123'));
        $this->assertTrue($this->fails('123456789'));
        $this->assertFalse($this->fails('1234'));
    }
}
