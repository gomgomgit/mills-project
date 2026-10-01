# Derived Assumptions — project.2-business-spec.usecases.usecase-146--laporan-weighbridge-web

## v1 — 2026-10-01

Autonomy `autopilot`: usecase ini diturunkan dari spec layar screen-143 lalu diterima tanpa
konfirmasi per-usecase (Step 7d jalur autopilot). Seluruh isinya rumusan agent.

- `usecase-146--laporan-weighbridge-web` = alur lengkap membaca laporan periode Weighbridge (10 langkah main_flow, 20 alternative_flow, 17 business rule, 35 bdd_scenario) ← diturunkan dari `available_actions`, `business_rules`, `information_displayed`, dan `edge_cases` screen-143; tidak dikonfirmasi satu per satu.

- `alternative_flows` = 20 entri ← diturunkan satu per satu dari ke-16 `edge_cases` screen-143 ditambah empat jalur gagal yang tersirat dari business rule (akun tanpa mill, mencoba mill/line lain, Operator ditolak, periode tertutup). NOL alternative_flow kosong, jadi tidak ada celah "tidak ada yang bisa salah di sini" yang perlu ditanyakan.

- `bdd_scenarios` = 35 skenario ← diturunkan oleh `bdd-spec-writer-agent` dari main_flow, alternative_flows, dan business_rules di atas. Pembagiannya: 2 happy-path (Supervisor/Mill Management berbagi satu karena hasilnya identik; Admin terpisah karena ia memilih mill), 20 dari alternative_flows, 12 dari business rule yang dapat diverifikasi sendiri, 1 untuk ekspor CSV.

- Lima business rule SENGAJA TIDAK diberi skenario tersendiri karena sudah tercakup penuh oleh alternative_flow-nya: "Operator tidak punya akses", "Status periode tidak membatasi", "Production Line WAJIB dipilih", "Trip arus keluar tanpa tujuan dikelompokkan tersendiri", dan "Supervisor/Mill Management terkunci; Admin memilih". Keputusan agent, bukan kelalaian — dicatat supaya penghilangan itu tidak dibaca sebagai celah cakupan.

- Enam aturan yang paling mudah diimplementasikan salah mendapat skenario KHUSUS, terpisah dari alternative_flow terkaitnya: dua arus tidak pernah dijumlahkan, penyebut rata-rata hanya trip berbobot, penimbangan belum selesai dihitung per arus, durasi butuh KEDUA waktu, line diambil dari trip itu sendiri, dan rentang periode inklusif. Pemilihan keenam itu sebagai titik rawan adalah penilaian agent.

- `preconditions` memuat "Mill sudah punya minimal satu Production Line" ← turunan agent; konsekuensi dari Production Line yang wajib dipilih, tidak dinyatakan user.
