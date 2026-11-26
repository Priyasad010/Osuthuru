<?php

/**
 * Write every UI string to docs/translation_review.csv so a native Sinhala
 * speaker can review it in a spreadsheet.
 */

$root = dirname(__DIR__);
$en = require $root . '/lang/en.php';
$si = require $root . '/lang/si.php';

$path = $root . '/docs/translation_review.csv';
$out = fopen($path, 'wb');
if ($out === false) {
    fwrite(STDERR, "Cannot write {$path}\n");
    exit(1);
}

// Byte order mark so spreadsheet programs read the file as UTF-8
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['key', 'english', 'sinhala', 'reviewer_correction', 'approved'], ',', '"', '');
foreach ($en as $key => $text) {
    fputcsv($out, [$key, $text, $si[$key] ?? '', '', ''], ',', '"', '');
}
fclose($out);

echo 'Wrote ' . count($en) . " strings to docs/translation_review.csv\n";
