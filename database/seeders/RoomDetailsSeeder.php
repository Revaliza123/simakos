<?php

namespace Database\Seeders;

use App\Models\Rental;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Melengkapi data demo Room Management: fasilitas per tipe, lantai/luas/deskripsi kamar,
 * masa sewa (tanggal berakhir), riwayat penyewa lama, dan foto kamar.
 *
 * Aman dijalankan berulang: hanya mengisi kolom yang masih kosong.
 * Jalankan: php artisan db:seed --class=RoomDetailsSeeder
 *
 * Foto: letakkan file .jpg/.png/.webp di database/seeders/room-photos/<slug-tipe>/,
 * mis. room-photos/standard-single/1.jpg. Kamar tanpa foto akan diberi foto dari folder tipenya.
 */
class RoomDetailsSeeder extends Seeder
{
    private const TYPE_DETAILS = [
        'Standard Single' => [
            'facilities' => ['kasur', 'lemari', 'meja', 'kipas', 'wifi', 'km_luar', 'jendela'],
            'size' => 9,
            'description' => 'Kamar single ekonomis dengan kasur, lemari, dan meja belajar. Kamar mandi bersama di lorong.',
        ],
        'Superior Single' => [
            'facilities' => ['kasur', 'lemari', 'meja', 'ac', 'wifi', 'km_dalam', 'jendela'],
            'size' => 12,
            'description' => 'Kamar single ber-AC dengan kamar mandi dalam, cocok untuk mahasiswa dan pekerja.',
        ],
        'Deluxe Studio' => [
            'facilities' => ['kasur', 'lemari', 'meja', 'ac', 'wifi', 'km_dalam', 'water_heater', 'tv', 'kulkas', 'balkon'],
            'size' => 18,
            'description' => 'Studio luas dengan water heater, TV, kulkas, dan balkon pribadi.',
        ],
    ];

    /** Fasilitas khusus unit tertentu (di luar fasilitas bawaan tipe). */
    private const ROOM_EXTRAS = [
        '102' => ['description' => 'Menghadap taman depan, pencahayaan pagi.', 'facilities' => ['balkon']],
        '201' => ['description' => 'Unit sudut dengan dua jendela besar.', 'facilities' => ['parkir']],
        '301' => ['description' => 'Lantai teratas, paling tenang dan dekat area jemur.', 'facilities' => ['laundry']],
    ];

    /** Lama kontrak demo (bulan) per penghuni; null = bulanan tanpa tanggal akhir. */
    private const CONTRACT_MONTHS = [
        'alya@simakos.test' => 12,
        'raka@simakos.test' => 6,
        'nadia@simakos.test' => 12,
        'dimas@simakos.test' => null,
        'salsa@simakos.test' => 12,
    ];

    /** Penyewa lama (sudah check-out) agar riwayat penyewaan terisi. */
    private const PAST_RENTALS = [
        ['101', 'Bima Saputra', 'bima@simakos.test', 20, 8],
        ['201', 'Citra Lestari', 'citra@simakos.test', 22, 12],
        ['201', 'Fajar Nugroho', 'fajar@simakos.test', 9, 3],
        ['301', 'Gita Permata', 'gita@simakos.test', 14, 6],
    ];

    public function run(): void
    {
        foreach (self::TYPE_DETAILS as $name => $details) {
            $type = RoomType::where('name', $name)->first();
            if ($type && empty($type->facilities)) {
                $type->update([
                    'facilities' => $details['facilities'],
                    'description' => $type->description ?: $details['description'],
                ]);
            }
        }

        foreach (Room::with('roomType')->get() as $room) {
            $typeDetails = self::TYPE_DETAILS[$room->roomType?->name] ?? null;
            $extras = self::ROOM_EXTRAS[$room->room_number] ?? [];
            $firstDigit = (int) substr((string) $room->room_number, 0, 1);

            $room->update(array_filter([
                'floor' => $room->floor ?? ($firstDigit ?: null),
                'size_m2' => $room->size_m2 ?? ($typeDetails['size'] ?? null),
                'description' => $room->description ?: ($extras['description'] ?? null),
                'facilities' => $room->facilities ?: ($extras['facilities'] ?? null),
            ], fn ($value) => $value !== null));
        }

        foreach (self::CONTRACT_MONTHS as $email => $months) {
            $rental = Rental::where('status', 'active')
                ->whereNull('end_date')
                ->whereHas('user', fn ($q) => $q->where('email', $email))
                ->first();

            if ($rental && $months) {
                $rental->update(['end_date' => $rental->start_date->copy()->addMonthsNoOverflow($months)->subDay()]);
            }
        }

        foreach (self::PAST_RENTALS as [$number, $name, $email, $startedMonthsAgo, $months]) {
            $room = Room::where('room_number', $number)->first();
            if (! $room) {
                continue;
            }

            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'password', 'role' => 'tenant']);
            $start = now()->startOfMonth()->subMonths($startedMonthsAgo);
            Rental::firstOrCreate(
                ['user_id' => $user->id, 'room_id' => $room->id],
                ['start_date' => $start, 'end_date' => $start->copy()->addMonthsNoOverflow($months)->subDay(), 'status' => 'completed'],
            );
        }

        $this->seedPhotos();
    }

    private function seedPhotos(): void
    {
        $source = database_path('seeders/room-photos');
        if (! File::isDirectory($source)) {
            $this->command?->info('Folder database/seeders/room-photos belum ada, foto kamar dilewati.');

            return;
        }

        $disk = Storage::disk('public');

        foreach (Room::with(['roomType', 'photos'])->get() as $room) {
            if ($room->photos->isNotEmpty() || ! $room->roomType) {
                continue;
            }

            $folder = $source.'/'.Str::slug($room->roomType->name);
            if (! File::isDirectory($folder)) {
                continue;
            }

            $files = collect(File::files($folder))
                ->filter(fn ($file) => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true))
                ->sortBy(fn ($file) => $file->getFilename())
                ->values();

            if ($files->isEmpty()) {
                continue;
            }

            // Putar urutan agar kamar bertipe sama tidak memakai foto sampul yang sama.
            $offset = (int) preg_replace('/\D/', '', (string) $room->room_number) % $files->count();
            $files = $files->slice($offset)->concat($files->take($offset))->take((int) config('simakos.room_photos.max_per_room', 8));

            foreach ($files->values() as $index => $file) {
                $path = 'room-photos/'.Str::slug($room->room_number).'-'.($index + 1).'.'.strtolower($file->getExtension());
                $disk->put($path, File::get($file->getPathname()));
                $room->photos()->create(['path' => $path, 'sort_order' => $index]);
            }
        }
    }
}
