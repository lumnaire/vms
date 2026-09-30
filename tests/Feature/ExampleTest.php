<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    // The board reads fish types, so the schema has to exist for this to render.
    use RefreshDatabase;

    /**
     * The public price board IS the home page — there is no separate /prices
     * route and no standalone login page, so "/" renders for a guest rather
     * than redirecting. FrontendSmokeTest covers the rendered board in detail;
     * this only pins the entry-point behaviour.
     */
    public function test_the_application_root_serves_the_public_price_board(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Fish Price Board', false)
            ->assertSee('Virac Public Market', false);
    }
}
