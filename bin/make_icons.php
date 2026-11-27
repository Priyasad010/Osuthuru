<?php

/**
 * Build the logo and app icons from the artwork in docs/branding:
 *   php bin/make_icons.php
 */

$root = dirname(__DIR__);
@mkdir($root . '/public/assets/icons', 0755, true);

// source file => [target => size]
$jobs = [
    'osuthuru-icon.png' => ['images/logo.png' => 256, 'icons/icon-192.png' => 192, 'icons/icon-512.png' => 512],
    // square to the edges, for phones that cut their own shape
    'osuthuru-icon-maskable.png' => ['icons/maskable-192.png' => 192, 'icons/maskable-512.png' => 512],
];

foreach ($jobs as $source => $targets) {
    $image = imagecreatefrompng("{$root}/docs/branding/{$source}");
    if (!$image) {
        fwrite(STDERR, "Cannot read {$source}.\n");
        exit(1);
    }
    foreach ($targets as $target => $size) {
        $icon = imagecreatetruecolor($size, $size);
        imagealphablending($icon, false);
        imagesavealpha($icon, true);
        imagecopyresampled($icon, $image, 0, 0, 0, 0, $size, $size, imagesx($image), imagesy($image));
        imagepng($icon, "{$root}/public/assets/{$target}", 9);
    }
}
// favicon.ico for browsers that ask for it by name: a 32px PNG in an ICO wrapper
$small = imagecreatetruecolor(32, 32);
imagealphablending($small, false);
imagesavealpha($small, true);
$source = imagecreatefrompng("{$root}/docs/branding/osuthuru-icon.png");
imagecopyresampled($small, $source, 0, 0, 0, 0, 32, 32, imagesx($source), imagesy($source));
ob_start();
imagepng($small);
$png = (string)ob_get_clean();
$header = pack('vvv', 0, 1, 1) . pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, strlen($png), 22);
file_put_contents("{$root}/public/favicon.ico", $header . $png);

echo "Logo, icons and favicon written to public/\n";
