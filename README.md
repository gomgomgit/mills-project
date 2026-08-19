# Mill Smart Log

Sistem manajemen operasional pabrik CPO (mill) yang mendigitalisasi log sheet stasiun produksi.
Operator menginput data melalui aplikasi mobile offline-first; Supervisor, Mill Management, dan
Admin mengelola, memonitor, dan turut menginput data stasiun melalui web (dan mobile untuk
Supervisor), menggantikan pencatatan manual berbasis kertas.

## Struktur Proyek

Monorepo dengan dua aplikasi:

- **`backend/`** — Laravel (PHP). Melayani REST API (dikonsumsi mobile via Sanctum token) dan
  web app Livewire+Blade (Admin/Supervisor/Mill Management, session-based auth).
- **`mobile/`** — Vue 3 + Capacitor (Operator/Supervisor). Offline-first dengan SQLite lokal,
  konsumsi REST API `backend/` untuk login dan sync manual.

## Tech Stack

**Backend**
- Laravel (PHP) — REST API + Livewire web UI dalam satu codebase
- MySQL — database, via Eloquent ORM + Laravel Migrations
- Laravel Sanctum — auth (token untuk mobile, session untuk web)
- Pest / PHPUnit — testing

**Mobile**
- Vue 3 + Capacitor (Android & iOS)
- Pinia — state management
- SQLite via Capacitor Community SQLite plugin — local storage offline-first
- Vitest — testing

## Prerequisites

- PHP >= 8.2, Composer >= 2.x
- Node.js >= 20.x, npm >= 10.x
- MySQL >= 8.0

## Running Locally

### Backend

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

Or via the root `Makefile`:

```bash
make install
make db-setup
make dev
```

Belum ada driver MySQL di sebagian environment dev/sandbox — sebagai alternatif, backend juga
jalan penuh di atas SQLite tanpa perlu server database terpisah:

```bash
cd backend
touch database/database.sqlite
```

Lalu di `.env`, ganti baris `DB_*` menjadi:

```
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/ke/backend/database/database.sqlite
```

(`DB_HOST`/`DB_PORT`/`DB_USERNAME`/`DB_PASSWORD` diabaikan oleh driver sqlite, tidak perlu dihapus.)
`php artisan migrate` tetap dijalankan seperti biasa setelahnya.

### Mobile

```bash
cd mobile
cp .env.example .env
npm install
npm run dev
```

## Demo Accounts (Local Dev)

Tidak ada Laravel seeder resmi di project ini (belum dibangun) — 3 akun demo di bawah ini
dibuat manual langsung di database lokal untuk keperluan development/testing. Semua akun berada
di business unit yang sama ("Business Unit A") dan memakai password yang sama.

| Username       | Password    | Role         | Kegunaan                                    |
|----------------|-------------|--------------|----------------------------------------------|
| `operator01`   | `Passw0rd!` | operator     | Login mobile — input data stasiun            |
| `supervisor01` | `Passw0rd!` | supervisor   | Login mobile & web — input + review + Checked By |
| `admin`        | `Passw0rd!` | admin        | Login web — kelola user/role, master data    |

Jika database di-reset dan akun ini hilang, buat ulang lewat `php artisan tinker`:

```php
$bu = App\Models\BusinessUnit::first() ?? App\Models\BusinessUnit::factory()->create(['name' => 'Business Unit A']);

foreach ([
    ['username' => 'operator01', 'name' => 'Operator Demo', 'role' => App\Enums\UserRole::Operator],
    ['username' => 'supervisor01', 'name' => 'Supervisor Demo', 'role' => App\Enums\UserRole::Supervisor],
    ['username' => 'admin', 'name' => 'Admin Demo', 'role' => App\Enums\UserRole::Admin],
] as $data) {
    App\Models\User::create([
        'username' => $data['username'],
        'name' => $data['name'],
        'role' => $data['role'],
        'password_hash' => Illuminate\Support\Facades\Hash::make('Passw0rd!'),
        'business_unit_id' => $bu->id,
        'is_active' => true,
    ]);
}
```

## Environment Variables

Lihat `backend/.env.example` dan `mobile/.env.example` untuk daftar lengkap variabel yang
dibutuhkan (koneksi database, Sanctum, base URL API, dsb).

## Testing

```bash
make test                     # backend (Pest/PHPUnit)
cd mobile && npm test              # mobile unit/component (Vitest)
cd mobile && npm run test:e2e      # mobile browser/e2e (Playwright) — perlu backend (php artisan serve)
                                    # dan mobile dev server (npm run dev) sudah berjalan; baca
                                    # mobile/tests/e2e/helpers.ts untuk pola login/seed yang dipakai
```

## Deploying

Deployment on-premise / self-hosted (server fisik/VM lokal di lingkungan mill, dikelola tim IT
internal), sesuai `arch-spec.deployment`.

1. Build backend: `cd backend && composer install --no-dev --optimize-autoloader && php artisan config:cache && php artisan route:cache`
2. Build mobile web assets (jika diperlukan) atau bundle native app: `cd mobile && npm run build && npx cap sync`
3. Serve `backend/public` melalui web server (Nginx/Apache) yang dikonfigurasi tim IT internal,
   dengan `.env` produksi terisi (lihat `backend/.env.example`).
4. Distribusikan build native mobile (Android/iOS) via `mobile/android` / `mobile/ios` setelah
   `cap sync`.
