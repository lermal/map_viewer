<?php

namespace App\Services;

use App\Models\UserRender;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserRenderImageStore
{
    private const TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /**
     * Decode the upload and store a re-encoded WebP copy on the private disk.
     * Only pixels survive: metadata and any data appended to the file are dropped.
     *
     * @return array{path: string, size: int}
     */
    public function store(UploadedFile $file): array
    {
        $info = @getimagesize($file->getRealPath());
        if ($info === false || ! in_array($info[2], self::TYPES, true)) {
            $this->fail('The file is not a valid JPEG, PNG or WebP image.');
        }

        [$width, $height] = $info;
        $maxSide = (int) config('user-renders.max_side');
        $maxPixels = (int) config('user-renders.max_pixels');
        if ($width < 1 || $height < 1 || $width > $maxSide || $height > $maxSide || $width * $height > $maxPixels) {
            $this->fail(sprintf(
                'The image must be at most %d×%d pixels and %d megapixels.',
                $maxSide, $maxSide, intdiv($maxPixels, 1_000_000),
            ));
        }

        // one image at a time: parallel uploads must not multiply the memory peak
        try {
            return Cache::lock('user-render-image', 180)->block(30, fn () => $this->encode($file, $info));
        } catch (LockTimeoutException) {
            $this->fail('The server is busy processing other uploads. Please try again in a minute.');
        }
    }

    /**
     * @return array{path: string, size: int}
     */
    private function encode(UploadedFile $file, array $info): array
    {
        [$width, $height] = $info;

        // decoding and encoding hold the bitmap several times; dimensions were checked above
        $memoryLimit = ini_get('memory_limit');
        $needed = 64 * 1024 * 1024 + $width * $height * 30;
        if ($memoryLimit !== '-1' && $this->bytes($memoryLimit) < $needed) {
            ini_set('memory_limit', (string) $needed);
        }
        set_time_limit(120);

        $tmp = tempnam(sys_get_temp_dir(), 'render');

        try {
            $image = match ($info[2]) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getRealPath()),
                IMAGETYPE_PNG => @imagecreatefrompng($file->getRealPath()),
                IMAGETYPE_WEBP => @imagecreatefromwebp($file->getRealPath()),
            };
            if ($image === false) {
                $this->fail('The image could not be read. Please save it again as PNG or JPEG and retry.');
            }

            if (! imageistruecolor($image)) {
                imagepalettetotruecolor($image);
            }
            imagealphablending($image, false);
            imagesavealpha($image, true);

            // renders are pixel art: keep PNG and WebP sources lossless, photos from JPEG stay lossy
            $quality = $info[2] === IMAGETYPE_JPEG ? 90 : IMG_WEBP_LOSSLESS;
            if (! imagewebp($image, $tmp, $quality) || filesize($tmp) === 0) {
                $this->fail('The image could not be processed. Please try another file.');
            }
            unset($image);

            $path = 'user-renders/'.Str::random(40).'.webp';
            $stream = fopen($tmp, 'rb');
            Storage::disk(UserRender::PRIVATE_DISK)->writeStream($path, $stream);
            fclose($stream);

            return ['path' => $path, 'size' => filesize($tmp)];
        } finally {
            @unlink($tmp);
            ini_set('memory_limit', $memoryLimit);
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['image' => $message]);
    }

    private function bytes(string $value): int
    {
        $number = (int) $value;

        return match (strtolower(substr(trim($value), -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
