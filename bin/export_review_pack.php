<?php

/**
 * Write the files people need for review:
 *   docs/review/practitioner_review.csv  health-related content, for a qualified practitioner
 *   docs/review/sinhala_content.csv      Sinhala text kept in the database, for a native speaker
 * (Interface text is exported separately by bin/export_translations.php.)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Service\ReportService;

Env::load(dirname(__DIR__));
$pdo = Database::getConnection();
$dir = dirname(__DIR__) . '/docs/review';
@mkdir($dir, 0755, true);

function writeCsv(string $path, array $header, array $rows): void
{
    $out = fopen($path, 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, array_map([ReportService::class, 'csvCell'], $row), ',', '"', '');
    }
    fclose($out);
    echo basename($path) . ': ' . count($rows) . " rows\n";
}

$health = [];
$add = function (string $type, string $item, string $english, string $sinhala, string $detail = '') use (&$health) {
    $health[] = [$type, $item, $english, $sinhala, $detail, '', ''];
};

foreach ($pdo->query('SELECT q.sort_order, q.text_en AS question, o.text_en, o.text_si, o.vata, o.pitta, o.kapha
    FROM quiz_option o JOIN quiz_question q ON q.question_id = o.question_id ORDER BY q.sort_order, o.option_id') as $r) {
    $add('Quiz option', "Q{$r['sort_order']}. {$r['question']}", $r['text_en'], $r['text_si'], "Vata {$r['vata']}, Pitta {$r['pitta']}, Kapha {$r['kapha']}");
}
foreach ($pdo->query('SELECT * FROM dosha_profile ORDER BY dosha') as $r) {
    $add('Dosha description', $r['dosha'], (string)$r['description_en'], (string)$r['description_si']);
    $add('Dosha tips', $r['dosha'], (string)$r['tips_en'], (string)$r['tips_si']);
}
foreach ($pdo->query('SELECT * FROM herb ORDER BY name') as $r) {
    $add('Herb uses', "{$r['name']} ({$r['scientific_name']})", (string)$r['uses'], (string)$r['uses_si']);
}
foreach ($pdo->query('SELECT h.name, c.name_en FROM herb_concern x JOIN herb h ON h.herb_id = x.herb_id JOIN health_concern c ON c.concern_id = x.concern_id ORDER BY h.name') as $r) {
    $add('Herb listed under topic', $r['name'], $r['name_en'], '');
}
foreach ($pdo->query('SELECT h.name, f.name_en, s.* FROM herb_safety s JOIN herb h ON h.herb_id = s.herb_id JOIN safety_flag f ON f.flag_id = s.flag_id ORDER BY h.name') as $r) {
    $add('Safety entry', "{$r['name']} / {$r['name_en']}", (string)$r['note_en'], (string)$r['note_si'],
        "{$r['severity']}; " . ($r['published'] ? 'published' : 'not published') . "; source: {$r['source_note']}");
}
foreach ($pdo->query('SELECT p.product_name, h.name FROM product_herb x JOIN product p ON p.product_id = x.product_id JOIN herb h ON h.herb_id = x.herb_id ORDER BY p.product_name') as $r) {
    $add('Product contains herb', $r['product_name'], $r['name'], '');
}
foreach ($pdo->query('SELECT product_name, description, description_si FROM product ORDER BY product_name') as $r) {
    $add('Product description', $r['product_name'], (string)$r['description'], (string)$r['description_si']);
}
writeCsv("{$dir}/practitioner_review.csv", ['type', 'item', 'english', 'sinhala', 'detail', 'reviewer_correction', 'approved'], $health);

$sinhala = [];
foreach ([
    ['product', 'product_id', ['product_name' => 'product_name_si', 'description' => 'description_si']],
    ['category', 'category_id', ['name' => 'name_si']],
    ['herb', 'herb_id', ['name' => 'name_si', 'uses' => 'uses_si']],
    ['health_concern', 'concern_id', ['name_en' => 'name_si']],
    ['safety_flag', 'flag_id', ['name_en' => 'name_si']],
    ['quiz_question', 'question_id', ['text_en' => 'text_si']],
    ['concern_keyword', 'keyword_id', ['keyword' => 'keyword']],
    ['blog', 'blog_id', ['title' => 'title_si', 'content' => 'content_si']],
] as [$table, $key, $fields]) {
    foreach ($pdo->query("SELECT * FROM {$table} ORDER BY {$key}") as $r) {
        foreach ($fields as $english => $si) {
            // Topic words are listed only when they are written in Sinhala
            if ($table === 'concern_keyword' && !preg_match('/[\x{0D80}-\x{0DFF}]/u', $r[$si])) {
                continue;
            }
            $sinhala[] = [$table, $r[$key], $english, (string)$r[$english], (string)($r[$si] ?? ''), '', ''];
        }
    }
}
writeCsv("{$dir}/sinhala_content.csv", ['table', 'id', 'field', 'english', 'sinhala', 'reviewer_correction', 'approved'], $sinhala);
