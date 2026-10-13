<x-layouts.app title="Laporan Periode Effluent Plant">
    {{-- Kosakata CSS `md-*` bersama, dimuat lewat slot `styles` milik layout
         (konvensi repo — lihat dashboard/laporan-kernel-plant.blade.php dan
         dashboard/laporan-boiler-room.blade.php).

         Layar ini memakai DUA kelas tata letak yang ditambahkan ke partial
         bersama itu bersamaan dengan screen-156: `.md-kpis--2` (blok kolam
         berisi tepat dua kartu) dan `.md-row--3` (tiga kartu berdampingan).
         Keduanya murni aditif — sejajar dengan `.md-kpis--3`/`.md-kpis--4`
         dan `.md-row--2` yang sudah ada — sehingga tidak satu pun laporan
         lain berubah tampilannya. Kelas yang dipakai markup WAJIB punya
         definisi, dan itu diperiksa pada hasil compile, bukan pada sumbernya.

         Ditaruh DI SINI, bukan di dalam komponen Livewire, karena dua hal:
         1. Livewire 3 memasang wire:id pada elemen ter-render PERTAMA. Bila
            partial ber-<style> ini berada di dalam atau di atas root
            komponen, atribut itu bisa menempel pada <style> dan SELURUH
            wire:model berhenti bekerja — termasuk ketiga pemilih layar ini.
            Component test tidak menangkapnya; browser test menangkapnya.
            Harganya sudah dibayar di screen-140.
         2. CSS milik halaman, bukan milik state komponen. Menjaganya di luar
            komponen membuat HTML yang diperiksa Livewire::test() berisi
            markup saja. --}}
    <x-slot:styles>
        @include('dashboard.partials.report-styles')
    </x-slot:styles>

    {{ $slot }}
</x-layouts.app>
