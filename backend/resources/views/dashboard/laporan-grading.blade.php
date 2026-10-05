<x-layouts.app title="Laporan Grading">
    {{-- Kosakata CSS `md-*` bersama, dimuat lewat slot `styles` milik layout
         (konvensi repo — lihat dashboard/laporan-weighbridge.blade.php dan
         dashboard/laporan-storage-tank.blade.php). TIDAK ADA kelas CSS baru
         yang dibutuhkan layar ini, dan partial bersama itu sengaja tidak
         disentuh: menambah kelas ke sana akan menyentuh keenam laporan lain
         yang memakainya.

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
