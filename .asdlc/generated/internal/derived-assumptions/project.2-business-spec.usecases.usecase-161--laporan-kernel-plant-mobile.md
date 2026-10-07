# Derived Assumptions — project.2-business-spec.usecases.usecase-161--laporan-kernel-plant-mobile

## v1 — 2026-10-07

Autonomy `autopilot`: usecase ini diturunkan dari spec layar screen-155 lalu diterima tanpa
konfirmasi per-usecase. Seluruh isinya rumusan agent.

- 13 langkah main_flow, 23 alternative_flow, 23 aturan bisnis, dan 41 bdd_scenario ← diturunkan dari `available_actions`, `business_rules`, `information_displayed`, dan `edge_cases` screen-155.

- Ke-41 bdd_scenario ditulis `bdd-spec-writer-agent`. Komposisinya: 1 happy-path (kedua aktor digabung), 23 dari alternative_flow, 17 dari aturan bisnis yang dapat diverifikasi sendiri.

- Agent BDD TIDAK DAPAT MEMBACA artifact — `ToolSearch` tidak tersedia di runtime-nya sehingga MCP artifact tools tak dapat disurface. Ia menyatakannya sendiri. Bedanya dengan kejadian serupa pada usecase-160: kali ini `existing_bdd_scenarios = []` yang saya berikan MEMANG akurat, karena artifact usecase-nya belum ada saat agent berjalan — jadi tidak ada risiko duplikat, dan payload yang saya kirim memang lengkap (3 precondition, 13 langkah, 23 alt_flow, 24 aturan, 3 postcondition). Hasilnya diekstrak dari transkrip JSONL lalu ditulis lewat MCP; urutan diverifikasi identik.

- Happy-path kedua aktor DIGABUNG menjadi satu ← keputusan agent BDD yang saya setujui: Station Operator dan Supervisor menghasilkan outcome identik pada layar ini (keduanya terikat satu mill, keduanya tanpa pemilih mill, isi laporan sama). Alasan penggabungan ditulis DI DALAM `then` supaya tidak hilang saat skenario dibaca lepas dari konteksnya.

- TUJUH aturan bisnis SENGAJA tanpa skenario tersendiri karena sudah tercakup penuh oleh satu alternative_flow: nilai-tak-tersedia/persen-tanda-pisah, days_counted-sebelum-period_running, angka-pasangan-tidak-dirata-ratakan, downtime-pada-bloknya-sendiri, nol-dibedakan-dari-tak-tercatat, temuan-harfiah, dan Production-Line-wajib. Keputusan agent, dicatat supaya penghilangan itu tidak dibaca sebagai celah cakupan.

- DUA skenario yang tampak mirip SENGAJA TIDAK digabungkan, dan ini keputusan yang saya periksa lalu setujui: "Production Line belum berlaku" menguji KEADAANNYA, sementara "penjagaan ada di fungsi pemuatan, bukan hanya template" menguji JALUR coba-lagi dan ganti-periode. Kelas kebocoran isolasi Production Line sudah terbukti tiga kali di proyek ini, jadi memisahkan keduanya benar.

- Skenario "Periode belum mulai" membawa URUTAN PEMERIKSAAN di dalam `then` (days_counted nol sebelum period_running) ← keputusan agent: itu sebab bug-nya, bukan sekadar hasil yang terlihat, dan menghapusnya akan membuat skenario lolos terhadap implementasi yang salah urutan.
