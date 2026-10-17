<x-layouts.app title="Laporan Periode Solid Waste Disposal">
    {{-- Kosakata CSS `md-*` bersama, dimuat lewat slot `styles` milik layout
         (konvensi repo — satu berkas layout per laporan, lihat
         dashboard/laporan-process-water.blade.php dan
         dashboard/laporan-weighbridge.blade.php yang identik kecuali judulnya).

         LAYAR INI TIDAK MENAMBAH SATU KELAS BARU PUN ke partial bersama itu.
         Seluruh kelas yang dipakai markupnya sudah terdefinisi di sana:
         md-hero*, md-chip*, md-filters* (lewat <x-report-filter-bar>), md-body,
         md-row / --2, md-card*, md-kpis / --2 / --3 / --4, md-kpi*, md-budget*,
         md-bar / --lg, md-explain, md-empty*, md-notes*, md-pill, md-details,
         md-recap, md-table, md-lc*, md-legend*, md-scrollhint / --lc, is-muted.
         Kelas yang dipakai markup WAJIB punya definisi, dan itu diperiksa pada
         hasil COMPILE, bukan pada sumbernya.

         MOCK LAYAR INI MEMPERKENALKAN EMPAT KOSAKATA TAMBAHAN yang TIDAK ada di
         partial bersama — `md-paired*`, `md-kpi--flag`, `md-cant*`, `md-no-sum`
         — dan keempatnya sengaja TIDAK dipakai: menambahkannya ke partial
         bersama berarti menyentuh keenam belas laporan lain yang memuatnya.
         Preseden ini lahir di screen-143 (Weighbridge), yang menolak `md-group*`
         dan `md-hc*` milik mock-nya dengan alasan yang sama. Pemetaannya:
         md-paired -> .md-card (kotak yang memuat KPI dan rekapnya sekaligus,
         sehingga hubungan DOM 'rekap purpose di dalam container kartu total'
         tetap utuh), md-kpi--flag -> .md-kpi biasa di dalam kartunya sendiri,
         md-cant dan md-no-sum -> .md-explain.

         KOTAK KETERANGAN MEMAKAI `.md-explain`, BUKAN `.md-threshold` —
         keduanya tampil identik, dan yang berbeda hanya namanya. Layar ini
         sengaja tidak menandai satu pun nilai di luar batas (Solid Waste
         Disposal tidak punya tabel master target), dan ketiadaan itu diasersi
         MENURUT NAMA KELAS pada HTML ter-render; memakai kelas bernama
         'threshold' untuk menjelaskan bahwa tidak ada ambang akan memerahkan
         asersi itu justru karena namanya. Konvensi ini lahir di screen-133
         (Storage Tank) dan dipakai ulang di screen-156, screen-158 dan
         screen-160.

         Ditaruh DI SINI, bukan di dalam komponen Livewire, karena dua hal:
         1. Livewire 3 memasang wire:id pada elemen ter-render PERTAMA. Bila
            partial ber-<style> ini berada di dalam atau di atas root komponen,
            atribut itu bisa menempel pada <style> dan SELURUH wire:model
            berhenti bekerja — termasuk ketiga pemilih layar ini. Component test
            tidak menangkapnya; browser test menangkapnya. Harganya sudah
            dibayar di screen-140.
         2. CSS milik halaman, bukan milik state komponen. Menjaganya di luar
            komponen membuat HTML yang diperiksa Livewire::test() berisi markup
            saja. --}}
    <x-slot:styles>
        @include('dashboard.partials.report-styles')
    </x-slot:styles>

    {{ $slot }}
</x-layouts.app>
