<?php

/**
 * BusinessUnitControllerTest — GET /api/business-units.
 *
 * Fills the known gap documented in screen-002--login-mobile's
 * implementation known_issues: both screen-001 (web login) and
 * screen-002 (mobile login) forms need a Business Area list before the
 * user has any session, but no screen tech-spec defined this endpoint.
 * Public endpoint — no auth required, mirrors POST /api/login.
 */

use App\Models\BusinessUnit;

it('returns all business units ordered by name, with no auth required', function () {
    BusinessUnit::factory()->create(['name' => 'Business Unit B']);
    BusinessUnit::factory()->create(['name' => 'Business Unit A']);

    $response = $this->getJson('/api/business-units');

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
    $response->assertJsonPath('data.0.name', 'Business Unit A');
    $response->assertJsonPath('data.1.name', 'Business Unit B');
    $response->assertJsonStructure(['data' => [['id', 'name']]]);
});

it('returns an empty list when no business units exist', function () {
    $response = $this->getJson('/api/business-units');

    $response->assertOk();
    $response->assertExactJson(['data' => []]);
});
