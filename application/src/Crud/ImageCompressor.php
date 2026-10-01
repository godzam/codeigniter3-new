<?php

namespace App\Crud;

/**
 * Re-encodes an uploaded image with GD: scales it down to a maximum width and
 * saves it at the given quality. Re-encoding also drops EXIF/metadata and any
 * payload hidden in the file. GIFs are copied as-is (to keep animation).
 */
final class ImageCompressor
{
    /** Refuse pictures bigger than this many pixels (decompression bombs). */
    public const MAX_PIXELS = 40000000;

    public static function available()
    {
        return extension_loaded('gd');
    }

    /**
     * @param string $mime image/jpeg, image/png, image/webp or image/gif
     *
     * @return bool true when $dest was written
     */
    public static function compress($src, $dest, $mime, $maxWidth, $quality)
    {
        if (!self::available()) {
            return false;
        }

        $info = @getimagesize($src);
        if ($info === false || $info[0] * $info[1] > self::MAX_PIXELS) {
            return false;
        }

        if ($mime === 'image/gif') {
            return @copy($src, $dest);
        }

        $image = null;
        switch ($mime) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($src);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($src);
                break;
            case 'image/webp':
                $image = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false;
                break;
        }
        if (!$image) {
            return false;
        }

        if ($mime === 'image/jpeg') {
            $image = self::autoRotate($image, $src);
        }

        $width = imagesx($image);
        if ($maxWidth > 0 && $width > $maxWidth) {
            $scaled = imagescale($image, $maxWidth);
            if ($scaled) {
                $image = $scaled;
            }
        }

        $ok = false;
        switch ($mime) {
            case 'image/jpeg':
                $ok = imagejpeg($image, $dest, $quality);
                break;
            case 'image/png':
                imagesavealpha($image, true);
                // Lossless format: quality does not apply, so use the smallest zlib setting.
                $ok = imagepng($image, $dest, 9);
                break;
            case 'image/webp':
                $ok = function_exists('imagewebp') && imagewebp($image, $dest, $quality);
                break;
        }

        return (bool) $ok;
    }

    /**
     * @param \GdImage $image
     *
     * @return \GdImage
     */
    private static function autoRotate($image, $src)
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($src);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;

        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            if ($rotated) {
                return $rotated;
            }
        }

        return $image;
    }
}
