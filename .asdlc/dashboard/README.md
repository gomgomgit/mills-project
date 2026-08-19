# Agentic SDLC — Dashboard

Dashboard lokal untuk memantau progress artifact: dep-graph status, versi, stale warnings, dan isi artifact per phase.

## Cara Menjalankan

Jalankan dari **root folder project** (tempat `CLAUDE.md` berada):

```bash
node .asdlc/dashboard/server.js
```

Lalu buka browser ke:

```
http://localhost:7701
```

### Opsi

| Flag                  | Default      | Keterangan                                      |
|-----------------------|--------------|-------------------------------------------------|
| `--port`              | `7701`       | Port server                                     |
| `--generated-folder`  | `generated`  | Folder generated relatif dari `.asdlc/`         |

### Contoh

```bash
# Jalankan dengan data project aktif (default)
node .asdlc/dashboard/server.js

# Jalankan di port lain (jika 7701 sudah terpakai)
node .asdlc/dashboard/server.js --port 7702

# Jalankan dengan data dari folder example
node .asdlc/dashboard/server.js --generated-folder generated_example/SimpleTodo_02

# Kombinasi
node .asdlc/dashboard/server.js --port 7702 --generated-folder generated_example/SimpleTodo_02

# Menjalankan dari .asdlc
node dashboard\server.js --generated-folder generated_example\SimpleTodo
```

## Halaman

| Halaman              | Keterangan                                                                      |
|----------------------|---------------------------------------------------------------------------------|
| Dashboard            | Overview metrics + phase progress bar + stale warnings                          |
| 1 · Foundation       | PRD, Architecture Spec, UIUX Spec — isi artifact + field-level dep-graph        |
| 2 · Business Spec    | Actor Index, Use Case Index — tabel + detail panel                              |
| 3 · Tech Spec        | Entity Catalog, Shared Decisions, API Index                                     |
| 4 · Implementation   | Scaffold, Entity Models, Shared Modules, Screen Implementation index + detail   |
| Module & Screens     | Tabel semua screen dengan status per fase (2-Business / 3-Tech / 4-Impl)        |
| Dep-graph            | Visualisasi SVG dependency antar artifact · klik node untuk detail              |
| All Artifacts        | Tabel semua artifact dengan status, versi, dan timestamp                        |
| Stale                | Daftar artifact yang perlu di-regenerate                                        |

## Catatan

- Zero dependencies — hanya Node.js built-in
- Auto-refresh setiap 1 detik (file watcher), tanpa perlu restart server
- Port default `7701`
- Data dibaca dari `.asdlc/generated/` (atau folder `--generated-folder` yang dipilih)
- Dep-graph module dibaca dari `.asdlc/generated/internal/dep-graph/`
