<?php

class ImageConverter
{
    const ALLOWED_MIMES = ['image/jpeg' => true, 'image/jpg' => true, 'image/png' => true, 'image/webp' => true];
    const MAX_SIZE = 8388608;

    public static function mimeAllowed($mime)
    {
        $mime = strtolower(trim($mime));
        return isset(self::ALLOWED_MIMES[$mime]);
    }

    public static function convertToJpg($tmpPath, $mime, $destPath)
    {
        $img = null;
        switch (strtolower($mime)) {
            case 'image/jpeg':
            case 'image/jpg':
                $img = @imagecreatefromjpeg($tmpPath);
                break;
            case 'image/png':
                $img = @imagecreatefrompng($tmpPath);
                if ($img) {
                    imagealphablending($img, false);
                    imagesavealpha($img, true);
                }
                break;
            case 'image/webp':
                $img = @imagecreatefromwebp($tmpPath);
                break;
            default:
                return false;
        }
        if (!$img) {
            return false;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $out = imagecreatetruecolor($w, $h);
        if (!$out) {
            imagedestroy($img);
            return false;
        }
        imagecopy($out, $img, 0, 0, 0, 0, $w, $h);
        imagedestroy($img);
        $ok = imagejpeg($out, $destPath, 90);
        imagedestroy($out);
        return $ok;
    }

    public static function convertBlobToJpg($blob, $contentType, $destPath)
    {
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        if ($tmp === false) {
            return false;
        }
        file_put_contents($tmp, $blob);
        $mime = explode(';', $contentType)[0];
        $mime = strtolower(trim($mime));
        $ok = self::convertToJpg($tmp, $mime, $destPath);
        @unlink($tmp);
        return $ok;
    }
}
