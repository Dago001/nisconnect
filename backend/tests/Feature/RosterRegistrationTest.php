<?php

namespace Tests\Feature;

use App\Models\PersonnelRecord;
use App\Personnel\PersonnelProviderInterface;
use App\Personnel\Providers\RosterPersonnelProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * With PERSONNEL_PROVIDER=roster, only an officer an administrator has
 * onboarded may create an account. This is the boundary that keeps the app
 * closed to the public, so it is pinned down here rather than left to
 * configuration.
 */
class RosterRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['personnel.provider' => 'roster']);
    }

    private function onboard(string $serviceNumber, string $status = 'active'): PersonnelRecord
    {
        return PersonnelRecord::create([
            'service_number' => $serviceNumber,
            'surname' => 'Okonkwo',
            'first_name' => 'Amaka',
            'rank' => 'Inspector',
            'status' => $status,
            'source' => 'admin',
        ]);
    }

    #[Test]
    public function the_container_resolves_the_roster_provider(): void
    {
        $this->assertInstanceOf(
            RosterPersonnelProvider::class,
            app(PersonnelProviderInterface::class),
        );
    }

    #[Test]
    public function an_onboarded_officer_is_found_and_authorised(): void
    {
        $this->onboard('778899');

        $record = app(PersonnelProviderInterface::class)->findByServiceNumber('778899');

        $this->assertNotNull($record);
        $this->assertSame('778899', $record->serviceNumber);
        $this->assertSame('Okonkwo', $record->surname);
        $this->assertTrue($record->isAuthorised());
    }

    #[Test]
    public function a_service_number_nobody_onboarded_is_refused(): void
    {
        $this->onboard('778899');

        $this->assertNull(
            app(PersonnelProviderInterface::class)->findByServiceNumber('000000'),
        );
    }

    #[Test]
    public function verification_refuses_an_unonboarded_number_over_the_api(): void
    {
        $this->onboard('778899');

        // Deliberately 200 with verified:false rather than an error status —
        // the endpoint must not let an outsider tell "no such officer" apart
        // from any other refusal. What matters is that no verification_id is
        // issued, so onboarding cannot proceed.
        $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => '000000'])
            ->assertOk()
            ->assertJson(['verified' => false])
            ->assertJsonMissingPath('verification_id');
    }

    #[Test]
    public function verification_accepts_an_onboarded_officer_over_the_api(): void
    {
        $this->onboard('778899');

        $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => '778899'])
            ->assertOk()
            ->assertJson(['verified' => true])
            ->assertJsonStructure(['verification_id']);
    }

    #[Test]
    public function a_retired_officer_cannot_register_over_the_api(): void
    {
        $this->onboard('665544', status: 'retired');

        $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => '665544'])
            ->assertOk()
            ->assertJson(['verified' => false])
            ->assertJsonMissingPath('verification_id');
    }

    #[Test]
    public function a_retired_officer_is_found_but_not_authorised(): void
    {
        $this->onboard('665544', status: 'retired');

        $record = app(PersonnelProviderInterface::class)->findByServiceNumber('665544');

        $this->assertNotNull($record, 'the record should still be found');
        $this->assertFalse($record->isAuthorised(), 'but must not be allowed to register');
    }

    #[Test]
    public function a_leading_zero_identifies_a_different_officer(): void
    {
        $this->onboard('001234');

        $this->assertNotNull(app(PersonnelProviderInterface::class)->findByServiceNumber('001234'));
        // 1234 is a different Service Number and nobody onboarded it.
        $this->assertNull(app(PersonnelProviderInterface::class)->findByServiceNumber('1234'));
    }
}
