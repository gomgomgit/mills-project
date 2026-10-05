{{--
    x-filter.bar — wadah filter bersama untuk seluruh layar daftar web (18
    Data Browser, master data, Kelola User, Mills Setting, Laporan
    Manajemen). Bahasa visualnya sama dengan toolbar Laporan Stasiun
    (components/report-filter-bar.blade.php); CSS-nya di
    components/filter-assets.blade.php (dimuat sekali oleh shell).

    Slot:
      - default : field-field (x-filter.field)
      - actions : tombol di kanan baris field (opsional)
      - note    : catatan pendek satu baris di ringkasan (opsional)
    Props:
      - total   : jumlah hasil (null = tidak ditampilkan)
      - active  : jumlah filter yang sedang menyimpang dari bawaan
      - reset   : nama aksi Livewire untuk Reset filter (mis. 'resetFilters');
                  tombolnya hanya muncul bila active > 0
--}}
@props([
    'label' => 'Filter',
    'total' => null,
    'noun' => 'data',
    'active' => 0,
    'reset' => null,
    'testid' => 'filter-bar',
])
<section {{ $attributes->class(['fb-bar']) }} aria-label="{{ $label }}" data-testid="{{ $testid }}">
    <div class="fb-bar__fields">
        {{ $slot }}
    </div>

    @isset($actions)
        <div class="fb-bar__actions">{{ $actions }}</div>
    @endisset

    @if ($total !== null || $active > 0 || isset($note))
        <div class="fb-bar__foot">
            <p class="fb-bar__summary">
                @if ($total !== null)
                    <span class="fb-count" data-testid="filter-result-count">
                        <x-filter.icon name="list" />
                        <span><strong>{{ number_format((int) $total, 0, ',', '.') }}</strong> {{ $noun }}</span>
                    </span>
                @endif
                @if ($active > 0)
                    <span class="fb-badge fb-badge--active" data-testid="filter-active-count">{{ $active }} filter aktif</span>
                @endif
                @isset($note)
                    <span class="fb-bar__note">
                        <x-filter.icon name="info" />
                        <span>{{ $note }}</span>
                    </span>
                @endisset
            </p>
            @if ($reset !== null && $active > 0)
                <button type="button" class="fb-reset" wire:click="{{ $reset }}" data-testid="filter-reset">
                    <x-filter.icon name="reset" />
                    Reset filter
                </button>
            @endif
        </div>
    @endif
</section>
