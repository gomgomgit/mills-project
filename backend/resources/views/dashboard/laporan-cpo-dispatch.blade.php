<x-layouts.app title="Laporan Periode CPO Dispatch">
    {{-- Kosakata CSS `md-*` bersama, dimuat lewat slot `styles` milik layout
         (konvensi repo — satu berkas layout per laporan, lihat
         dashboard/laporan-kernel-dispatch.blade.php yang identik kecuali
         judulnya).

         LAYAR INI TIDAK MENAMBAH SATU KELAS BARU PUN ke partial bersama itu.
         Seluruh kelas yang dipakai markupnya sudah terdefinisi di sana:
         md-hero*, md-chip*, md-filters* (lewat <x-report-filter-bar>), md-body,
         md-card*, md-kpis / --2 / --3 / --4, md-kpi*, md-budget*, md-bar,
         md-explain, md-empty*, md-notes*, md-pill, md-details, md-recap,
         md-table, md-scrollhint, md-trendchart* (sebaran 24 jam, preseden
         laporan Weighbridge), is-muted. Kelas yang dipakai markup WAJIB punya
         definisi, dan itu diperiksa pada hasil COMPILE, bukan pada sumbernya.

         KOTAK KETERANGAN MEMAKAI `.md-explain`, BUKAN `.md-threshold`: layar ini
         sengaja tidak menandai satu pun nilai (mutu maupun waktu putar) sebagai
         di luar batas, dan ketiadaan penandaan diasersi MENURUT NAMA KELAS pada
         HTML ter-render; kelas bernama 'threshold' pada kotak yang menyatakan
         tidak ada ambang akan memerahkan asersi itu justru karena namanya.

         Ditaruh DI SINI, bukan di dalam komponen Livewire: Livewire 3 memasang
         wire:id pada elemen ter-render PERTAMA, dan partial ber-<style> di dalam
         atau di atas root komponen dapat menerima atribut itu sehingga SELURUH
         wire:model berhenti bekerja. --}}
    <x-slot:styles>
        @include('dashboard.partials.report-styles')
    </x-slot:styles>

    {{ $slot }}
</x-layouts.app>
