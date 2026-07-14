<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\DigitalCredential;
use App\Models\InstitutionalSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class CredentialService
{
    public function __construct(private QrCodeService $qrCodeService)
    {
    }

    public function generate(Affiliate $affiliate): DigitalCredential
    {
        $affiliate->load('sector');
        $institution = InstitutionalSetting::current();

        $existingCredential = $affiliate->credential;
        if ($existingCredential && ! $this->shouldRegenerate($existingCredential, $affiliate, $institution)) {
            return $existingCredential;
        }

        $qrPath = $this->qrCodeService->png(
            route('verify.show', $affiliate->verification_token),
            "credentials/qr/{$affiliate->registration_number}.png",
            360,
            [0, 0, 0]
        );

        $pdfPath = "credentials/pdf/{$affiliate->registration_number}.pdf";
        $pngPath = "credentials/png/{$affiliate->registration_number}.png";

        $this->generatePng($affiliate, $institution, storage_path('app/public/'.$qrPath), $pngPath);
        $pdf = Pdf::loadView('credentials.pdf', [
            'cardImageDataUri' => $this->dataUri(storage_path('app/public/'.$pngPath)),
        ])->setPaper([0, 0, 286.3, 181.4]);

        Storage::disk('public')->put($pdfPath, $pdf->output());

        return DigitalCredential::updateOrCreate(
            ['affiliate_id' => $affiliate->id],
            [
                'qr_path' => $qrPath,
                'pdf_path' => $pdfPath,
                'png_path' => $pngPath,
                'generated_at' => now(),
            ]
        );
    }

    private function shouldRegenerate(DigitalCredential $credential, Affiliate $affiliate, InstitutionalSetting $institution): bool
    {
        if (! $credential->pdf_path || ! $credential->png_path || ! $credential->qr_path) {
            return true;
        }

        if (
            ! Storage::disk('public')->exists($credential->pdf_path) ||
            ! Storage::disk('public')->exists($credential->png_path) ||
            ! Storage::disk('public')->exists($credential->qr_path)
        ) {
            return true;
        }

        if (! $credential->generated_at) {
            return true;
        }

        return $credential->generated_at->lessThan($affiliate->updated_at)
            || $credential->generated_at->lessThan($institution->updated_at);
    }

    private function generatePng(Affiliate $affiliate, InstitutionalSetting $institution, string $qrAbsolutePath, string $pngPath): void
    {
        $width = 1011;
        $height = 638;
        $image = imagecreatetruecolor($width, $height);

        $navy = $this->allocateHex($image, $institution->primary_color ?: '#171345');
        $darkNavy = imagecolorallocate($image, 8, 10, 54);
        $gold = $this->allocateHex($image, $institution->secondary_color ?: '#d99a00');
        $goldLight = imagecolorallocate($image, 244, 207, 120);
        $white = imagecolorallocate($image, 255, 255, 255);
        $soft = imagecolorallocate($image, 238, 242, 247);
        $text = imagecolorallocate($image, 8, 11, 70);

        imagefilledrectangle($image, 0, 0, $width, $height, $white);
        for ($x = -80; $x < $width; $x += 70) {
            imageline($image, $x, 0, $x + 300, $height, imagecolorallocatealpha($image, 8, 11, 70, 118));
            imageline($image, $x + 300, 0, $x, $height, imagecolorallocatealpha($image, 8, 11, 70, 120));
        }

        imagefilledpolygon($image, [0, 0, $width, 0, $width, 86, 820, 72, 665, 30, 445, 16, 230, 30, 0, 86], $navy);
        imagefilledellipse($image, 910, -92, 430, 220, imagecolorallocatealpha($image, 255, 255, 255, 112));
        imagefilledpolygon($image, [0, 88, 120, 50, 350, 30, 585, 42, 775, 82, $width, 88, $width, 132, 815, 126, 660, 96, 430, 64, 180, 68, 0, 112], $gold);
        imagefilledpolygon($image, [0, 112, 160, 76, 392, 58, 610, 74, 810, 118, $width, 130, $width, 164, 830, 160, 650, 132, 420, 90, 185, 92, 0, 132], $goldLight);

        $fontBold = $this->fontPath('arialbd.ttf');
        $font = $this->fontPath('arial.ttf');

        imagefilledellipse($image, 118, 112, 192, 192, $darkNavy);
        imageellipse($image, 118, 112, 192, 192, $gold);
        imageellipse($image, 118, 112, 184, 184, $gold);
        $this->pasteImage($image, $institution->logoAbsolutePath(), 34, 28, 168, 168, true, null, 'SIAFCO');

        $institutionLines = preg_split('/\s+(?=TIERRA\b)/', mb_strtoupper($institution->institution_name ?: 'COOPERATIVA TIERRA BENDITA R.L.'), 2);
        $this->textFit($image, $institutionLines[0] ?? '', 236, 148, 570, 30, $text, $fontBold, 18);
        if (! empty($institutionLines[1])) {
            $this->textFit($image, $institutionLines[1], 236, 184, 570, 30, $text, $fontBold, 18);
        }

        imagefilledrectangle($image, 64, 236, 564, 284, $navy);
        $this->text($image, 'CARNET DE AFILIADO', 94, 269, 29, $white, $fontBold);

        $fieldX = 94;
        $valueX = 276;
        $sepX = 242;
        $longName = mb_strlen($affiliate->full_name) > 24;
        $this->credentialNameField($image, mb_strtoupper($affiliate->full_name), $fieldX, $sepX, $valueX, 323, $text, $font);
        $this->credentialField($image, 'ID', $affiliate->registration_number, $fieldX, $sepX, $valueX, $longName ? 390 : 368, $text, $font, $fontBold, 30, 390);
        $this->credentialField($image, 'C.I.', $affiliate->ci, $fieldX, $sepX, $valueX, $longName ? 435 : 413, $text, $font, $fontBold, 30, 390);

        imagefilledrectangle($image, 265, 536, 1011, 584, $navy);
        imagefilledpolygon($image, [220, 536, 265, 536, 265, 584, 220, 584], $navy);
        imagefilledrectangle($image, 72, 448, 264, 632, $white);
        $this->pasteImage($image, $qrAbsolutePath, 80, 456, 176, 176, false);

        imagefilledrectangle($image, 704, 207, 944, 527, $white);
        for ($i = 0; $i < 4; $i++) {
            imagerectangle($image, 704 + $i, 207 + $i, 944 - $i, 527 - $i, $navy);
        }
        $this->pasteImage($image, $affiliate->photo_path ? storage_path('app/public/'.$affiliate->photo_path) : null, 708, 211, 232, 312, false, $soft, 'SIN FOTO');

        Storage::disk('public')->makeDirectory(dirname($pngPath));
        imagepng($image, storage_path('app/public/'.$pngPath), 9);
        imagedestroy($image);
    }

    private function labelValue($image, string $label, string $value, int $x, int $y, int $labelColor, int $valueColor, ?string $font, ?string $fontBold): void
    {
        $this->text($image, $label, $x, $y, 14, $labelColor, $fontBold);
        $this->text($image, $value, $x, $y + 32, 22, $valueColor, $font);
    }

    private function dataUri(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }

    private function credentialField($image, string $label, string $value, int $labelX, int $sepX, int $valueX, int $y, int $color, ?string $font, ?string $fontBold, int $size, int $maxWidth): void
    {
        $this->text($image, $label, $labelX, $y, $size, $color, $font);
        $this->text($image, ':', $sepX, $y, $size, $color, $font);
        $this->textFit($image, $value, $valueX, $y, $maxWidth, $size, $color, $font, 20);
    }

    private function credentialNameField($image, string $value, int $labelX, int $sepX, int $valueX, int $y, int $color, ?string $font): void
    {
        $this->text($image, 'NOMBRE', $labelX, $y, 30, $color, $font);
        $this->text($image, ':', $sepX, $y, 30, $color, $font);

        if (mb_strlen($value) <= 24) {
            $this->textFit($image, $value, $valueX, $y, 410, 29, $color, $font, 20);
            return;
        }

        $lines = explode("\n", wordwrap($value, 22, "\n"));
        $this->textFit($image, $lines[0] ?? '', $valueX, $y, 390, 20, $color, $font, 20);
        $this->textFit($image, $lines[1] ?? '', $valueX, $y + 24, 390, 20, $color, $font, 20);
    }

    private function textFit($image, string $text, int $x, int $y, int $maxWidth, int $size, int $color, ?string $font, int $minSize = 10): void
    {
        if (! $font) {
            $this->text($image, mb_strimwidth($text, 0, 44, '...'), $x, $y, $size, $color, $font);
            return;
        }

        $currentSize = $size;
        while ($currentSize > $minSize) {
            $box = imagettfbbox($currentSize, 0, $font, $text);
            $width = abs($box[2] - $box[0]);
            if ($width <= $maxWidth) {
                break;
            }
            $currentSize--;
        }

        imagettftext($image, $currentSize, 0, $x, $y, $color, $font, $text);
    }

    private function text($image, string $text, int $x, int $y, int $size, int $color, ?string $font): void
    {
        if ($font) {
            imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
            return;
        }

        imagestring($image, 5, $x, $y, $text, $color);
    }

    private function pasteImage($canvas, ?string $path, int $x, int $y, int $targetWidth, int $targetHeight, bool $preserveRatio = true, ?int $background = null, string $placeholder = 'SIAFCO'): void
    {
        if ($background !== null) {
            imagefilledrectangle($canvas, $x, $y, $x + $targetWidth, $y + $targetHeight, $background);
        }

        $source = $this->loadImage($path);
        if (! $source) {
            imagestring($canvas, 5, $x + 20, $y + (int) ($targetHeight / 2), $placeholder, imagecolorallocate($canvas, 212, 175, 55));
            return;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $destWidth = $targetWidth;
        $destHeight = $targetHeight;
        $destX = $x;
        $destY = $y;

        if ($preserveRatio) {
            $ratio = min($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
            $destWidth = (int) ($sourceWidth * $ratio);
            $destHeight = (int) ($sourceHeight * $ratio);
            $destX = $x + (int) (($targetWidth - $destWidth) / 2);
            $destY = $y + (int) (($targetHeight - $destHeight) / 2);
        }

        imagecopyresampled($canvas, $source, $destX, $destY, 0, 0, $destWidth, $destHeight, $sourceWidth, $sourceHeight);
        imagedestroy($source);
    }

    private function loadImage(?string $path)
    {
        if (! $path || ! is_file($path)) {
            return null;
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'gif' => @imagecreatefromgif($path),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default => null,
        };
    }

    private function allocateHex($image, string $hex): int
    {
        $hex = ltrim($hex, '#');

        return imagecolorallocate($image, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }

    private function fontPath(string $file): ?string
    {
        $path = 'C:\\Windows\\Fonts\\'.$file;

        return is_file($path) ? $path : null;
    }
}
