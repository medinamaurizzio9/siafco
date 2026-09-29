<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentVoucherService
{
    private const DIRECTORY = 'payments/vouchers';
    private const TARGET_BYTES = 512000;
    private const MAX_DIMENSION = 2200;
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];
    private const PDF_MIME = 'application/pdf';

    public function store(?UploadedFile $file, string $field = 'voucher', string $directory = self::DIRECTORY): ?string
    {
        if (! $file) {
            return null;
        }

        $mime = (string) ($file->getMimeType() ?: mime_content_type($file->getRealPath()));

        if ($mime === self::PDF_MIME) {
            return $this->storePdf($file, $directory);
        }

        if (! in_array($mime, self::IMAGE_MIMES, true) || ! @getimagesize($file->getRealPath())) {
            throw ValidationException::withMessages([$field => 'El comprobante no tiene un formato válido.']);
        }

        if (($file->getSize() ?: 0) <= self::TARGET_BYTES) {
            return $this->storeOriginalImage($file, $mime, $directory);
        }

        return $this->storeOptimizedImage($file, $mime, $field, $directory);
    }

    public function metadata(?UploadedFile $file, ?string $path): array
    {
        if (! $file) {
            return ['has_file' => false];
        }

        return [
            'has_file' => true,
            'stored_path' => $path ? basename($path) : null,
            'original_extension' => strtolower((string) $file->getClientOriginalExtension()),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ];
    }

    private function storePdf(UploadedFile $file, string $directory): string
    {
        return $file->storeAs($directory, (string) Str::uuid().'.pdf', 'local');
    }

    private function storeOriginalImage(UploadedFile $file, string $mime, string $directory): string
    {
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        };

        return $file->storeAs($directory, (string) Str::uuid().'.'.$extension, 'local');
    }

    private function storeOptimizedImage(UploadedFile $file, string $mime, string $field, string $directory): string
    {
        if (! function_exists('imagewebp')) {
            return $this->storeOriginalImage($file, $mime, $directory);
        }

        $image = $this->loadImage($file->getRealPath(), $mime);
        if (! $image) {
            throw ValidationException::withMessages([$field => 'El comprobante no pudo procesarse.']);
        }

        if ($mime === 'image/jpeg') {
            $image = $this->orient($image, $file->getRealPath());
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_DIMENSION / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $output = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($output, 255, 255, 255);
        imagefilledrectangle($output, 0, 0, $targetWidth, $targetHeight, $white);
        imagecopyresampled($output, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $contents = $this->encodeWebp($output);

        imagedestroy($image);
        imagedestroy($output);

        if ($contents === '') {
            throw ValidationException::withMessages([$field => 'El comprobante no pudo procesarse.']);
        }

        $path = $directory.'/'.Str::uuid().'.webp';
        Storage::disk('local')->put($path, $contents);

        return $path;
    }

    private function encodeWebp(\GdImage $image): string
    {
        foreach ([88, 82, 76, 70] as $quality) {
            ob_start();
            imagewebp($image, null, $quality);
            $contents = (string) ob_get_clean();
            if (strlen($contents) <= self::TARGET_BYTES || $quality === 70) {
                return $contents;
            }
        }

        return '';
    }

    private function loadImage(string $path, string $mime): \GdImage|false
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

        $orientation = @exif_read_data($path)['Orientation'] ?? null;
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        if ($rotated !== $image) {
            imagedestroy($image);
        }

        return $rotated;
    }
}
