<x-layouts.app title="Laporan Clarification">
    {{-- Kosakata CSS `md-*` bersama, dimuat lewat slot `styles` milik layout
         (konvensi repo — lihat dashboard/laporan-boiler-room.blade.php dan
         dashboard/laporan-cages-track.blade.php). Termasuk kosakata grafik
         garis tiga seri `md-lc*` yang ditambahkan untuk layar ini.

         Ditaruh DI SINI, bukan di dalam komponen Livewire, karena dua hal:
         1. Livewire 3 memasang wire:id pada elemen ter-render PERTAMA. Bila
            partial ber-<style> ini berada di dalam atau di atas root
            komponen, atribut itu bisa menempel pada <style> dan SELURUH
            wire:model berhenti bekerja. Component test tidak menangkapnya;
            browser test menangkapnya. Harganya sudah dibayar di screen-140.
         2. CSS milik halaman, bukan milik state komponen. Menjaganya di luar
            komponen membuat HTML yang diperiksa Livewire::test() berisi
            markup saja. --}}
    <x-slot:styles>
        @include('dashboard.partials.report-styles')
    </x-slot:styles>

    {{ $slot }}
</x-layouts.app>
