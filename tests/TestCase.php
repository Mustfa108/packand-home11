<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authenticate a user against Sanctum API routes.
     */
    protected function actingAsUser($user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }
}
