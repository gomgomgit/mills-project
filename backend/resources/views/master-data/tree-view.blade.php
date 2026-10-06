<x-layouts.app title="Struktur Mills">
    {{-- Kosakata CSS `md-*` milik layar ini, dimuat lewat slot `styles`
         milik layout — konvensi repo, pola yang sama dengan
         dashboard/laporan-pressing.blade.php.

         Ditaruh DI SINI, bukan di dalam komponen Livewire, karena dua hal:
         1. Livewire 3 memasang wire:id pada elemen ter-render PERTAMA. Bila
            partial ber-<style> ini berada di dalam atau di atas root
            komponen, atribut itu menempel pada <style> dan SELURUH
            wire:model berhenti bekerja — termasuk kotak penyaring dan
            setiap field modal. Component test tidak menangkapnya; browser
            test menangkapnya. Harganya sudah dibayar di screen-140.
         2. CSS milik halaman, bukan milik state komponen. Menjaganya di
            luar komponen membuat HTML yang diperiksa Livewire::test()
            berisi markup saja.

         Partial-nya milik layar ini SENDIRI, bukan
         dashboard/partials/report-styles.blade.php — partial itu dipakai
         sembilan laporan, dan menambah kelas ke sana akan menyentuh
         kesembilannya. --}}
    <x-slot:styles>
        @include('master-data.partials.master-data-styles')
    </x-slot:styles>

    {{ $slot }}
</x-layouts.app>
