<?php

namespace App\Support;

use App\Models\Job;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pictures on document line items (mockups for tenders). Each job keeps its
 * own under {job_id}/item-images/, and a line item stores only the file name,
 * so a document can never point at a file outside its job's folder.
 */
class ItemImages
{
    public const NAME = '/^[A-Za-z0-9]{32}\.jpg$/';

    /** Largest side kept after upload, in pixels: sharp in print, small on disk. */
    private const MAX_SIDE = 1600;

    /** Printed size limits in the PDF item column, in points. */
    private const MAX_W = 200.0;

    private const MAX_H = 150.0;

    public static function valid(?string $name): bool
    {
        return is_string($name) && preg_match(self::NAME, $name) === 1;
    }

    public static function path(Job $job, string $name): string
    {
        return "{$job->job_id}/item-images/{$name}";
    }

    /** Resizes and re-encodes as JPEG (drops anything hidden in the original file) and returns the new name. */
    public static function store(UploadedFile $file, Job $job): string
    {
        $name = Str::random(32).'.jpg';
        Storage::disk('public')->put(self::path($job, $name), self::jpeg($file->getRealPath(), self::MAX_SIDE));

        return $name;
    }

    /**
     * JPEG bytes of an image, no larger than $maxSide on its longest side, on
     * white (transparent PNG mockups would otherwise print black).
     */
    public static function jpeg(string $file, int $maxSide, int $quality = 85): string
    {
        $src = @imagecreatefromstring((string) file_get_contents($file));
        abort_unless($src, 422, 'This image could not be read. Use a JPG, PNG or WebP file.');

        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, $maxSide / max($w, $h));
        [$nw, $nh] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];

        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($out, null, $quality);

        return (string) ob_get_clean();
    }

    /**
     * Adds the file path and printed size for items whose picture exists in
     * this job's folder; anything else simply prints without a picture.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function resolve(array $items, Job $job): array
    {
        return array_map(function ($item) use ($job) {
            unset($item['image_file'], $item['image_w'], $item['image_h']);
            if (! self::valid($item['image'] ?? null) || ! $job->job_id) {
                return $item;
            }
            $file = Storage::disk('public')->path(self::path($job, $item['image']));
            $size = is_file($file) ? @getimagesize($file) : false;
            if (! $size) {
                return $item;
            }
            $scale = min(self::MAX_W / $size[0], self::MAX_H / $size[1]);

            return $item + ['image_file' => $file, 'image_w' => round($size[0] * $scale, 1), 'image_h' => round($size[1] * $scale, 1)];
        }, $items);
    }

    /** Duplicate Job: the copy gets its own copies of the pictures its items use. */
    public static function copy(Job $from, Job $to): void
    {
        $disk = Storage::disk('public');
        foreach ($to->line_items ?? [] as $item) {
            $name = $item['image'] ?? null;
            if (self::valid($name) && $disk->exists(self::path($from, $name)) && ! $disk->exists(self::path($to, $name))) {
                $disk->copy(self::path($from, $name), self::path($to, $name));
            }
        }
    }
}
