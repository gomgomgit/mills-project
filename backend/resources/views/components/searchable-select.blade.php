{{--
    Reusable typeable/searchable single-select combobox — the ONE
    implementation every plain `<select>` in the web app (master-data
    parent-FK pickers, list filters, the login business-area picker, data
    browser filters, the Station type enum) is replaced with. Built with
    Alpine.js (already bundled by Livewire 3 — no separate script tag or
    build step needed, see vendor/livewire/livewire/dist/livewire.esm.js).

    Usage — drop-in replacement for `<select wire:model="prop">`:

        <x-searchable-select
            id="corporate_id"
            wire:model="corporate_id"
            :options="collect($corporateOptions)->map(fn ($o) => ['value' => $o['id'], 'label' => $o['name']])->all()"
            placeholder="-- Pilih Corporate --"
            empty-message="Belum ada Corporate. Buat Corporate terlebih dahulu."
            class="kc-form-field__input @error('corporate_id') kc-form-field__input--error @enderror"
        />

    `wire:model.live="prop"` works identically (deferred vs live is
    detected from whichever modifier the caller actually wrote — see the
    $modelIsLive extraction below — so screens like KelolaMachineryGroup's
    `station_id` / KelolaMachinery's `machinery_group_id`, which rely on
    `updated<Prop>()` firing immediately, keep working unmodified).

    Two-way binding mechanism: rather than forwarding `wire:model` onto a
    hidden input and re-dispatching DOM events (fragile — depends on
    exactly how Livewire's Alpine integration listens for input/change),
    this component entangles this Alpine component's own `selected` value
    directly with the Livewire property via `$wire.entangle(name, live)`
    — the officially supported way to back a fully custom Alpine input
    with a Livewire property (see Livewire\Features\SupportEntangle /
    generateEntangleFunction in the vendor bundle). Reads always reflect
    the live server-side property value (e.g. after openEditForm() sets
    corporate_id server-side and re-renders); writes propagate back
    deferred or immediately exactly like a plain wire:model[.live] select
    would.

    Options mapping is deliberately NOT this component's job (kept
    generic/reusable) — callers map their own differently-shaped option
    data ({id,name} / {id,group_code} / enum {value,label} / Eloquent
    models) onto the {value,label} shape before passing :options, in the
    consuming Blade view.

    Known limitation (see final report `known_issues`): the filtering /
    keyboard-navigation logic below runs entirely in Alpine (client-side
    JS) and is NOT exercised by this project's PHPUnit/Livewire Feature
    tests, which never execute JavaScript — those tests set the bound
    Livewire property directly (`->set('corporate_id', $id)`), which
    still works because it targets the same underlying property this
    component entangles with, not the DOM. The typing/arrow-key/Enter/
    Escape behavior itself is unverified by any automated test in this
    sandbox.
--}}
@props([
    'options' => [],
    'placeholder' => 'Pilih...',
    'emptyMessage' => null,
    'id' => null,
])

@php
    $modelProperty = null;
    $modelIsLive = false;

    foreach ($attributes->getAttributes() as $attributeName => $attributeValue) {
        if ($attributeName === 'wire:model' || str_starts_with($attributeName, 'wire:model.')) {
            $modelProperty = $attributeValue;
            $modelIsLive = str_contains($attributeName, 'live');
            break;
        }
    }

    $normalizedOptions = collect($options)
        ->map(fn ($option) => [
            'value' => (string) ($option['value'] ?? ''),
            'label' => (string) ($option['label'] ?? ''),
        ])
        ->values()
        ->all();

    $baseId = $id ?: ('ss-'.substr(md5(uniqid('', true)), 0, 8));
    $listboxId = $baseId.'-listbox';

    $otherAttributes = $attributes->whereDoesntStartWith('wire:model');

    $entangleExpression = $modelProperty !== null
        ? '$wire.entangle('.\Illuminate\Support\Js::from($modelProperty).', '.($modelIsLive ? 'true' : 'false').')'
        : 'null';
@endphp

{{-- Style + ssSelect() TIDAK lagi dipancarkan dari sini dengan once: kalau
     combobox pertama di sebuah halaman baru muncul lewat morph Livewire
     (mis. di dalam modal Edit Periode di Detail Periode), <script> yang
     disisipkan morph TIDAK pernah dieksekusi browser → ssSelect tidak
     terdefinisi → combobox kosong + listbox kosong terbuka (temuan audit
     2026-10-04 #8). Sekarang dimuat sekali per halaman oleh shell
     (components/layouts/app.blade.php → x-searchable-select-assets). --}}

<div
    {{-- x-data SENGAJA tidak memuat opsi: atribut ini harus identik di
         setiap render. Kalau berubah (opsi baru setelah BU diganti), morph
         menginisialisasi ulang komponen Alpine sementara listbox yang
         wire:ignore masih terikat ke scope lama → listbox terbuka/tertutup
         tidak sinkron. Opsi dibaca dari data-ss-options (lihat init()). --}}
    x-data="ssSelect(@js((string) $placeholder), {!! $entangleExpression !!})"
    data-ss-options="{{ json_encode($normalizedOptions) }}"
    class="ss-combobox"
    @click.outside="closeList()"
>
    <input
        type="text"
        role="combobox"
        aria-haspopup="listbox"
        autocomplete="off"
        id="{{ $baseId }}"
        :aria-expanded="open ? 'true' : 'false'"
        aria-controls="{{ $listboxId }}"
        :aria-activedescendant="highlighted > -1 ? '{{ $baseId }}-option-' + highlighted : null"
        x-ref="input"
        :value="displayValue"
        placeholder="Ketik untuk mencari..."
        @focus="openList()"
        @click="openList()"
        @input="onInput($event)"
        @keydown.down.prevent="move(1)"
        @keydown.up.prevent="move(-1)"
        @keydown.enter.prevent="selectHighlighted()"
        @keydown.escape.prevent.stop="closeList()"
        @keydown.tab="closeList()"
        {{ $otherAttributes->merge(['class' => 'ss-combobox__input']) }}
    >

    {{-- wire:ignore: subtree ini MILIK Alpine (x-show + template x-for).
         Tanpa ini morph Livewire menyamakannya dengan HTML server — membuang
         <li> hasil render x-for (pageerror "index/option is not defined",
         opsi hantu kosong) dan menghapus style display:none dari x-show
         (listbox terbuka sendiri menutupi kolom di bawahnya). Opsi baru
         tetap sampai lewat data-ss-options di elemen akar. --}}
    <ul
        wire:ignore
        role="listbox"
        id="{{ $listboxId }}"
        x-ref="listbox"
        x-show="open"
        x-cloak
        class="ss-combobox__listbox"
    >
        <template x-if="filtered.length === 0">
            <li class="ss-combobox__empty">Tidak ada hasil.</li>
        </template>
        <template x-for="(option, index) in filtered" :key="option.value + '-' + index">
            <li
                role="option"
                :id="'{{ $baseId }}-option-' + index"
                :aria-selected="option.value === selected ? 'true' : 'false'"
                :data-highlighted="index === highlighted ? 'true' : 'false'"
                class="ss-combobox__option"
                :class="{ 'ss-combobox__option--highlighted': index === highlighted }"
                @mousedown.prevent="pick(option)"
                @mouseenter="highlighted = index"
                x-text="option.label"
            ></li>
        </template>
    </ul>

    @if ($emptyMessage && count($normalizedOptions) === 0)
        <p class="ss-combobox__empty-hint">{{ $emptyMessage }}</p>
    @endif
</div>
