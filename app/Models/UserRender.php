<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserRender extends Model
{
    /**
     * Approved images are served as static files; everything else stays on the private disk
     * and is only reachable through the render's secret link.
     */
    public const PUBLIC_DISK = 'public';

    public const PRIVATE_DISK = 'local';

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'image',
        'size',
        'description',
        'status',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'size' => 'integer',
            'rejected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // renders added from the admin panel get a secret link as well
        static::creating(function (UserRender $render) {
            while (blank($render->slug) || self::where('slug', $render->slug)->exists()) {
                $render->slug = Str::random(32);
            }
        });

        static::saving(function (UserRender $render) {
            if ($render->isDirty('status')) {
                $render->rejected_at = $render->status === 'rejected' ? now() : null;
            }
        });

        static::saved(function (UserRender $render) {
            $old = $render->getOriginal('image');
            if ($render->wasChanged('image') && $old && $old !== $render->image) {
                self::deleteFile($old);
            }
            $render->syncImageLocation();
        });

        static::deleted(function (UserRender $render) {
            if ($render->image) {
                self::deleteFile($render->image);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Total size of the files waiting for moderation, in bytes.
     */
    public static function pendingBytes(): int
    {
        return (int) self::where('status', 'pending')->sum('size');
    }

    public function expectedDisk(): string
    {
        return $this->status === 'approved' ? self::PUBLIC_DISK : self::PRIVATE_DISK;
    }

    /**
     * The disk that currently holds the image, or null when there is no file.
     */
    public function imageDisk(): ?string
    {
        if (! $this->image) {
            return null;
        }

        foreach (array_unique([$this->expectedDisk(), self::PUBLIC_DISK, self::PRIVATE_DISK]) as $disk) {
            if (Storage::disk($disk)->exists($this->image)) {
                return $disk;
            }
        }

        return null;
    }

    /**
     * Image address for visitors: a static file once approved, the secret link while pending,
     * nothing for rejected renders.
     */
    public function imageUrl(): ?string
    {
        if (! $this->image || $this->status === 'rejected') {
            return null;
        }

        if ($this->status === 'approved' && Storage::disk(self::PUBLIC_DISK)->exists($this->image)) {
            return Storage::disk(self::PUBLIC_DISK)->url($this->image);
        }

        return route('user-renders.image', $this->slug);
    }

    /**
     * Move the image to the disk that matches the status and record its size if it is missing.
     */
    public function syncImageLocation(): void
    {
        if (! $this->image) {
            return;
        }

        $target = Storage::disk($this->expectedDisk());
        $other = Storage::disk($this->expectedDisk() === self::PUBLIC_DISK ? self::PRIVATE_DISK : self::PUBLIC_DISK);

        if (! $target->exists($this->image) && $other->exists($this->image)) {
            $stream = $other->readStream($this->image);
            $target->writeStream($this->image, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            $other->delete($this->image);
        }

        if ($this->size === null && $target->exists($this->image)) {
            $this->size = $target->size($this->image);
            $this->saveQuietly();
        }
    }

    /**
     * Remove the image of a rejected render; the page keeps showing its status.
     */
    public function forgetImage(): void
    {
        if ($this->image) {
            self::deleteFile($this->image);
        }
        $this->image = null;
        $this->size = null;
        $this->saveQuietly();
    }

    private static function deleteFile(string $path): void
    {
        Storage::disk(self::PUBLIC_DISK)->delete($path);
        Storage::disk(self::PRIVATE_DISK)->delete($path);
    }
}
