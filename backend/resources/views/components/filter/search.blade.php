{{--
    x-filter.search — kolom pencarian dengan ikon kaca pembesar (diberi oleh
    x-filter.field icon="search") dan tombol hapus.

    wire:model.live.debounce tetap ditulis di sini dari props `model` +
    `debounce`, sama persis dengan binding lama layar pemanggil. Tombol
    hapus memakai $set, sehingga hook updated<Prop>() layar (reset halaman,
    dsb.) tetap berjalan seperti saat user menghapus teks dengan keyboard.
    Atribut lain (id, data-testid, placeholder) diteruskan ke <input>.
--}}
@props(['model', 'value' => '', 'debounce' => '300ms'])
<div class="fb-search">
    <input type="search" autocomplete="off"
           wire:model.live.debounce.{{ $debounce }}="{{ $model }}"
           {{ $attributes->class(['fb-control', 'fb-control--search']) }}>
    @if ((string) $value !== '')
        <button type="button" class="fb-search__clear" wire:click="$set('{{ $model }}', '')"
                aria-label="Hapus pencarian" title="Hapus pencarian" data-testid="search-clear">
            <x-filter.icon name="x" />
        </button>
    @endif
</div>
