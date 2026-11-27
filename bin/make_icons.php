<?php

/** Build the app icons from the logo:  php bin/make_icons.php */

$root = dirname(__DIR__);
$logo = imagecreatefromjpeg($root . '/public/assets/images/Logo.jpg');
if (!$logo) {
    fwrite(STDERR, "Cannot read the logo.\n");
    exit(1);
}

$width = imagesx($logo);
$height = imagesy($logo);
$side = max($width, $height);
@mkdir($root . '/public/assets/icons', 0755, true);

foreach ([192, 512] as $size) {
    $icon = imagecreatetruecolor($size, $size);
    imagefill($icon, 0, 0, imagecolorallocate($icon, 255, 255, 255));
    // Margin keeps the logo inside the safe area of rounded ("maskable") icons
    $scale = $size * 0.8 / $side;
    $w = (int)round($width * $scale);
    $h = (int)round($height * $scale);
    imagecopyresampled($icon, $logo, intdiv($size - $w, 2), intdiv($size - $h, 2), 0, 0, $w, $h, $width, $height);
    imagepng($icon, "{$root}/public/assets/icons/icon-{$size}.png", 9);
}
echo "Icons written to public/assets/icons/\n";
