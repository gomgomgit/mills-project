<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case — test infrastructure (shared-modules)
|--------------------------------------------------------------------------
| Feature tests boot the full Laravel application (Tests\TestCase) and reset
| the database between tests (RefreshDatabase, against the `sqlite`
| in-memory testing connection configured in phpunit.xml). Covers API
| endpoints (Sanctum) and Livewire web routes generated per-screen in
| impl-2-screen.
*/
uses(TestCase::class, RefreshDatabase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Unit Tests
|--------------------------------------------------------------------------
| Unit tests do not boot the application — plain Pest test cases, used for
| isolated logic (e.g. App\Support\Pagination, service classes).
*/
uses()->in('Unit');
