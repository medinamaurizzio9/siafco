<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\DigitalCredential;
use App\Models\InstitutionalSetting;
use App\Support\AffiliationStatusPresenter;
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
        ])->setPaper([0, 0, 242.65, 153.01]);

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

        return $credential->generated_at->timestamp < filemtime(__FILE__)
            || $credential->generated_at->lessThan($affiliate->updated_at)
            || $credential->generated_at->lessThan($institution->updated_at);
    }

    private function generatePng(Affiliate $affiliate, InstitutionalSetting $institution, string $qrAbsolutePath, string $pngPath): void
    {
        $width = 850;
        $height = 540;
        $image = imagecreatetruecolor($width, $height);

        $navy = $this->allocateHex($image, $institution->primary_color ?: '#0B1F3A');
        $gold = $this->allocateHex($image, $institution->secondary_color ?: '#D8A928');
        $white = imagecolorallocate($image, 255, 255, 255);
        $soft = imagecolorallocate($image, 244, 246, 248);
        $muted = imagecolorallocate($image, 102, 115, 134);
        $greenLight = imagecolorallocate($image, 220, 252, 231);
        $greenDark = imagecolorallocate($image, 22, 101, 52);
        $amberLight = imagecolorallocate($image, 254, 243, 199);
        $amberDark = imagecolorallocate($image, 146, 64, 14);
        $redLight = imagecolorallocate($image, 254, 226, 226);
        $redDark = imagecolorallocate($image, 153, 27, 27);

        imagefilledrectangle($image, 0, 0, $width, $height, $white);
        $pattern = imagecolorallocate($image, 241, 243, 246);
        for ($x = -500; $x < $width; $x += 64) {
            imageline($image, $x, 104, $x + 430, $height - 40, $pattern);
        }

        $fontBold = $this->fontPath('arialbd.ttf');
        $font = $this->fontPath('arial.ttf');

        imagefilledrectangle($image, 0, 0, $width, 98, $navy);
        imagefilledrectangle($image, 0, 98, $width, 103, $gold);
        $this->pasteImage($image, $institution->logoAbsolutePath(), 28, 13, 72, 72, true, null, 'SIAFCO');
        $this->textFit(
            $image,
            mb_strtoupper($institution->institution_name ?: 'COOPERATIVA TIERRA BENDITA'),
            118,
            48,
            680,
            23,
            $white,
            $fontBold,
            14
        );
        $this->text($image, 'SISTEMA INTEGRAL DE AFILIACIÓN', 118, 72, 11, $white, $fontBold);

        imagefilledrectangle($image, 28, 123, 246, 154, $gold);
        $this->text($image, 'CREDENCIAL DE AFILIADO', 43, 145, 15, $navy, $fontBold);

        $this->labelValue($image, 'NOMBRE COMPLETO', mb_strtoupper($affiliate->full_name), 28, 173, $muted, $navy, $font, $fontBold, 13, 535);
        $this->labelValue($image, 'NÚMERO DE AFILIADO', $affiliate->registration_number, 28, 226, $muted, $navy, $font, $fontBold, 13, 245);
        $this->labelValue($image, 'CÉDULA DE IDENTIDAD', mb_strtoupper($affiliate->ci), 302, 226, $muted, $navy, $font, $fontBold, 13, 245);
        $this->labelValue($image, 'SECTOR', mb_strtoupper($affiliate->sector?->name ?? 'NO REGISTRADO'), 28, 279, $muted, $navy, $font, $fontBold, 13, 245);
        $this->labelValue($image, 'REGIONAL', mb_strtoupper($affiliate->regional ?: 'NO REGISTRADO'), 302, 279, $muted, $navy, $font, $fontBold, 13, 245);
        $this->labelValue(
            $image,
            'INSTITUCIÓN',
            mb_strtoupper($affiliate->institution ?: $institution->institution_name ?: 'NO REGISTRADO'),
            28,
            332,
            $muted,
            $navy,
            $font,
            $fontBold,
            13,
            535
        );

        imagefilledrectangle($image, 28, 387, 132, 491, $white);
        imagerectangle($image, 28, 387, 132, 491, $soft);
        $this->pasteImage($image, $qrAbsolutePath, 34, 393, 92, 92, true);
        $this->text($image, 'ESCANEA PARA VERIFICAR', 148, 424, 11, $navy, $fontBold);
        $this->text($image, 'Consulta la validez de esta credencial en línea.', 148, 446, 9, $muted, $font);

        imagefilledrectangle($image, 644, 126, 817, 340, $navy);
        imagefilledrectangle($image, 646, 128, 815, 338, $gold);
        $this->pasteImageCover(
            $image,
            $affiliate->photo_path ? storage_path('app/public/'.$affiliate->photo_path) : null,
            650,
            132,
            161,
            202,
            $soft,
            'SIN FOTO'
        );

        $normalizedStatus = strtolower($affiliate->status);
        [$statusBackground, $statusText] = match ($normalizedStatus) {
            'activo', 'active', 'confirmado', 'confirmed' => [$greenLight, $greenDark],
            'suspendido', 'suspended' => [$redLight, $redDark],
            default => [$amberLight, $amberDark],
        };
        imagefilledrectangle($image, 646, 357, 815, 390, $statusBackground);
        imagefilledellipse($image, 663, 374, 14, 14, $statusText);
        imageline($image, 659, 374, 662, 377, $statusBackground);
        imageline($image, 662, 377, 667, 370, $statusBackground);
        $this->textFit(
            $image,
            mb_strtoupper(AffiliationStatusPresenter::label($affiliate->status)),
            675,
            379,
            130,
            10,
            $statusText,
            $fontBold,
            7
        );

        imagefilledrectangle($image, 0, 500, $width, $height, $navy);
        $this->text($image, 'Válida mientras la afiliación permanezca activa.', 28, 525, 9, $white, $font);
        $this->text($image, 'siafco.viankagold.com', 697, 525, 9, $white, $font);

        Storage::disk('public')->makeDirectory(dirname($pngPath));
        imagepng($image, storage_path('app/public/'.$pngPath), 9);
        imagedestroy($image);
    }

    private function labelValue($image, string $label, string $value, int $x, int $y, int $labelColor, int $valueColor, ?string $font, ?string $fontBold, int $valueSize, int $maxWidth): void
    {
        $this->text($image, $label, $x, $y, 8, $labelColor, $fontBold);
        $this->textFit($image, $value, $x, $y + 25, $maxWidth, $valueSize, $valueColor, $fontBold, 9);
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

    private function pasteImageCover($canvas, ?string $path, int $x, int $y, int $targetWidth, int $targetHeight, int $background, string $placeholder): void
    {
        imagefilledrectangle($canvas, $x, $y, $x + $targetWidth, $y + $targetHeight, $background);

        $source = $this->loadImage($path);
        if (! $source) {
            $placeholderColor = imagecolorallocate($canvas, 102, 115, 134);
            imagestring($canvas, 5, $x + 42, $y + (int) ($targetHeight / 2), $placeholder, $placeholderColor);

            return;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $targetWidth / $targetHeight;

        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
            $sourceX = (int) (($sourceWidth - $cropWidth) / 2);
            $sourceY = 0;
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
            $sourceX = 0;
            $sourceY = (int) (($sourceHeight - $cropHeight) / 2);
        }

        imagecopyresampled(
            $canvas,
            $source,
            $x,
            $y,
            $sourceX,
            $sourceY,
            $targetWidth,
            $targetHeight,
            $cropWidth,
            $cropHeight
        );
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
        $isBold = str_contains(strtolower($file), 'bd') || str_contains(strtolower($file), 'bold');
        $candidates = [
            resource_path('fonts/'.$file),
            resource_path('fonts/'.strtolower($file)),
            'C:/Windows/Fonts/'.$file,
            $isBold ? '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf' : '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
