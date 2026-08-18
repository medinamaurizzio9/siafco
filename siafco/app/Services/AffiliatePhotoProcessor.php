<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AffiliatePhotoProcessor
{
    public const OUTPUT_SIZE = 600;
    public const CREDENTIAL_WIDTH = 600;
    public const CREDENTIAL_HEIGHT = 760;

    public function process(UploadedFile $file, int $outputWidth = self::OUTPUT_SIZE, ?int $outputHeight = null): string
    {
        $outputHeight ??= $outputWidth;

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages([
                'photo' => 'El archivo seleccionado no es una imagen válida.',
            ]);
        }

        $image = $this->load($file->getRealPath(), $mime);
        if (! $image) {
            throw ValidationException::withMessages([
                'photo' => 'La fotografía no pudo procesarse.',
            ]);
        }

        if ($mime === 'image/jpeg') {
            $image = $this->orient($image, $file->getRealPath());
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $targetRatio = $outputWidth / $outputHeight;
        $sourceWidth = $width;
        $sourceHeight = (int) round($width / $targetRatio);

        if ($sourceHeight > $height) {
            $sourceHeight = $height;
            $sourceWidth = (int) round($height * $targetRatio);
        }

        $sourceX = (int) floor(($width - $sourceWidth) / 2);
        $sourceY = (int) floor(($height - $sourceHeight) / 2);

        $output = imagecreatetruecolor($outputWidth, $outputHeight);
        $white = imagecolorallocate($output, 255, 255, 255);
        imagefilledrectangle($output, 0, 0, $outputWidth, $outputHeight, $white);
        imagecopyresampled(
            $output,
            $image,
            0,
            0,
            $sourceX,
            $sourceY,
            $outputWidth,
            $outputHeight,
            $sourceWidth,
            $sourceHeight
        );

        ob_start();
        imagejpeg($output, null, 86);
        $jpeg = ob_get_clean();
        imagedestroy($image);
        imagedestroy($output);

        if (! is_string($jpeg)) {
            throw ValidationException::withMessages(['photo' => 'La fotografía no pudo procesarse.']);
        }

        $path = 'affiliates/photos/'.Str::uuid().'.jpg';
        Storage::disk('public')->put($path, $jpeg);

        return $path;
    }

    private function load(string $path, string $mime): \GdImage|false
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    private function orient(\GdImage $image, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = @exif_read_data($path)['Orientation'] ?? 1;
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => false,
        };

        if ($rotated instanceof \GdImage) {
            imagedestroy($image);
            return $rotated;
        }

        return $image;
    }
}
