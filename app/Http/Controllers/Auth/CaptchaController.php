<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class CaptchaController extends Controller
{
    /**
     * Generate a captcha image with random alphanumeric characters.
     * Stores the code in session('captcha_code') for validation.
     */
    public function generate(): Response
    {
        $length = 5;
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        // Store lowercase for case-insensitive comparison
        session(['captcha_code' => strtolower($code)]);

        // Generate image
        $width = 140;
        $height = 48;
        $image = imagecreatetruecolor($width, $height);

        // Background
        $bg = imagecolorallocate($image, 245, 245, 245);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $bg);

        // Noise lines
        for ($i = 0; $i < 6; $i++) {
            $lineColor = imagecolorallocate($image, random_int(150, 220), random_int(150, 220), random_int(150, 220));
            imageline($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $lineColor);
        }

        // Noise dots
        for ($i = 0; $i < 80; $i++) {
            $dotColor = imagecolorallocate($image, random_int(180, 240), random_int(180, 240), random_int(180, 240));
            imagesetpixel($image, random_int(0, $width), random_int(0, $height), $dotColor);
        }

        // Draw characters
        $x = 12;
        for ($i = 0; $i < $length; $i++) {
            $fontSize = random_int(4, 6);
            $textColor = imagecolorallocate($image, random_int(20, 100), random_int(20, 100), random_int(20, 100));
            $char = $code[$i];
            imagestring($image, $fontSize, $x, random_int(6, 16), $char, $textColor);
            $x += random_int(20, 26);
        }

        ob_start();
        imagepng($image);
        $content = ob_get_clean();
        imagedestroy($image);

        return new Response($content, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
