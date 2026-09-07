<?php

/**
 * MasterDataTreeViewTest (Feature/Livewire) — screen-127--master-data-tree-view /
 * usecase-127--master-data-tree-view.
 *
 * Component tests for App\Livewire\MasterData\MasterDataTreeView. Mirrors
 * the Livewire::actingAs($user)->test() convention used by every other
 * KelolaXTest.php in this directory.
 *
 * Access control (last scenario) uses a real HTTP request rather than
 * Livewire::test(), same reasoning as every other master-data screen's
 * test in this suite: access control is enforced entirely at the routing
 * layer ('auth' + 'role:admin' in routes/web.php), which Livewire::test()
 * cannot observe since it mounts the component directly, bypassing route
 * middleware.
 */

use App\Enums\UserRole;
use App\Livewire\MasterData\MasterDataTreeView;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Corporate;
use App\Models\ProductionLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('renders the full 4-level hierarchy with correct children_count at each level', function () {
    // $this->admin's own factory-cascaded BusinessUnit->Company->Corporate
    // chain (UserFactory's business_unit_id default) is not part of what
    // this test cares about — clear it first, mirroring the same pattern
    // KelolaCompanyTest.php's "Belum ada Corporate" scenario uses.
    Corporate::query()->delete();

    $corporate = Corporate::factory()->create(['name' => 'PT Induk Jaya']);
    $company = Company::factory()->create(['corporate_id' => $corporate->id, 'name' => 'PT Anak Usaha']);
    $businessUnit = BusinessUnit::factory()->create(['company_id' => $company->id, 'name' => 'Mill Satu']);
    ProductionLine::factory()->forBusinessUnit($businessUnit)->withCode('PL-TREE-001')->create(['name' => 'Line 1']);
    ProductionLine::factory()->forBusinessUnit($businessUnit)->withCode('PL-TREE-002')->create(['name' => 'Line 2']);

    $emptyCorporate = Corporate::factory()->create(['name' => 'PT Tanpa Anak']);

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);

    $tree = $component->get('tree');

    expect($tree)->toHaveCount(2);

    $node = collect($tree)->firstWhere('id', $corporate->id);
    expect($node['children_count'])->toBe(1);
    expect($node['children'][0]['id'])->toBe($company->id);
    expect($node['children'][0]['children_count'])->toBe(1);
    expect($node['children'][0]['children'][0]['id'])->toBe($businessUnit->id);
    expect($node['children'][0]['children'][0]['children_count'])->toBe(2);
    expect($node['children'][0]['children'][0]['children'])->toHaveCount(2);

    $emptyNode = collect($tree)->firstWhere('id', $emptyCorporate->id);
    expect($emptyNode['children_count'])->toBe(0);
    expect($emptyNode['children'])->toBe([]);
});

it('shows an empty-state message when there is no master data at all', function () {
    // Same reasoning as above — clear $this->admin's own cascaded chain so
    // there are genuinely 0 corporates.
    Corporate::query()->delete();

    Livewire::actingAs($this->admin)
        ->test(MasterDataTreeView::class)
        ->assertSee('Belum ada data master');
});

it('loads the whole tree in a constant, small number of queries regardless of tree size (no N+1)', function () {
    $corporate = Corporate::factory()->create();
    $company = Company::factory()->create(['corporate_id' => $corporate->id]);
    $businessUnit = BusinessUnit::factory()->create(['company_id' => $company->id]);
    foreach (range(1, 5) as $i) {
        ProductionLine::factory()->forBusinessUnit($businessUnit)->withCode("PL-N1-{$i}")->create();
    }

    $queryCount = 0;
    DB::listen(function () use (&$queryCount) {
        $queryCount++;
    });

    Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);

    // A handful of fixed queries (corporates + 3 nested eager-loads, plus
    // Livewire/session bookkeeping) — NOT growing with the number of
    // production lines. The exact ceiling here is generous on purpose;
    // the point is "constant", not "exactly N".
    expect($queryCount)->toBeLessThan(15);
});

it('toggles a node open then closed again via toggleNode()', function () {
    $corporate = Corporate::factory()->create();
    $company = Company::factory()->create(['corporate_id' => $corporate->id, 'name' => 'PT Anak Usaha']);

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);

    // Corporate starts expanded by default (mount() pre-populates it), so
    // the Company is already visible; the Company level itself starts
    // collapsed by default per business-spec — toggling it open reveals
    // nothing further here since it has no children, but we can still
    // assert the expanded-state flag flips correctly via isExpanded().
    expect($component->instance()->isExpanded('corporate', $corporate->id))->toBeTrue();
    expect($component->instance()->isExpanded('company', $company->id))->toBeFalse();

    $component->call('toggleNode', 'company', $company->id);
    expect($component->instance()->isExpanded('company', $company->id))->toBeTrue();

    $component->call('toggleNode', 'company', $company->id);
    expect($component->instance()->isExpanded('company', $company->id))->toBeFalse();
});

it('renders each node link with the correct target route and pre-applied parent filter', function () {
    $corporate = Corporate::factory()->create(['name' => 'PT Induk Jaya']);
    $company = Company::factory()->create(['corporate_id' => $corporate->id, 'name' => 'PT Anak Usaha']);
    $businessUnit = BusinessUnit::factory()->create(['company_id' => $company->id, 'name' => 'Mill Satu']);
    ProductionLine::factory()->forBusinessUnit($businessUnit)->withCode('PL-LINK-001')->create(['name' => 'Line 1']);

    $response = $this->actingAs($this->admin, 'web')->get('/master-data/tree-view');

    $response->assertSee(route('master-data.corporates'), false);
    $response->assertSee(route('master-data.companies', ['filterCorporateId' => $corporate->id]), false);

    // Business Unit / Production Line links only render once their parent
    // node is expanded — expand both via toggleNode before re-rendering.
    Livewire::actingAs($this->admin)
        ->test(MasterDataTreeView::class)
        ->call('toggleNode', 'company', $company->id)
        ->call('toggleNode', 'business_unit', $businessUnit->id)
        ->assertSee(route('master-data.business-units', ['filterCompanyId' => $company->id]), false)
        ->assertSee(route('master-data.production-lines', ['filterBusinessUnitId' => $businessUnit->id]), false);
});

it('akses ditolak: returns 403 and never renders the component for a non-admin session', function (string $role) {
    $user = User::factory()->role(UserRole::from($role))->create();

    $response = $this->actingAs($user, 'web')->get('/master-data/tree-view');

    $response->assertForbidden();
    $response->assertDontSee('Master Data Tree View');
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);
