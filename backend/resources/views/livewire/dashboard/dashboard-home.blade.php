<div class="dash">
    <div class="dash__header">
        <div>
            <h2 class="dash__title">Ringkasan Operasional</h2>
            <p class="dash__subtitle">KPI Weighbridge, Grading, dan Cages Track per rentang tanggal & business unit</p>
        </div>
    </div>

    @if ($errorMessage)
        <div class="dash-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="dash-filterbar">
        <div class="dash-filterbar__field">
            <label for="date_from" class="dash-filterbar__label">Tanggal Dari</label>
            <input type="date" id="date_from" wire:model.live="date_from" class="dash-filterbar__input">
        </div>

        <div class="dash-filterbar__field">
            <label for="date_to" class="dash-filterbar__label">Tanggal Sampai</label>
            <input type="date" id="date_to" wire:model.live="date_to" class="dash-filterbar__input">
        </div>

        <div class="dash-filterbar__field">
            <label for="business_unit_id" class="dash-filterbar__label">Business Unit</label>
            <x-searchable-select
                id="business_unit_id"
                wire:model.live="business_unit_id"
                :options="collect($businessUnits)->map(fn ($businessUnit) => ['value' => $businessUnit->id, 'label' => $businessUnit->name])->all()"
                placeholder="Semua Business Unit"
                class="dash-filterbar__input"
            />
        </div>
    </div>

    <div class="dash-cards">
        <a href="{{ $this->dataBrowserUrl('data.weighbridge') }}" class="dash-card" data-testid="dash-card-weighbridge">
            <p class="dash-card__label">Weighbridge</p>
            <p class="dash-card__value">{{ $summary['weighbridge']['count'] }}</p>
            <p class="dash-card__meta">Total Net Weight: {{ number_format($summary['weighbridge']['total_net_weight'], 2) }} kg</p>
        </a>

        <a href="{{ $this->dataBrowserUrl('data.grading') }}" class="dash-card" data-testid="dash-card-grading">
            <p class="dash-card__label">Grading</p>
            <p class="dash-card__value">{{ $summary['grading']['count'] }}</p>
            <p class="dash-card__meta">Total Netto: {{ number_format($summary['grading']['total_netto'], 2) }} kg</p>
            <p class="dash-card__meta">Total Quantity: {{ number_format($summary['grading']['total_quantity'], 2) }} tandan</p>
        </a>

        <a href="{{ $this->dataBrowserUrl('data.cages-track') }}" class="dash-card" data-testid="dash-card-cages-track">
            <p class="dash-card__label">Cages Track</p>
            <p class="dash-card__value">{{ $summary['cages_track']['count'] }}</p>
            <p class="dash-card__meta">Total Cages Tipped: {{ $summary['cages_track']['total_cages_tipped'] }}</p>
        </a>
    </div>

    <div class="dash-table-wrap">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Stasiun</th>
                    <th>Jumlah Record</th>
                    <th>Agregat Utama</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Weighbridge</td>
                    <td>{{ $summary['weighbridge']['count'] }}</td>
                    <td>{{ number_format($summary['weighbridge']['total_net_weight'], 2) }} kg (Net Weight)</td>
                </tr>
                <tr>
                    <td>Grading</td>
                    <td>{{ $summary['grading']['count'] }}</td>
                    <td>{{ number_format($summary['grading']['total_netto'], 2) }} kg (Netto) / {{ number_format($summary['grading']['total_quantity'], 2) }} tandan (Quantity)</td>
                </tr>
                <tr>
                    <td>Cages Track</td>
                    <td>{{ $summary['cages_track']['count'] }}</td>
                    <td>{{ $summary['cages_track']['total_cages_tipped'] }} cages tipped</td>
                </tr>
            </tbody>
        </table>
    </div>

    <style>
        .dash__header { margin-bottom: 16px; }
        .dash__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .dash__subtitle { margin: 0; font-size: 14px; color: #6b7280; }
        .dash-alert { background: #fee2e2; color: #b91c1c; padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; }
        .dash-filterbar { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
        .dash-filterbar__field { display: flex; flex-direction: column; gap: 4px; }
        .dash-filterbar__label { font-size: 12px; color: #6b7280; }
        .dash-filterbar__input { padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; min-width: 200px; }
        .dash-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .dash-card { display: block; padding: 20px; background: #fff; border: 1px solid #d1d5db; border-radius: 12px; text-decoration: none; color: inherit; box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
        .dash-card:hover { border-color: #249360; }
        .dash-card__label { margin: 0 0 8px; font-size: 14px; color: #6b7280; }
        .dash-card__value { margin: 0 0 8px; font-size: 32px; font-weight: 700; }
        .dash-card__meta { margin: 0; font-size: 13px; color: #6b7280; }
        .dash-table-wrap { background: #fff; border: 1px solid #d1d5db; border-radius: 12px; overflow: hidden; }
        .dash-table { width: 100%; border-collapse: collapse; }
        .dash-table th, .dash-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
        .dash-table th { background: #f9fafb; font-weight: 600; color: #6b7280; }
    </style>
</div>
