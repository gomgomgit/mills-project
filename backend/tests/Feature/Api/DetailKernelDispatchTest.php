<?php

/**
 * DetailKernelDispatchTest (Feature/Api) —
 * screen-103--detail-kernel-dispatch-web /
 * usecase-077--detail-kernel-dispatch-web.
 *
 * Integration tests for GET /api/kernel-dispatch-records/{id}. Mirrors
 * DetailSolidWasteDisposalTest.php's structure exactly.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\KernelDispatchDetail;
use App\Models\KernelDispatchRecord;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->kernelDispatch()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

// Scenario: "Lihat Detail Kernel Dispatch - berhasil"
it('berhasil: returns the full record with resolved names and the detail event log', function () {
    $record = KernelDispatchRecord::factory()->forStation($this->station)->create();
    KernelDispatchDetail::factory()->forRecord($record)->create(['destination_buyer' => 'PT Kernel Buyer']);

    $response = $this->actingAs($this->supervisor, 'web')->getJson("/api/kernel-dispatch-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $record->id,
        'kernel_dispatch_id' => $record->kernel_dispatch_id,
        'station_name' => $this->station->name,
    ]);
    $response->assertJsonFragment(['destination_buyer' => 'PT Kernel Buyer']);
});

it('returns details ordered by event_date regardless of creation order', function () {
    $record = KernelDispatchRecord::factory()->forStation($this->station)->create();
    KernelDispatchDetail::factory()->forRecord($record)->create(['event_date' => '2026-08-14']);
    KernelDispatchDetail::factory()->forRecord($record)->create(['event_date' => '2026-08-08']);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/kernel-dispatch-records/{$record->id}");

    $response->assertOk();
    $dates = array_column($response->json('details'), 'event_date');
    expect($dates)->toBe(['2026-08-08', '2026-08-14']);
});

it('Mill Management and Admin can also access the detail endpoint', function () {
    $record = KernelDispatchRecord::factory()->forStation($this->station)->create();

    $this->actingAs($this->millManagement, 'web')->getJson("/api/kernel-dispatch-records/{$record->id}")->assertOk();
    $this->actingAs($this->admin, 'web')->getJson("/api/kernel-dispatch-records/{$record->id}")->assertOk();
});

it('returns 403 for the Operator role (route-level role gate)', function () {
    $record = KernelDispatchRecord::factory()->forStation($this->station)->create();

    $response = $this->actingAs($this->operator, 'web')->getJson("/api/kernel-dispatch-records/{$record->id}");

    $response->assertStatus(403);
});

// Scenario: "Lihat Detail Kernel Dispatch - Record Tidak Ditemukan"
it('returns 404 when the id does not exist', function () {
    $response = $this->actingAs($this->admin, 'web')->getJson('/api/kernel-dispatch-records/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
});

it('returns null checked_by_name and acknowledged_by_name when not set', function () {
    $record = KernelDispatchRecord::factory()->forStation($this->station)->create(['checked_by' => null, 'acknowledged_by' => null]);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/kernel-dispatch-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment(['checked_by_name' => null, 'acknowledged_by_name' => null]);
});

it('resolves checked_by_name and acknowledged_by_name to user names when present', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = KernelDispatchRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/kernel-dispatch-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment(['checked_by_name' => 'Checker Person', 'acknowledged_by_name' => 'Acknowledger Person']);
});

it('rejects unauthenticated requests', function () {
    $record = KernelDispatchRecord::factory()->forStation($this->station)->create();

    $response = $this->getJson("/api/kernel-dispatch-records/{$record->id}");

    $response->assertStatus(401);
});
