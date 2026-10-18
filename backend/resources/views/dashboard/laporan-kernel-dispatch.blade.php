<x-layouts.app title="Laporan Periode Kernel Dispatch">
    {{-- Kosakata CSS `md-*` bersama, dimuat lewat slot `styles` milik layout
         (konvensi repo — satu berkas layout per laporan, lihat
         dashboard/laporan-solid-waste-disposal.blade.php dan
         dashboard/laporan-process-water.blade.php yang identik kecuali
         judulnya).

         LAYAR INI TIDAK MENAMBAH SATU KELAS BARU PUN ke partial bersama itu.
         Seluruh kelas yang dipakai markupnya sudah terdefinisi di sana:
         md-hero*, md-chip*, md-filters* (lewat <x-report-filter-bar>), md-body,
         md-row / --2, md-card*, md-kpis / --2 / --3 / --4, md-kpi*, md-budget*,
         md-bar / --lg, md-explain, md-empty*, md-notes*, md-pill, md-details,
         md-recap, md-table, md-scrollhint, is-muted. Kelas yang dipakai markup
         WAJIB punya definisi, dan itu diperiksa pada hasil COMPILE, bukan pada
         sumbernya.

         LAYAR INI HIBRIDA — DUA PRESEDEN, DAN KEDUANYA SUDAH ADA KOSAKATANYA.
         Separuh transaksinya memakai kosakata yang sama dengan screen-162
         (md-kpis--4 untuk kartu angka pokok, md-recap/md-table untuk rekap,
         md-details untuk rekap yang dapat dilipat); separuh mutunya memakai
         kosakata yang sama dengan screen-160 (md-kpi dengan md-kpi__value /
         __meta / __foot, tempat min dan max duduk di __meta sementara
         penyebutnya duduk di __foot). Tidak ada kelas baru yang perlu dibuat
         untuk menggabungkan keduanya: yang baru di layar ini adalah SUSUNANNYA,
         bukan kosakatanya. Menambah kelas ke partial bersama berarti menyentuh
         ketujuh belas laporan lain yang memuatnya — preseden screen-143, yang
         menolak `md-group*`/`md-hc*` milik mock-nya dengan alasan yang sama.

         KOTAK KETERANGAN MEMAKAI `.md-explain`, BUKAN `.md-threshold` —
         keduanya tampil identik, dan yang berbeda hanya namanya. Layar ini
         sengaja tidak menandai satu pun nilai mutu di luar batas (Kernel
         Dispatch tidak punya tabel master ambang mutu), dan ketiadaan itu
         diasersi MENURUT NAMA KELAS pada HTML ter-render; memakai kelas bernama
         'threshold' untuk menjelaskan bahwa tidak ada ambang akan memerahkan
         asersi itu justru karena namanya. Konvensi ini lahir di screen-133
         (Storage Tank) dan dipakai ulang di screen-156, screen-158, screen-160
         dan screen-162.

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
