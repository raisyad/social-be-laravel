<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Paksa Sanctum hanya pakai token, bukan cookie/session
        config([
            'sanctum.stateful' => [],       // kosongkan daftar domain stateful
            'sanctum.guards'   => ['sanctum'], // hanya izinkan guard sanctum
        ]);
    }
}
