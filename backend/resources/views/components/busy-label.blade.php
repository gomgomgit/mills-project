{{--
    x-busy-label — isi tombol aksi yang membaca/menulis data: teks normal
    saat diam, spinner + teks sibuk ("Menyimpan…") selama aksinya berjalan.
    CSS-nya (.ld-*) di components/loading-assets.blade.php.

    Pemakaian (Livewire) — tombolnya sendiri tetap memasang
    wire:loading.attr="disabled" + wire:target yang SAMA, supaya klik kedua
    tidak mengirim request ganda (hanya TOMBOL yang dinonaktifkan sesaat,
    bukan field form — konvensi web tanpa input disabled tetap berlaku):

        <button type="submit" wire:loading.attr="disabled" wire:target="save">
            <x-busy-label target="save" busy="Menyimpan…">Simpan</x-busy-label>
        </button>

    Tanpa `target` (tautan ekspor a[data-export-link]) kedua span di-toggle
    oleh JS loading-assets lewat aria-busy pada tautan.

    Props:
      target  (string|null) — ekspresi wire:target, mis. "save",
                              "toggleChecked", "removeDetailRow(3)"
      busy    (string)      — teks selama memproses
--}}
@props(['target' => null, 'busy' => 'Memproses…'])
@if ($target !== null)
<span class="ld-label ld-label--idle" wire:loading.remove wire:target="{{ $target }}">{{ $slot }}</span><span class="ld-label ld-label--busy" wire:loading.inline-flex wire:target="{{ $target }}"><span class="ld-spinner" aria-hidden="true"></span>{{ $busy }}</span>
@else
<span class="ld-label ld-label--idle">{{ $slot }}</span><span class="ld-label ld-label--busy ld-label--js"><span class="ld-spinner" aria-hidden="true"></span>{{ $busy }}</span>
@endif
