<?php

namespace Tests;
 
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Prevent global redirect to /test-access in automated tests.
        config(['test_mode.password' => '']);
    }
}
