<?php

namespace App\Livewire\MasterData;

use App\Services\MasterDataTreeService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * MasterDataTreeView — screen-127--master-data-tree-view /
 * usecase-127--master-data-tree-view (Livewire web "Master Data Tree
 * View", route name `master-data.tree-view`, /master-data/tree-view).
 *
 * Read-only navigation aid over the Corporate -> Company -> Business Unit
 * -> Production Line hierarchy — no create/update/delete here at all.
 * Clicking a node's name navigates (plain link, full page load — this
 * codebase has no wire:navigate usage anywhere) to that entity's existing
 * "Kelola X" CRUD screen, pre-filtered to the clicked node's parent via
 * that screen's own existing filter property (see KelolaCompany /
 * KelolaBusinessUnit / KelolaProductionLine's new #[Url] attribute).
 *
 * Unlike the 4 CRUD screens, $tree is loaded ONCE in mount(), not on every
 * render() — this screen doesn't paginate/refilter, so refetching the
 * whole hierarchy on every expand/collapse click would be wasteful.
 *
 * Access control: route-level only, identical to every other master-data
 * screen — routes/web.php guards /master-data/tree-view with 'auth' +
 * 'role:admin'; EnsureRole::forbidden() aborts(403) before this component
 * ever mounts for a non-admin session.
 */
#[Layout('master-data.tree-view')]
class MasterDataTreeView extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $tree = [];

    /** @var array<string, bool> keyed "{level}-{id}" */
    public array $expanded = [];

    public function mount(MasterDataTreeService $service): void
    {
        $this->tree = $service->getTree();

        foreach ($this->tree as $corporateNode) {
            $this->expanded["corporate-{$corporateNode['id']}"] = true;
        }
    }

    public function toggleNode(string $level, string $id): void
    {
        $key = "{$level}-{$id}";

        if (isset($this->expanded[$key])) {
            unset($this->expanded[$key]);
        } else {
            $this->expanded[$key] = true;
        }
    }

    public function isExpanded(string $level, string $id): bool
    {
        return isset($this->expanded["{$level}-{$id}"]);
    }

    public function render()
    {
        return view('livewire.master-data.master-data-tree-view');
    }
}
