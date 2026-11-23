<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementMedia extends Model
{
    protected $table = 'announcement_media';

    protected $fillable = ['announcement_id', 'type', 'path', 'original_name', 'sort_order'];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function url(): string
    {
        return asset('storage/'.$this->path);
    }

    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    /** MIME untuk tag <source> video. */
    public function mimeType(): string
    {
        return match (strtolower(pathinfo($this->path, PATHINFO_EXTENSION))) {
            'webm' => 'video/webm',
            'ogv', 'ogg' => 'video/ogg',
            default => 'video/mp4',
        };
    }
}
