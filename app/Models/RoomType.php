<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'facilities'];

    protected function casts(): array
    {
        return ['facilities' => 'array'];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /** @return array<int, array{key:string, label:string, icon:string}> */
    public function facilityList(): array
    {
        return Room::describeFacilities($this->facilities ?? []);
    }
}
