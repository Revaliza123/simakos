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

## Akun dan hak akses

Pengguna dapat mendaftar melalui halaman **Daftar** dan akun yang dibuat akan selalu memiliki role penghuni. Route pengelola memerlukan role `admin`; route portal penghuni memerlukan role `tenant`.

Untuk membuat akun admin pertama, isi `SIMAKOS_ADMIN_NAME`, `SIMAKOS_ADMIN_EMAIL`, dan `SIMAKOS_ADMIN_PASSWORD` pada `.env` dengan kredensial yang aman, lalu jalankan:

```bash
php artisan db:seed
```

Variabel password admin sengaja tidak memiliki nilai bawaan. Jangan gunakan password contoh atau membagikan file `.env`.

## Pemeriksaan

```bash
php artisan test
npm run build
```

Optimasi autoload Composer dinonaktifkan untuk mempercepat instalasi lokal. Saat deployment, gunakan `composer install --no-dev --optimize-autoloader`.
