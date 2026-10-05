{{--
    x-filter.field — satu field berlabel di dalam x-filter.bar.

    Isi slot adalah kontrolnya sendiri (native <select class="fb-control
    fb-control--select">, <x-searchable-select class="fb-control">,
    <x-filter.search>, <x-filter.date-range>) — wire:model, id, dan
    data-testid tetap ditulis layar pemanggil, jadi perilaku tiap layar
    tidak berubah.

    `static` = konteks tetap (mis. mill akun Supervisor / Mill Management):
    dirender sebagai keterangan, BUKAN input disabled/readonly — konvensi
    web proyek ini. `for` diabaikan saat static (tidak ada kontrol).

    Ukuran: sm 160 · md 220 · lg 280 · range 300 · auto (selebar isi) ·
    grow (melebar s/d 420).
    Di ponsel semuanya selebar penuh (filter-assets.blade.php).
--}}
@props([
    'label',
    'for' => null,
    'icon' => null,
    'size' => 'md',
    'required' => false,
    'static' => null,
    'staticTestid' => null,
    'staticTitle' => null,
])
<div {{ $attributes->class(['fb-field', 'fb-field--'.$size]) }}>
    <div class="fb-field__head">
        @if ($for !== null && $static === null)
            <label class="fb-field__label" for="{{ $for }}">{{ $label }}</label>
        @else
            <span class="fb-field__label">{{ $label }}</span>
        @endif
        @if ($required)
            <span class="fb-badge fb-badge--req">Wajib</span>
        @endif
        {{ $badge ?? '' }}
    </div>

    @if ($static !== null)
        <p class="fb-field__static" @if ($staticTestid) data-testid="{{ $staticTestid }}" @endif @if ($staticTitle) title="{{ $staticTitle }}" @endif>
            @if ($icon)
                <x-filter.icon :name="$icon" />
            @endif
            <strong>{{ $static }}</strong>
        </p>
    @else
        <div @class(['fb-field__box', 'fb-field__box--icon' => $icon !== null])>
            @if ($icon)
                <x-filter.icon :name="$icon" class="fb-field__icon" />
            @endif
            {{ $slot }}
        </div>
    @endif
</div>
