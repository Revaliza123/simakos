<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'content', 'video_url', 'created_by', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Lampiran gambar & video, urut sesuai unggahan. */
    public function media(): HasMany
    {
        return $this->hasMany(AnnouncementMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * ID video YouTube dari tautan (youtube.com/watch?v=, youtu.be/, /shorts/, /embed/, /live/).
     */
    public static function youtubeId(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $pattern = '~(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~i';

        return preg_match($pattern, $url, $m) ? $m[1] : null;
    }

    public function youtubeEmbedUrl(): ?string
    {
        $id = self::youtubeId($this->video_url);

        return $id ? 'https://www.youtube-nocookie.com/embed/'.$id : null;
    }

    public function youtubeThumbnail(): ?string
    {
        $id = self::youtubeId($this->video_url);

        return $id ? 'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg' : null;
    }

    /** Pengumuman yang sudah terbit dan boleh tampil di portal penghuni. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lessThanOrEqualTo(now());
    }
}
