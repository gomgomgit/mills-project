{{--
    x-filter.date-range — dua <input type="date"> (Dari – Sampai) dalam satu
    field. Native date input: tanggal bisa DIKETIK manual dan dipilih lewat
    pemilih bawaan browser — konvensi web proyek ini. Tiap input tetap punya
    <label> sendiri (tersembunyi visual) dan id lama (#date_from / #date_to
    dipakai spec e2e), serta wire:model.live yang sama dengan sebelumnya.
--}}
@props([
    'fromId' => 'date_from',
    'toId' => 'date_to',
    'fromModel' => 'date_from',
    'toModel' => 'date_to',
    'fromLabel' => 'Tanggal Dari',
    'toLabel' => 'Tanggal Sampai',
])
<div class="fb-range">
    <label class="fb-sr" for="{{ $fromId }}">{{ $fromLabel }}</label>
    <input type="date" id="{{ $fromId }}" wire:model.live="{{ $fromModel }}" class="fb-control fb-control--date" title="{{ $fromLabel }}">
    <span class="fb-range__sep" aria-hidden="true">–</span>
    <label class="fb-sr" for="{{ $toId }}">{{ $toLabel }}</label>
    <input type="date" id="{{ $toId }}" wire:model.live="{{ $toModel }}" class="fb-control fb-control--date" title="{{ $toLabel }}">
</div>
