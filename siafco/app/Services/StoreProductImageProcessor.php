<?php

namespace App\Services;

use App\Models\StoreProduct;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreProductImageProcessor
{
    private const MAX_DIMENSION = 5000;
    private const OUTPUT_WIDTH = 1200;

    public function process(StoreProduct $product, UploadedFile $file): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['image' => 'La imagen no tiene un formato válido.']);
        }

        $size = @getimagesize($file->getRealPath());
        if (! $size || $size[0] < 1 || $size[1] < 1 || $size[0] > self::MAX_DIMENSION || $size[1] > self::MAX_DIMENSION) {
            throw ValidationException::withMessages(['image' => 'La imagen tiene dimensiones no permitidas.']);
        }

        $image = $this->load($file->getRealPath(), $mime);
        if (! $image) {
            throw ValidationException::withMessages(['image' => 'La imagen no pudo procesarse.']);
        }

        if ($mime === 'image/jpeg') {
            $image = $this->orient($image, $file->getRealPath());
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $targetWidth = min(self::OUTPUT_WIDTH, $width);
        $targetHeight = max(1, (int) round($height * ($targetWidth / $width)));

        $output = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($output, 255, 255, 255);
        imagefilledrectangle($output, 0, 0, $targetWidth, $targetHeight, $white);
        imagecopyresampled($output, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($output, null, 84);
        $jpeg = ob_get_clean();
        imagedestroy($image);
        imagedestroy($output);

        if (! is_string($jpeg)) {
            throw ValidationException::withMessages(['image' => 'La imagen no pudo procesarse.']);
        }

        $path = 'store/products/'.$product->public_code.'/'.Str::uuid().'.jpg';
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
