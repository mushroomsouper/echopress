<?php
function create_image_set(string $src, string $destDir, string $base, string $origName = '', string $prefix = ''): array
{
    $sizes = [320, 640, 1024];
    $webpSet = [];
    $jpgSet  = [];
    $extInfo = pathinfo($origName ?: $src);
    $origExt = strtolower($extInfo['extension'] ?? 'jpg');
    $isJpg   = in_array($origExt, ['jpg','jpeg']);
    $fallback = $isJpg ? "$prefix{$base}.jpg" : "$prefix{$base}.{$origExt}";

    if (extension_loaded('imagick')) {
        $img = new Imagick($src);
        $origWidth = $img->getImageWidth();
        if ($isJpg) {
            $img->setImageBackgroundColor('white');
            $img = $img->flattenImages();
        }
        $img->setImageColorspace(Imagick::COLORSPACE_SRGB);
        $img->stripImage();
        $img->setImageCompressionQuality(90);
        $validSizes = array_filter($sizes, function($s) use ($origWidth) { return $s <= $origWidth; });
        foreach ($validSizes as $size) {
            $tmp = clone $img;
            $tmp->resizeImage($size, 0, Imagick::FILTER_LANCZOS, 1);
            $tmp->setImageFormat('webp');
            $tmp->setImageCompressionQuality(90);
            $tmp->writeImage("$destDir/{$base}-{$size}.webp");
            $webpSet[] = "$prefix{$base}-{$size}.webp {$size}w";
            if ($isJpg) {
                $tmp->setImageFormat('jpeg');
                $tmp->setImageCompressionQuality(90);
                $tmp->writeImage("$destDir/{$base}-{$size}.jpg");
                $jpgSet[] = "$prefix{$base}-{$size}.jpg {$size}w";
            }
            $tmp->destroy();
        }
        $fallbackSize = min(1024, $origWidth);
        $img->resizeImage($fallbackSize, 0, Imagick::FILTER_LANCZOS, 1);
        $img->setImageFormat($isJpg ? 'jpeg' : $origExt);
        $img->setImageCompressionQuality(90);
        $img->writeImage("$destDir/{$base}." . ($isJpg ? 'jpg' : $origExt));
        $img->destroy();
    } elseif (function_exists('imagecreatetruecolor')) {
        $info = getimagesize($src);
        if (!$info) return ['', '', ''];
        $type = $info[2];
        switch ($type) {
            case IMAGETYPE_JPEG:
                $img = imagecreatefromjpeg($src);
                break;
            case IMAGETYPE_PNG:
                $img = imagecreatefrompng($src);
                break;
            case IMAGETYPE_GIF:
                $img = imagecreatefromgif($src);
                break;
            default:
                return ['', '', ''];
        }
        $width = imagesx($img);
        $height = imagesy($img);
        $validSizes = array_filter($sizes, function($s) use ($width) { return $s <= $width; });
        foreach ($validSizes as $size) {
            $ratio = $height / $width;
            $h = (int) round($size * $ratio);
            $tmp = imagecreatetruecolor($size, $h);
            if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
                imagealphablending($tmp, false);
                imagesavealpha($tmp, true);
                $transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
                imagefill($tmp, 0, 0, $transparent);
            }
            imagecopyresampled($tmp, $img, 0, 0, 0, 0, $size, $h, $width, $height);
            if (function_exists('imagewebp')) {
                imagewebp($tmp, "$destDir/{$base}-{$size}.webp");
                $webpSet[] = "$prefix{$base}-{$size}.webp {$size}w";
            }
            if ($isJpg) {
                imagejpeg($tmp, "$destDir/{$base}-{$size}.jpg");
                $jpgSet[] = "$prefix{$base}-{$size}.jpg {$size}w";
            }
            imagedestroy($tmp);
        }
        if ($isJpg) {
            $fallbackSize = min(1024, $width);
            $ratio = $height / $width;
            $h = (int) round($fallbackSize * $ratio);
            $tmp = imagecreatetruecolor($fallbackSize, $h);
            imagecopyresampled($tmp, $img, 0, 0, 0, 0, $fallbackSize, $h, $width, $height);
            imagejpeg($tmp, "$destDir/{$base}.jpg");
            imagedestroy($tmp);
        } else {
            copy($src, "$destDir/{$base}.{$origExt}");
        }
        imagedestroy($img);
    } else {
        copy($src, "$destDir/{$base}.{$origExt}");
        return [$fallback, '', ''];
    }
    return [$fallback, implode(', ', $webpSet), implode(', ', $jpgSet)];
}
?>
