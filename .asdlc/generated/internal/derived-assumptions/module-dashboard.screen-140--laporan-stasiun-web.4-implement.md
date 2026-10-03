# Derived Assumptions — module-dashboard.screen-140--laporan-stasiun-web.4-implement

## v1 — 2026-09-23

Phase 4 mengimplementasikan tech spec apa adanya, jadi yang dicatat di sini hanya pilihan
yang tidak ditentukan spec.

- **Penempatan `@include('dashboard.partials.report-styles')`** = di view halaman lewat `<x-slot:styles>`, bukan di dalam komponen Livewire ← spec hanya mewajibkan partial itu di-include, tidak menyebut di mana. Penempatan ternyata berkonsekuensi nyata: partial ini memancarkan `<style>`, dan Livewire 3 memasang `wire:id` pada elemen ter-render **pertama**. Menaruhnya di atas root div membuat atribut itu menempel pada `<style>`, sehingga seluruh `wire:model` berhenti bekerja dan pemilih Mill Admin mati total di peramban. Ditangkap browser test; **tidak** tertangkap component test, karena `Livewire::test()` memeriksa render sisi server dan tidak melewati semantik root-element peramban.
- **`<x-slot:styles>` dipilih di atas 'di dalam root div'** ← keduanya memperbaiki masalah root-element, tetapi menaruh CSS di dalam komponen membuat selector ikut terbaca oleh asersi `Livewire::test()`. Itu langsung memerahkan `LaporanSterilizerTest` yang mengasersi `assertDontSeeHtml('disabled')` — kata itu muncul di selector `.station-tile.disabled` milik screen-140. Memindahkan CSS ke luar komponen menyelesaikannya tanpa melemahkan satu pun asersi. Slot `styles` juga sudah jadi konvensi repo (`data/production-process-activity.blade.php`, `settings/password.blade.php`).
- **401 `UNAUTHENTICATED` untuk kasus tanpa sesi** (screen-129 memakai 403) ← ditentukan tech spec layar ini, diimplementasikan lewat `AuthenticationException` yang dipetakan `ApiExceptionHandler`. Deviasi sadar antar dua layar sejenis, bukan kelalaian.
- **Ikon fallback untuk jenis stasiun tak dikenal** ← spec mewajibkan daftar stasiun bersumber dari master yang bisa bertambah kapan saja, tetapi tidak menyebut apa yang terjadi bila muncul kode tanpa ikon. Dipilih ikon umum agar menambah jenis stasiun di master tidak pernah merusak halaman.
- **`keepSelectionValid()`** membuang `businessUnitId` basi di komponen ← spec hanya mendefinisikan perilaku service. Tanpa ini, pilihan mill yang mill-nya sudah dihapus akan membuat service menjawab 404 untuk pilihan yang tidak pernah dibuat pengguna.
- **Kondisi aktif entri sidebar** = `routeIs('reports.stations','reports.sterilizer')` ← agar menu tetap tersorot ketika pengguna sudah masuk ke layar laporan turunannya. Tidak diminta spec.

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar
