# Derived Assumptions — module-dashboard.screen-134--dashboard-reporting-mobile.3-tech-spec

## v1 — 2026-09-23

- `route` = `/dashboard-reporting`, name `dashboard-reporting`, `meta.public = false` ← mengikuti pola rute mobile yang sudah ada di `router/index.ts`. User tidak menyebut rute.
- `api_contracts[0].endpoints` = `[]` dan seluruh `test_scenarios[].api_test` = `[]` ← layar ini nol pemanggilan data. Preseden: `screen-006--station-list`, yang juga punya satu entri kontrak dengan `endpoints` kosong dan `business_logic` berisi langkah sisi-klien. **Tidak ada tambahan ke api-index.**
- `MENU_OPTIONS` sebagai satu konstanta dengan `routeName: null` sebagai penanda "belum dibangun" ← spec bisnis hanya menyatakan kartunya nonaktif, tidak menyebut bagaimana diwakili. Dipilih agar menghidupkan sebuah kartu kelak berarti mengisi satu nilai, bukan mengubah template.
- **Pesan info disimpan pada satu variabel reaktif tunggal, bukan daftar** ← inilah yang membuat aturan "pesan tidak menumpuk" benar secara konstruksi, bukan karena ada kode pembersih. Diuji langsung oleh dua unit test dan satu skenario browser.
- `aria-disabled="true"` alih-alih atribut `disabled` native ← disalin dari `StationGrid.vue`, yang menuliskan alasannya: `disabled` menelan ketukan sehingga tidak ada pesan yang bisa muncul. Berbeda dari tile nonaktif di web screen-140 yang memang inert, dan perbedaan itu disengaja.
- **Satu unit test khusus mengasersi nol pemanggilan `apiClient` dan `localDb`** ← bukan diminta spec. Sifat offline-friendly layar ini akan hilang diam-diam bila kelak seseorang menambahkan pemuatan data; test itu yang menahannya.
- `screen_dependencies` menyertakan `screen-005--home` ← karena run ini **mengubah** `HomeView.vue` (kartu `dashboard-reporting` yang tadinya `showComingSoon`). Ketergantungannya nyata, bukan sekadar navigasi.
- Skenario "Belum masuk" diuji pada lapis **router**, bukan `mount()` ← satu-satunya entri yang menyimpang dari pola, karena komponennya memang tidak pernah dipasang saat penjagaan sesi menolak.
- Browser test peran dibatasi **dua** wakil (Operator + Supervisor) padahal component test menguji keempatnya ← menjaga waktu jalan e2e tetap wajar; keempat peran tetap tertutup penuh di lapis component.
