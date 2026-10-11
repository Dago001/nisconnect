<?php

namespace Tests\Feature;

use Tests\TestCase;

class ServiceMetaTest extends TestCase
{
    public function test_meta_is_public_and_reports_no_move_by_default(): void
    {
        config(['app.canonical_url' => null]);
        $this->getJson('/api/v1/meta')->assertOk()
            ->assertJson(['name' => 'NISconnect', 'api_version' => 'v1', 'moved_to' => null]);
    }

    public function test_meta_announces_new_https_address(): void
    {
        config(['app.canonical_url' => 'https://connect.immigration.gov.ng/']);
        $this->getJson('/api/v1/meta')->assertOk()
            ->assertJsonPath('moved_to', 'https://connect.immigration.gov.ng');
    }

    public function test_non_https_address_is_never_announced(): void
    {
        config(['app.canonical_url' => 'http://connect.immigration.gov.ng']);
        $this->getJson('/api/v1/meta')->assertOk()->assertJsonPath('moved_to', null);
    }
}
