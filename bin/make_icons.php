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
echo "Logo and icons written to public/assets/\n";
