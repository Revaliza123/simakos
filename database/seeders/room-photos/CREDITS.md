# Foto Kamar Demo

Foto contoh untuk `RoomDetailsSeeder`, diunduh dari Unsplash dengan
[Lisensi Unsplash](https://unsplash.com/license) (gratis dipakai, termasuk untuk keperluan komersial; atribusi tidak wajib tetapi dihargai).
Ukuran sudah diperkecil ke lebar 1280 px.

| File | Fotografer | Sumber |
|---|---|---|
| standard-single/1.jpg | SR LEE | https://unsplash.com/photos/5UuMPgXHekA |
| standard-single/2.jpg | Iglucraft | https://unsplash.com/photos/Xl5yRCCh_Iw |
| standard-single/3.jpg | Christopher Jolly | https://unsplash.com/photos/GqbU78bdJFM |
| superior-single/1.jpg | Irena Oze | https://unsplash.com/photos/-bKPWxIX3nI |
| superior-single/2.jpg | Irena Oze | https://unsplash.com/photos/eeoQBGB-pYg |
| superior-single/3.jpg | Aleksandra Dementeva | https://unsplash.com/photos/GzR2KS4ABYA |
| deluxe-studio/1.jpg | gerson runer | https://unsplash.com/photos/BrzGBZlLj8o |
| deluxe-studio/2.jpg | Spacejoy | https://unsplash.com/photos/nEtpvJjnPVo |
| deluxe-studio/3.jpg | Iglucraft | https://unsplash.com/photos/r3dQ0K3nDzg |

Tambahkan foto lain dengan menaruh file .jpg/.png/.webp di folder `<slug-tipe-kamar>/`,
lalu jalankan `php artisan db:seed --class=RoomDetailsSeeder` (hanya mengisi kamar yang belum punya foto).
