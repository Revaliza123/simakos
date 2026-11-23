<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Room extends Model
{
    use HasFactory;

    public const STATUS_LABELS = [
        'available' => 'Tersedia',
        'occupied' => 'Terisi',
        'maintenance' => 'Perbaikan',
    ];

    protected $fillable = ['room_number', 'room_type_id', 'floor', 'size_m2', 'price', 'status', 'description', 'facilities'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'size_m2' => 'decimal:1',
            'floor' => 'integer',
            'facilities' => 'array',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /** Kontrak sewa yang sedang berjalan (paling banyak satu per kamar). */
    public function activeRental(): HasOne
    {
        return $this->hasOne(Rental::class)->where('status', 'active')->latest('start_date')->latest('id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(RoomPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function coverPhoto(): ?RoomPhoto
    {
        return $this->relationLoaded('photos') ? $this->photos->first() : $this->photos()->first();
    }

    /** Fasilitas bawaan tipe kamar + fasilitas tambahan unit ini, tanpa duplikat. */
    public function facilityKeys(): array
    {
        return array_values(array_unique([
            ...($this->roomType?->facilities ?? []),
            ...($this->facilities ?? []),
        ]));
    }

    /** @return array<int, array{key:string, label:string, icon:string}> */
    public function facilityList(): array
    {
        return self::describeFacilities($this->facilityKeys());
    }

    public function floorLabel(): string
    {
        return $this->floor ? 'Lantai '.$this->floor : 'Lantai belum diisi';
    }

    public function sizeLabel(): ?string
    {
        return $this->size_m2 ? rtrim(rtrim(number_format((float) $this->size_m2, 1, ',', '.'), '0'), ',').' m²' : null;
    }

    /** Ubah daftar kunci fasilitas menjadi label + ikon sesuai katalog config. */
    public static function describeFacilities(array $keys): array
    {
        $catalog = config('simakos.facilities', []);

        return collect($keys)
            ->filter(fn ($key) => isset($catalog[$key]))
            ->map(fn ($key) => ['key' => $key, 'label' => $catalog[$key][0], 'icon' => $catalog[$key][1]])
            ->values()
            ->all();
    }
}
