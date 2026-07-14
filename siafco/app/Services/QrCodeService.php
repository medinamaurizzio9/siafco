<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;

class QrCodeService
{
    public function png(string $data, string $path, int $size = 360, array $foreground = [20, 83, 45]): string
    {
        $result = (new Builder(
            writer: new PngWriter(),
            data: $data,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 12,
            foregroundColor: new Color($foreground[0], $foreground[1], $foreground[2]),
            backgroundColor: new Color(255, 255, 255),
        ))->build();

        Storage::disk('public')->put($path, $result->getString());

        return $path;
    }
}
