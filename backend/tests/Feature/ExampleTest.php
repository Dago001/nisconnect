<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_admin_login(): void
    {
        $this->get('/')->assertRedirect(route('admin.login'));
    }

    public function test_health_check_is_available(): void
    {
        $this->get('/up')->assertOk();
    }
}
