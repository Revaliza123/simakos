# Simakos

Proyek Simakos menggunakan Laravel 12.69.3, PHP 8.2+, dan Vite.

## Instalasi

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Buat file `database/database.sqlite` untuk database SQLite bawaan, lalu jalankan:

```bash
php artisan migrate
npm ci
npm run build
php artisan serve
```

Buka http://localhost:8000. Untuk pengembangan frontend, jalankan `npm run dev` di terminal terpisah.

## Pemeriksaan

```bash
php artisan test
npm run build
```

Optimasi autoload Composer dinonaktifkan untuk mempercepat instalasi lokal. Saat deployment, gunakan `composer install --no-dev --optimize-autoloader`.
