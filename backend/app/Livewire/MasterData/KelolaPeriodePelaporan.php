<?php

namespace App\Livewire\MasterData;

use App\Enums\PeriodStatus;
use App\Exceptions\PeriodClosedImmutableException;
use App\Exceptions\PeriodOverlapException;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Services\PeriodService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * KelolaPeriodePelaporan — screen-128--kelola-periode-pelaporan /
 * usecase-128 (CRUD). Livewire web screen at /master-data/periods, route
 * name `master-data.periods`.
 *
 * usecase-140 (tutup & buka kembali) and usecase-144 (buka stasiun draft)
 * used to be handled here too; since 2026-09-27 they belong to
 * screen-142--detail-periode-pelaporan.
 *
 * Reuses PeriodService — the exact same service
 * App\Http\Controllers\Api\PeriodController uses — so the web form and the
 * API never disagree on a rule. Per-station closure (PeriodClosureService)
 * belongs to screen-142 and is not reachable from here.
 *
 * ── WHAT 2026-09-25/26 CHANGED ON THIS SCREEN ───────────────────────────
 *
 * A period no longer HAS a station type, a status, a closer or a closing
 * time. It has a LIST of station rows, each with its own status, and the
 * period only summarises them (PeriodService::toRow(): `status_summary`,
 * `station_count`, `closed_station_count`, `is_immutable`). Three consequences
 * shape everything below:
 *
 *  1. THE FORM TAKES NO STATION CHOICE. The "Jenis Stasiun" select and the
 *     `$station_type` property are gone: create() registers every station
 *     type active in the mill by itself, and update() backfills the ones the
 *     mill has gained since. The Admin lost a control they used to have, so
 *     the form SHOWS them what the period will cover instead — the preview
 *     list built in render() from PeriodService::activeStationTypesForMill(),
 *     never from a rule re-derived here.
 *
 *  2. THE LIST IS ONE ROW PER PERIOD, AND NOTHING ELSE (2026-09-27). The
 *     station rows used to live here in an expandable second <tr>, together
 *     with every per-station action. A mill can have 19 station types, so
 *     that accordion was carrying a whole screen inside a table row — it is
 *     now a screen: App\Livewire\MasterData\DetailPeriodePelaporan,
 *     /master-data/periods/{id}, reached by clicking the period name. Gone
 *     from here with it: `$expandedPeriodIds`, toggleExpanded()/expand(),
 *     the askClose/askOpen/askReopen trios and their dialogs. This component
 *     no longer touches PeriodClosureService at all.
 *
 *  3. THE PARENT ROW SUMMARISES, IT DOES NOT ACT. `status_summary`,
 *     `station_count` and `closed_station_count` are all this screen shows
 *     of a period's stations; deciding anything per-station happens on the
 *     detail screen, where the `period_stations` id lives.
 *
 * EDIT / HAPUS ARE DRIVEN BY `is_immutable`, NOT RE-DERIVED. That flag is
 * exactly the condition PeriodService::update()/delete() refuse on (409
 * PERIOD_CLOSED_IMMUTABLE — at least one station closed). The buttons are
 * rendered DISABLED rather than hidden: an Admin looking at a period with one
 * closed station out of nineteen needs to see that editing exists and is
 * blocked, not wonder where it went.
 *
 * VALIDATION HAS ONE SOURCE. This component used to mirror
 * PeriodService::validate() in a local buildValidator(). The copy had drifted
 * (it scoped the unique-name rule to `station_type` and added a
 * whereNull('business_unit_id') branch the service never had), which means
 * the form and the API could accept or refuse different input. The mirror is
 * GONE: save() calls the service and maps its ValidationException onto this
 * form's binding keys. One rule set, no second place to keep in step.
 *
 * Access control: route-level only. routes/web.php guards
 * /master-data/periods with 'auth' + 'role:admin' — EnsureRole::forbidden()
 * aborts(403) before this component ever mounts for a non-admin session,
 * same as every other master-data screen.
 */
#[Layout('master-data.periods')]
class KelolaPeriodePelaporan extends Component
{
    public int $page = 1;

    public int $perPage = 20;

    /** Filter Business Unit — URL-bound so the filtered view is linkable. */
    #[Url]
    public string $filterBusinessUnitId = '';

    /**
     * Filter status — draft | open | closed, '' = semua status. Matches a
     * period that has AT LEAST ONE station in that status (see
     * PeriodService::listPeriods()), which is why a period can appear under
     * two different filter values.
     */
    #[Url]
    public string $filterStatus = '';

    public bool $showForm = false;

    public ?string $editingId = null;

    /**
     * Kept out of $form because it is bound directly by x-searchable-select.
     * Bound `.live` so the station preview under it follows the selected mill
     * without waiting for a save.
     */
    public string $business_unit_id = '';

    /** @var array<string, string> */
    public array $form = [];

    public ?string $formErrorMessage = null;

    public ?string $successMessage = null;

    public ?string $confirmingDeleteId = null;

    public ?string $deleteErrorMessage = null;

    public function mount(): void
    {
        $this->form = $this->emptyForm();
    }

    public function updatedFilterBusinessUnitId(): void
    {
        $this->page = 1;
    }

    public function updatedFilterStatus(): void
    {
        $this->page = 1;
    }

    /**
     * @return array<string, string>
     */
    protected function emptyForm(): array
    {
        return [
            'name' => '',
            'start_date' => '',
            'end_date' => '',
        ];
    }

    public function openCreateForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->business_unit_id = '';
        $this->form = $this->emptyForm();
        $this->formErrorMessage = null;
        $this->successMessage = null;
        $this->showForm = true;
    }

    /**
     * "Edit" row action. A period with at least one CLOSED station may not be
     * edited — the table renders the button disabled for one, and this second
     * guard keeps a forged/stale call from even opening the form (the service
     * would refuse the save anyway with PERIOD_CLOSED_IMMUTABLE).
     */
    public function openEditForm(string $id): void
    {
        try {
            $period = Period::findOrFail($id);
        } catch (ModelNotFoundException) {
            $this->deleteErrorMessage = 'Periode tidak ditemukan, mungkin sudah dihapus.';

            return;
        }

        if ($this->hasClosedStation($period)) {
            $this->deleteErrorMessage = (new PeriodClosedImmutableException)->getMessage();

            return;
        }

        $this->resetValidation();
        $this->formErrorMessage = null;
        $this->successMessage = null;
        $this->editingId = $period->id;
        $this->business_unit_id = (string) $period->business_unit_id;

        $this->form = [
            'name' => (string) ($period->name ?? ''),
            'start_date' => optional($period->start_date)->toDateString() ?? '',
            'end_date' => optional($period->end_date)->toDateString() ?? '',
        ];

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->business_unit_id = '';
        $this->form = $this->emptyForm();
        $this->formErrorMessage = null;
        $this->resetValidation();
    }

    /**
     * "Simpan" — create or update, per whether $editingId is set.
     *
     * NO CLIENT-SIDE MIRROR OF THE RULES (see the class docblock): the
     * service validates, and its ValidationException is remapped onto this
     * form's binding keys below, so the field-level errors under each input
     * are the service's own verdict rather than a second opinion.
     *
     * Every failure path deliberately LEAVES THE MODAL OPEN with the
     * Admin's input intact (the screen's test_scenarios assert exactly
     * that for the overlap and the validation cases): a rejected save must
     * never cost them the form they just filled in.
     */
    public function save(): void
    {
        $this->formErrorMessage = null;
        $this->successMessage = null;
        $this->resetValidation();

        $service = app(PeriodService::class);

        $payload = [
            'business_unit_id' => $this->business_unit_id,
            'name' => $this->form['name'],
            'start_date' => $this->form['start_date'],
            'end_date' => $this->form['end_date'],
        ];

        try {
            if ($this->editingId !== null) {
                $service->update($this->editingId, $payload);
            } else {
                $service->create($payload);
            }
        } catch (ModelNotFoundException) {
            // Deleted by another Admin between opening the form and
            // submitting it — friendly message, list refreshes on the next
            // render(), no 500.
            $this->formErrorMessage = 'Periode tidak ditemukan, mungkin sudah dihapus oleh pengguna lain.';

            return;
        } catch (PeriodClosedImmutableException $e) {
            // A station of this period was closed by another Admin while
            // this form was open.
            $this->formErrorMessage = $e->getMessage();

            return;
        } catch (PeriodOverlapException $e) {
            // 422 PERIOD_OVERLAP — a form-level message (not field-keyed):
            // it is a conflict between two date fields and an existing
            // row, and the message names the conflicting period.
            $this->formErrorMessage = $e->getMessage();

            return;
        } catch (ValidationException $e) {
            // The service's plain field keys remapped onto this form's
            // binding keys: `business_unit_id` is a top-level property,
            // everything else lives under `form.`.
            foreach ($e->errors() as $field => $messages) {
                $key = $field === 'business_unit_id' ? $field : "form.$field";
                $this->addError($key, $messages[0] ?? 'Validasi gagal.');
            }

            return;
        }

        $this->successMessage = $this->editingId !== null
            ? 'Periode berhasil diperbarui.'
            : 'Periode berhasil dibuat.';

        $this->showForm = false;
        $this->editingId = null;
        $this->business_unit_id = '';
        $this->form = $this->emptyForm();
        $this->resetValidation();
    }

    public function askDelete(string $id): void
    {
        $this->confirmingDeleteId = $id;
        $this->deleteErrorMessage = null;
        $this->successMessage = null;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    /**
     * Confirming the delete — 404 (already gone) and 409
     * PERIOD_CLOSED_IMMUTABLE (a station is closed) both surface inline and
     * leave the row in place; only a clean delete removes it.
     */
    public function confirmDelete(): void
    {
        if ($this->confirmingDeleteId === null) {
            return;
        }

        try {
            app(PeriodService::class)->delete($this->confirmingDeleteId);
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = null;
            $this->successMessage = 'Periode berhasil dihapus.';
        } catch (PeriodClosedImmutableException $e) {
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = 'Periode tidak ditemukan, mungkin sudah dihapus.';
        }
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    /**
     * The exact condition PeriodService::update()/delete() refuse on. Read
     * from `period_stations` with the table-qualified column, the only
     * spelling PeriodQueryBuilder allows for a name that used to live on
     * `periods`.
     */
    protected function hasClosedStation(Period $period): bool
    {
        return $period->stations()
            ->where('period_stations.status', PeriodStatus::Closed->value)
            ->exists();
    }

    /**
     * The station types the period being edited/created will cover, for the
     * form's preview. Derived from PeriodService::activeStationTypesForMill()
     * — the same call create() and update()'s backfill use — never from a
     * rule spelled out again here.
     *
     * `is_new` marks a type that has no row yet: on a create that is all of
     * them, on an edit it is exactly what update()'s backfill will add.
     *
     * @param  array<string, string>  $labels  code => name
     * @return list<array{code: string, label: string, is_new: bool}>
     */
    protected function stationPreview(PeriodService $service, array $labels): array
    {
        if (! $this->showForm || $this->business_unit_id === '') {
            return [];
        }

        $existing = $this->editingId !== null
            ? PeriodStation::query()
                ->where('period_id', $this->editingId)
                ->pluck('station_type')
                ->all()
            : [];

        return array_map(
            fn (string $code) => [
                'code' => $code,
                'label' => $labels[$code] ?? $code,
                'is_new' => ! in_array($code, $existing, true),
            ],
            $service->activeStationTypesForMill($this->business_unit_id)
        );
    }

    public function render()
    {
        $service = app(PeriodService::class);

        $result = $service->listPeriods(
            $this->page,
            $this->perPage,
            $this->filterBusinessUnitId !== '' ? $this->filterBusinessUnitId : null,
            $this->filterStatus !== '' ? $this->filterStatus : null
        );

        // code => name, so the form's station preview can render a human
        // label without a second query.
        $stationTypeLabels = collect($service->stationTypeOptions())
            ->mapWithKeys(fn (array $option) => [$option['code'] => $option['name']])
            ->all();

        return view('livewire.master-data.kelola-periode-pelaporan', [
            'periods' => $result['data'],
            'meta' => $result['meta'],
            'businessUnitOptions' => $service->businessUnitOptions(),
            'stationTypeLabels' => $stationTypeLabels,
            'stationPreview' => $this->stationPreview($service, $stationTypeLabels),
        ]);
    }
}
