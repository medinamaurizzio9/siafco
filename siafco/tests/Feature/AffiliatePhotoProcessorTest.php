<?php

namespace Tests\Feature;

use App\Services\AffiliatePhotoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AffiliatePhotoProcessorTest extends TestCase
{
    public function test_jpg_and_png_are_reencoded_as_secure_square_jpegs(): void
    {
        Storage::fake('public');
        $processor = app(AffiliatePhotoProcessor::class);

        foreach ([
            UploadedFile::fake()->image('portrait.jpg', 700, 1100),
            UploadedFile::fake()->image('horizontal.png', 1200, 700),
        ] as $file) {
            $path = $processor->process($file);
            Storage::disk('public')->assertExists($path);
            $this->assertMatchesRegularExpression('/^affiliates\/photos\/[0-9a-f-]{36}\.jpg$/', $path);
            $this->assertSame([600, 600], array_slice(getimagesize(Storage::disk('public')->path($path)), 0, 2));
            $this->assertSame('image/jpeg', mime_content_type(Storage::disk('public')->path($path)));
        }
    }

    public function test_webp_is_accepted_when_supported_by_gd(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD no incluye soporte WEBP.');
        }

        Storage::fake('public');
        $image = imagecreatetruecolor(800, 600);
        $color = imagecolorallocate($image, 30, 90, 140);
        imagefilledrectangle($image, 0, 0, 800, 600, $color);
        ob_start();
        imagewebp($image, null, 90);
        $content = ob_get_clean();
        imagedestroy($image);

        $file = UploadedFile::fake()->createWithContent('camera.webp', $content);
        $path = app(AffiliatePhotoProcessor::class)->process($file);

        $this->assertSame([600, 600], array_slice(getimagesize(Storage::disk('public')->path($path)), 0, 2));
        $this->assertEmpty(@exif_read_data(Storage::disk('public')->path($path))['GPS'] ?? null);
    }
}
