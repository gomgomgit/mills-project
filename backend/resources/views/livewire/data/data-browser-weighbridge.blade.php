<div class="wb-browser" wire:loading.class="wb-browser--busy" wire:target="nextPage,previousPage,goToPage">
    <div class="wb-browser__header">
        <div>
            <h2 class="wb-browser__title">Data Browser Weighbridge</h2>
            <p class="wb-browser__subtitle">Riwayat data log sheet stasiun Weighbridge</p>
        </div>

        <div class="wb-browser__export">
            <a href="{{ route('data.weighbridge.create') }}" class="wb-button wb-button--secondary" data-testid="add-data-button">
                Tambah Data
            </a>
            <a href="{{ $exportCsvUrl }}" class="wb-button wb-button--secondary" target="_blank" rel="noopener">
                Ekspor CSV
            </a>
            <a href="{{ $exportExcelUrl }}" class="wb-button wb-button--secondary" target="_blank" rel="noopener">
                Ekspor Excel
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="wb-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="wb-filterbar">
        <div class="wb-filterbar__field">
            <label for="date_from" class="wb-filterbar__label">Tanggal Dari</label>
            <input type="date" id="date_from" wire:model.live="date_from" class="wb-filterbar__input">
        </div>

        <div class="wb-filterbar__field">
            <label for="date_to" class="wb-filterbar__label">Tanggal Sampai</label>
            <input type="date" id="date_to" wire:model.live="date_to" class="wb-filterbar__input">
        </div>

        <div class="wb-filterbar__field">
            <label for="weighbridge_type" class="wb-filterbar__label">Tipe</label>
            <select id="weighbridge_type" wire:model.live="weighbridge_type" class="wb-filterbar__input">
                <option value="">Semua Tipe</option>
                <option value="receive">Receive</option>
                <option value="dispatch">Dispatch</option>
            </select>
        </div>

        <div class="wb-filterbar__field">
            <label for="business_unit_id" class="wb-filterbar__label">Business Unit</label>
            <x-searchable-select
                id="business_unit_id"
                wire:model.live="business_unit_id"
                :options="collect($businessUnits)->map(fn ($businessUnit) => ['value' => $businessUnit->id, 'label' => $businessUnit->name])->all()"
                placeholder="Semua Business Unit"
                class="wb-filterbar__input"
            />
        </div>
    </div>

    <div class="wb-table-wrap">
        <table class="wb-table">
            <thead class="wb-table__head">
                <tr>
                    <th>No. Kartu WB</th>
                    <th>Tipe</th>
                    <th>Tanggal &amp; Waktu</th>
                    <th>No. Kendaraan</th>
                    <th>Nama Pengemudi</th>
                    <th>Tujuan Muatan</th>
                    <th>Berat Bersih (kg)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $hasDetailRoute = \Illuminate\Support\Facades\Route::has('data.weighbridge.detail');
                        $detailHref = $hasDetailRoute
                            ? route('data.weighbridge.detail', ['id' => $record['id']])
                            : '#';
                    @endphp
                    <tr
                        class="wb-table__row @if(!$hasDetailRoute) wb-table__row--static @endif"
                        wire:key="wb-record-{{ $record['id'] }}"
                        @if ($hasDetailRoute)
                            onclick="window.location.href='{{ $detailHref }}'"
                        @endif
                    >
                        <td>{{ $record['wb_card_number'] }}</td>
                        <td>
                            <span class="wb-badge wb-badge--type-{{ $record['weighbridge_type'] }}">{{ ucfirst($record['weighbridge_type']) }}</span>
                        </td>
                        <td>{{ $record['record_datetime'] ? \Illuminate\Support\Carbon::parse($record['record_datetime'])->format('d/m/Y H:i') : '-' }}</td>
                        <td>{{ $record['vehicle_number'] }}</td>
                        <td>{{ $record['driver_name'] }}</td>
                        <td>{{ $record['destination'] ?: '-' }}</td>
                        <td>{{ $record['net_weight'] !== null ? number_format($record['net_weight'], 2) : '-' }}</td>
                        <td>
                            <span class="wb-badge wb-badge--{{ $record['status'] }}">{{ $record['status'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="wb-table__row wb-table__row--static">
                        <td colspan="8">
                            <div class="wb-empty">
                                <div class="wb-empty__illustration" aria-hidden="true">&#128203;</div>
                                <p class="wb-empty__title">Tidak ada data</p>
                                <p class="wb-empty__subtitle">Tidak ada data weighbridge yang cocok dengan filter saat ini.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meta['total'] > 0)
        <div class="wb-pagination">
            <span class="wb-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="wb-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    class="wb-button wb-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    class="wb-button wb-button--ghost"
                    @if ($meta['page'] >= $meta['total_pages']) disabled @endif
                >
                    Berikutnya &rarr;
                </button>
            </div>
        </div>
    @endif

    <style>
        /* Design tokens — uiux-spec: brand #249360. Inlined here (same
           approach as resources/views/data/weighbridge.blade.php and every
           other Livewire view in this codebase so far) since the backend
           scaffold has no frontend build pipeline yet — see
           implementation_notes. */
        .wb-browser {
            --wb-brand: #249360;
            --wb-brand-hover: #1d7a4e;
            --wb-destructive: #DC2626;
            --wb-text: #1f2937;
            --wb-text-muted: #6b7280;
            --wb-border: #d1d5db;
            --wb-radius-input: 6px;
            --wb-radius-button: 8px;
            color: var(--wb-text);
        }

        .wb-browser--busy {
            opacity: 0.85;
        }

        .wb-browser__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }

        .wb-browser__title {
            margin: 0 0 4px;
            font-size: 20px;
            font-weight: 700;
        }

        .wb-browser__subtitle {
            margin: 0;
            font-size: 14px;
            color: var(--wb-text-muted);
        }

        .wb-browser__export {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .wb-alert {
            margin-bottom: 16px;
            padding: 10px 12px;
            border-radius: var(--wb-radius-input);
            background: #fef2f2;
            border: 1px solid var(--wb-destructive);
            color: var(--wb-destructive);
            font-size: 14px;
        }

        .wb-filterbar {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
            padding: 16px;
            background: #fff;
            border: 1px solid var(--wb-border);
            border-radius: 10px;
        }

        .wb-filterbar__field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 180px;
        }

        .wb-filterbar__label {
            font-size: 13px;
            font-weight: 500;
            color: var(--wb-text-muted);
        }

        .wb-filterbar__input {
            padding: 8px 10px;
            font-size: 14px;
            font-family: inherit;
            color: var(--wb-text);
            border: 1px solid var(--wb-border);
            border-radius: var(--wb-radius-input);
            background: #fff;
        }

        .wb-filterbar__input:focus {
            outline: none;
            border-color: var(--wb-brand);
            box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15);
        }

        .wb-table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--wb-border);
            border-radius: 10px;
            background: #fff;
        }

        .wb-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .wb-table__head th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f9fafb;
            text-align: left;
            padding: 12px 16px;
            font-weight: 600;
            color: var(--wb-text-muted);
            border-bottom: 1px solid var(--wb-border);
            white-space: nowrap;
        }

        .wb-table__row td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--wb-border);
            white-space: nowrap;
        }

        .wb-table__row:last-child td {
            border-bottom: none;
        }

        .wb-table__row:not(.wb-table__row--static) {
            cursor: pointer;
        }

        .wb-table__row:not(.wb-table__row--static):hover {
            background: #f0fdf4;
        }

        .wb-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
            background: #f3f4f6;
            color: var(--wb-text-muted);
        }

        .wb-badge--saved,
        .wb-badge--synced {
            background: #f0fdf4;
            color: #16a34a;
        }

        .wb-badge--draft_ongoing,
        .wb-badge--draft_paused {
            background: #fffbeb;
            color: #b45309;
        }

        .wb-badge--type-receive {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .wb-badge--type-dispatch {
            background: #fdf4ff;
            color: #a21caf;
        }

        .wb-empty {
            padding: 48px 16px;
            text-align: center;
        }

        .wb-empty__illustration {
            font-size: 40px;
            margin-bottom: 12px;
        }

        .wb-empty__title {
            margin: 0 0 4px;
            font-size: 15px;
            font-weight: 600;
            color: var(--wb-text);
        }

        .wb-empty__subtitle {
            margin: 0;
            font-size: 13px;
            color: var(--wb-text-muted);
        }

        .wb-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 16px;
            font-size: 13px;
            color: var(--wb-text-muted);
        }

        .wb-pagination__controls {
            display: flex;
            gap: 8px;
        }

        .wb-button {
            padding: 8px 14px;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            border-radius: var(--wb-radius-button);
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .wb-button--secondary {
            background: var(--wb-brand);
            color: #fff;
        }

        .wb-button--secondary:hover {
            background: var(--wb-brand-hover);
        }

        .wb-button--ghost {
            background: #fff;
            color: var(--wb-text);
            border: 1px solid var(--wb-border);
        }

        .wb-button--ghost:hover:not(:disabled) {
            border-color: var(--wb-brand);
            color: var(--wb-brand);
        }

        .wb-button--ghost:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</div>
