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

        $issuedAt = $existingCredential?->created_at ?? now();

        $qrPath = $this->qrCodeService->png(
            route('verify.show', $affiliate->verification_token),
            "credentials/qr/{$affiliate->registration_number}.png",
            360,
            [0, 0, 0]
        );

        $pdfPath = "credentials/pdf/{$affiliate->registration_number}.pdf";
        $pngPath = "credentials/png/{$affiliate->registration_number}.png";

        $credentialData = $this->presentationData($affiliate, $existingCredential, $issuedAt);
        $this->generatePng($affiliate, $institution, $credentialData, storage_path('app/public/'.$qrPath), $pngPath);
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

    public function presentationData(Affiliate $affiliate, ?DigitalCredential $credential = null, $issuedAt = null): array
    {
        $affiliate->loadMissing('sector');
        $institution = InstitutionalSetting::current();
        $date = $issuedAt ?? $credential?->created_at ?? $affiliate->created_at;

        return [
            'full_name' => mb_strtoupper($affiliate->full_name),
            'affiliate_number' => $affiliate->registration_number,
            'identity_document' => mb_strtoupper($affiliate->ci),
            'sector' => mb_strtoupper($affiliate->sector?->name ?? 'NO REGISTRADO'),
            'regional' => mb_strtoupper($affiliate->regional ?: 'NO REGISTRADO'),
            'institution' => mb_strtoupper($affiliate->institution ?: $institution->institution_name ?: 'NO REGISTRADO'),
            'issued_at' => $date?->timezone(config('app.timezone'))->format('d/m/Y') ?? 'NO REGISTRADA',
            'version' => config('siafco.credential_version', '2026.1'),
            'institutional_website' => config('siafco.institutional_website', 'www.cooperativatierrabendita.com'),
            'status_label' => mb_strtoupper(AffiliationStatusPresenter::label($affiliate->status)),
        ];
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

    private function generatePng(Affiliate $affiliate, InstitutionalSetting $institution, array $credentialData, string $qrAbsolutePath, string $pngPath): void
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

        $this->pasteImageOpacity($image, $institution->logoAbsolutePath(), 205, 135, 350, 300, 4);

        imagefilledrectangle($image, 0, 0, $width, 88, $navy);
        imagefilledrectangle($image, 0, 88, $width, 94, $gold);
        $this->pasteImage($image, $institution->logoAbsolutePath(), 24, 11, 66, 66, true, null, 'SIAFCO');
        $this->textFit(
            $image,
            mb_strtoupper($institution->institution_name ?: 'COOPERATIVA TIERRA BENDITA'),
            108,
            43,
            700,
            22,
            $white,
            $fontBold,
            14
        );
        $this->text($image, 'SISTEMA INTEGRAL DE AFILIACIÓN', 108, 67, 11, $white, $fontBold);

        imagefilledrectangle($image, 24, 108, 230, 136, $gold);
        $this->text($image, 'CREDENCIAL DE AFILIADO', 38, 128, 14, $navy, $fontBold);

        $this->labelBlock($image, 'NOMBRE COMPLETO', $credentialData['full_name'], 24, 153, 330, 16, 2, $muted, $navy, $fontBold);
        $this->labelBlock($image, 'NÚMERO DE AFILIADO', $credentialData['affiliate_number'], 24, 207, 330, 12, 1, $muted, $navy, $fontBold);
        $this->labelBlock($image, 'SECTOR', $credentialData['sector'], 24, 250, 330, 11, 2, $muted, $navy, $fontBold);
        $this->labelBlock($image, 'INSTITUCIÓN', $credentialData['institution'], 24, 307, 330, 11, 2, $muted, $navy, $fontBold);

        $this->labelBlock($image, 'CÉDULA DE IDENTIDAD', $credentialData['identity_document'], 375, 153, 225, 12, 1, $muted, $navy, $fontBold);
        $this->labelBlock($image, 'REGIONAL', $credentialData['regional'], 375, 207, 225, 12, 1, $muted, $navy, $fontBold);
        $this->labelBlock($image, 'FECHA DE EMISIÓN', $credentialData['issued_at'], 375, 261, 225, 12, 1, $muted, $navy, $fontBold);
        $this->labelBlock($image, 'VERSIÓN', $credentialData['version'], 375, 315, 225, 12, 1, $muted, $navy, $fontBold);

        imagefilledrectangle($image, 24, 348, 178, 502, $white);
        imagerectangle($image, 24, 348, 178, 502, $soft);
        $this->pasteImage($image, $qrAbsolutePath, 31, 355, 140, 140, true);
        $this->text($image, 'ESCANEA PARA VERIFICAR', 193, 405, 10, $navy, $fontBold);
        $this->textBlockFit($image, 'Consulta la autenticidad y el estado actual de esta credencial.', 193, 426, 185, 9, 3, $muted, $font, 8, 13);

        imagefilledrectangle($image, 670, 112, 829, 308, $navy);
        imagefilledrectangle($image, 672, 114, 827, 306, $gold);
        $this->pasteImageCover(
            $image,
            $affiliate->photo_path ? storage_path('app/public/'.$affiliate->photo_path) : null,
            676,
            118,
            147,
            184,
            $soft,
            'SIN FOTO'
        );

        $normalizedStatus = strtolower($affiliate->status);
        [$statusBackground, $statusText] = match ($normalizedStatus) {
            'activo', 'active', 'confirmado', 'confirmed' => [$greenLight, $greenDark],
            'suspendido', 'suspended' => [$redLight, $redDark],
            default => [$amberLight, $amberDark],
        };
        imagefilledrectangle($image, 670, 319, 829, 350, $statusBackground);
        imagefilledellipse($image, 686, 335, 13, 13, $statusText);
        imageline($image, 682, 335, 685, 338, $statusBackground);
        imageline($image, 685, 338, 690, 331, $statusBackground);
        $this->textFit(
            $image,
            $credentialData['status_label'],
            698,
            340,
            122,
            9,
            $statusText,
            $fontBold,
            7
        );

        imagefilledrectangle($image, 0, 506, $width, $height, $navy);
        $this->text($image, 'Válida mientras la afiliación permanezca activa.', 24, 528, 8, $white, $font);
        $this->textFit($image, $credentialData['institutional_website'], 650, 528, 176, 8, $white, $font, 7);

        Storage::disk('public')->makeDirectory(dirname($pngPath));
        imagepng($image, storage_path('app/public/'.$pngPath), 9);
        imagedestroy($image);
    }

    private function labelValue($image, string $label, string $value, int $x, int $y, int $labelColor, int $valueColor, ?string $font, ?string $fontBold, int $valueSize, int $maxWidth): void
    {
        $this->text($image, $label, $x, $y, 8, $labelColor, $fontBold);
        $this->textFit($image, $value, $x, $y + 25, $maxWidth, $valueSize, $valueColor, $fontBold, 9);
    }

    private function labelBlock($image, string $label, string $value, int $x, int $y, int $maxWidth, int $valueSize, int $maxLines, int $labelColor, int $valueColor, ?string $fontBold): void
    {
        $this->text($image, $label, $x, $y, 8, $labelColor, $fontBold);
        $this->textBlockFit($image, $value, $x, $y + 21, $maxWidth, $valueSize, $maxLines, $valueColor, $fontBold, 8, $valueSize + 3);
    }

    private function textBlockFit($image, string $text, int $x, int $y, int $maxWidth, int $size, int $maxLines, int $color, ?string $font, int $minSize, int $lineHeight): void
    {
        if (! $font) {
            $this->text($image, mb_strimwidth($text, 0, 44, '...'), $x, $y, $size, $color, null);

            return;
        }

        $currentSize = $size;
        do {
            $lines = $this->wrapText($text, $font, $currentSize, $maxWidth);
            if (count($lines) <= $maxLines || $currentSize <= $minSize) {
                break;
            }
            $currentSize--;
        } while ($currentSize >= $minSize);

        $lines = array_slice($lines, 0, $maxLines);
        if (count($this->wrapText($text, $font, $currentSize, $maxWidth)) > $maxLines) {
            $lines[$maxLines - 1] = mb_strimwidth($lines[$maxLines - 1], 0, max(1, mb_strlen($lines[$maxLines - 1]) - 2), '...');
        }

        foreach ($lines as $index => $line) {
            $this->textFit($image, $line, $x, $y + ($index * $lineHeight), $maxWidth, $currentSize, $color, $font, $minSize);
        }
    }

    private function wrapText(string $text, string $font, int $size, int $maxWidth): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;
            $box = imagettfbbox($size, 0, $font, $candidate);
            if (abs($box[2] - $box[0]) <= $maxWidth || $line === '') {
                $line = $candidate;
                continue;
            }

            $lines[] = $line;
            $line = $word;
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines ?: [''];
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

    private function pasteImageOpacity($canvas, ?string $path, int $x, int $y, int $targetWidth, int $targetHeight, int $opacityPercent): void
    {
        $source = $this->loadImage($path);
        if (! $source) {
            return;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $ratio = min($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $destWidth = max(1, (int) ($sourceWidth * $ratio));
        $destHeight = max(1, (int) ($sourceHeight * $ratio));
        $destX = $x + (int) (($targetWidth - $destWidth) / 2);
        $destY = $y + (int) (($targetHeight - $destHeight) / 2);

        $watermark = imagecreatetruecolor($destWidth, $destHeight);
        imagealphablending($watermark, false);
        imagesavealpha($watermark, true);
        $transparent = imagecolorallocatealpha($watermark, 255, 255, 255, 127);
        imagefilledrectangle($watermark, 0, 0, $destWidth, $destHeight, $transparent);
        imagecopyresampled($watermark, $source, 0, 0, 0, 0, $destWidth, $destHeight, $sourceWidth, $sourceHeight);
        imagefilter($watermark, IMG_FILTER_GRAYSCALE);
        imagefilter($watermark, IMG_FILTER_COLORIZE, 90, 100, 112, 127 - (int) round(127 * ($opacityPercent / 100)));
        imagealphablending($canvas, true);
        imagecopy($canvas, $watermark, $destX, $destY, 0, 0, $destWidth, $destHeight);

        imagedestroy($watermark);
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
