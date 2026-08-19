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

@once
<style>
    /* Shared searchable-select combobox styling — emitted only once per
       page (this whole block is wrapped in Blade's "once" directive
       further up) even though the component itself may be instantiated
       several times (e.g. one list filter + one modal-form select per
       screen). Colors/radii are the same literal values every screen in
       this codebase already inlines in its own <style> block (brand
       #249360, border #d1d5db, text #1f2937/#6b7280, radius 6px) —
       intentionally hardcoded rather than read from CSS custom
       properties, since different shells in this app define those under
       different names (--kc-*, --color-*, --wb-*, ...). */
    [x-cloak] {
        display: none !important;
    }

    .ss-combobox {
        position: relative;
        width: 100%;
    }

    .ss-combobox__input {
        width: 100%;
        box-sizing: border-box;
        cursor: text;
        background-color: #fff;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6' fill='none'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%236b7280' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 10px 6px;
        padding-right: 30px;
    }

    .ss-combobox__input:disabled {
        cursor: not-allowed;
        background-color: #f3f4f6;
        color: #6b7280;
    }

    .ss-combobox__listbox {
        position: absolute;
        z-index: 60;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        margin: 0;
        padding: 4px;
        list-style: none;
        max-height: 240px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        box-shadow: 0 12px 28px rgba(17, 24, 39, 0.16);
    }

    .ss-combobox__option {
        padding: 8px 10px;
        border-radius: 4px;
        font-size: 14px;
        line-height: 1.3;
        color: #1f2937;
        cursor: pointer;
    }

    .ss-combobox__option[aria-selected="true"] {
        font-weight: 600;
        color: #1d7a4e;
    }

    .ss-combobox__option--highlighted {
        background: rgba(36, 147, 96, 0.12);
    }

    .ss-combobox__empty {
        padding: 10px;
        font-size: 13px;
        color: #6b7280;
        text-align: center;
        cursor: default;
    }

    .ss-combobox__empty-hint {
        margin: 4px 0 0;
        font-size: 12px;
        color: #6b7280;
    }
</style>
<script>
    function ssSelect(options, placeholderLabel, entangledSelected) {
        return {
            open: false,
            query: '',
            highlighted: -1,
            options: options,
            selected: entangledSelected !== null ? entangledSelected : '',

            get allOptions() {
                return [{ value: '', label: placeholderLabel }, ...this.options];
            },

            get filtered() {
                const q = this.query.trim().toLowerCase();

                if (q === '') {
                    return this.allOptions;
                }

                return this.allOptions.filter((option) => option.label.toLowerCase().includes(q));
            },

            get selectedLabel() {
                const match = this.allOptions.find((option) => option.value === this.selected);

                return match ? match.label : '';
            },

            get displayValue() {
                return this.open ? this.query : this.selectedLabel;
            },

            openList() {
                if (this.open) {
                    return;
                }

                this.open = true;
                this.query = '';
                this.highlighted = this.filtered.findIndex((option) => option.value === this.selected);
            },

            closeList() {
                this.open = false;
                this.highlighted = -1;
            },

            onInput(event) {
                this.query = event.target.value;
                this.open = true;
                this.highlighted = this.filtered.length ? 0 : -1;
            },

            move(delta) {
                if (!this.open) {
                    this.openList();

                    return;
                }

                if (this.filtered.length === 0) {
                    return;
                }

                let next = this.highlighted + delta;

                if (next < 0) {
                    next = this.filtered.length - 1;
                }

                if (next >= this.filtered.length) {
                    next = 0;
                }

                this.highlighted = next;
                this.$nextTick(() => this.scrollHighlightedIntoView());
            },

            selectHighlighted() {
                if (!this.open) {
                    return;
                }

                if (this.highlighted > -1 && this.highlighted < this.filtered.length) {
                    this.pick(this.filtered[this.highlighted]);
                } else {
                    this.closeList();
                }
            },

            pick(option) {
                this.selected = option.value;
                this.closeList();
                this.$nextTick(() => this.$refs.input && this.$refs.input.focus());
            },

            scrollHighlightedIntoView() {
                if (!this.$refs.listbox) {
                    return;
                }

                const el = this.$refs.listbox.querySelector('[data-highlighted="true"]');

                if (el && el.scrollIntoView) {
                    el.scrollIntoView({ block: 'nearest' });
                }
            },
        };
    }
</script>
@endonce

<div
    x-data="ssSelect(@js($normalizedOptions), @js((string) $placeholder), {!! $entangleExpression !!})"
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

    <ul
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
