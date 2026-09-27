<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The application root redirects guests to the public price board, so it
     * answers with a redirect rather than a page. FrontendSmokeTest covers the
     * destination page itself; this only pins the entry-point behaviour.
     */
    public function test_the_application_root_redirects_to_the_public_price_board(): void
    {
        $this->get('/')->assertRedirect('/prices');
    }
}
