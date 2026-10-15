<x-layouts.app title="Laporan Periode Engine Room">
    {{-- Kosakata CSS `md-*` bersama, dimuat lewat slot `styles` milik layout
         (konvensi repo — lihat dashboard/laporan-effluent-plant.blade.php dan
         dashboard/laporan-kernel-plant.blade.php).

         Layar ini TIDAK menambah satu kelas baru pun ke partial bersama itu.
         Seluruh kelas yang dipakai markupnya sudah terdefinisi di sana:
         md-hero*, md-chip*, md-filters* (lewat <x-report-filter-bar>),
         md-row / --2 / --2-1 / --3, md-card*, md-kpis / --2 / --3 / --4,
         md-kpi*, md-budget*, md-bar / --lg, md-explain, md-empty*, md-notes*,
         md-trendchart* / --days, md-legend*, md-scrollhint, md-details,
         md-recap, md-table, md-pill, is-muted. Kelas yang dipakai markup
         WAJIB punya definisi, dan itu diperiksa pada hasil compile, bukan
         pada sumbernya.

         KOTAK KETERANGAN MEMAKAI `.md-explain`, BUKAN `.md-threshold` —
         keduanya tampil identik, dan yang berbeda hanya namanya. Layar ini
         sengaja tidak menandai satu pun nilai di luar batas (Engine Room
         tidak punya tabel master target), dan ketiadaan itu diasersi MENURUT
         NAMA KELAS pada HTML ter-render; memakai kelas bernama 'threshold'
         untuk menjelaskan bahwa tidak ada ambang akan memerahkan asersi itu
         justru karena namanya. Konvensi ini lahir di screen-133 (Storage
         Tank) dan dipakai ulang di screen-156.

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
