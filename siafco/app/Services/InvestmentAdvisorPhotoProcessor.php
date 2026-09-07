<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvestmentAdvisorPhotoProcessor
{
    public function process(UploadedFile $file): string
    {
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $image=match($mime){'image/jpeg'=>@imagecreatefromjpeg($file->getRealPath()),'image/png'=>@imagecreatefrompng($file->getRealPath()),'image/webp'=>function_exists('imagecreatefromwebp')?@imagecreatefromwebp($file->getRealPath()):false,default=>false};
        if(!$image) throw ValidationException::withMessages(['photo'=>'La fotografía no es una imagen válida.']);
        $width=imagesx($image); $height=imagesy($image); $side=min($width,$height); $x=(int)(($width-$side)/2); $y=(int)(($height-$side)/2);
        $output=imagecreatetruecolor(480,480); imagecopyresampled($output,$image,0,0,$x,$y,480,480,$side,$side);
        ob_start(); imagejpeg($output,null,86); $contents=ob_get_clean(); imagedestroy($image); imagedestroy($output);
        if(!is_string($contents)) throw ValidationException::withMessages(['photo'=>'La fotografía no pudo procesarse.']);
        $path='investments/advisors/photos/'.Str::uuid().'.jpg'; Storage::disk('public')->put($path,$contents); return $path;
    }
}
