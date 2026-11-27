<?php

namespace App\Service;

use App\Core\Database;
use App\Repository\AssistantRepo;
use App\Repository\HerbRepo;
use App\Repository\ProductRepo;

/**
 * Finds the catalogue entries a free-text question is about (English, Sinhala or Singlish).
 */
class RetrievalService
{
    private const MAX_HERBS = 6;
    private const MAX_PRODUCTS = 6;

    /** Words too common to identify a herb or product. */
    private const STOP_WORDS = [
        'the', 'and', 'for', 'you', 'have', 'has', 'what', 'which', 'with', 'can', 'cannot', 'feel', 'need', 'want',
        'any', 'some', 'good', 'use', 'used', 'please', 'does', 'are', 'was', 'how', 'my', 'me', 'is', 'it', 'do',
        'herbal', 'natural', 'product', 'products', 'herb', 'herbs', 'ayurvedic', 'ayurveda', 'ceylon',
        'mata', 'mama', 'oyala', 'thiyenawada', 'tiyenawada', 'monawada', 'mokakda', 'ekak', 'one', 'ona',
        'මට', 'මම', 'ඔබ', 'මොනවාද', 'තිබේද', 'ඇත', 'එකක්', 'සඳහා', 'ආයුර්වේද', 'ඖෂධ', 'ඖෂධීය',
    ];

    public static function tokens(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{M}\p{N}\x{200D}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique(array_filter(
            $words,
            fn($w) => mb_strlen($w) >= 3 && !in_array($w, self::STOP_WORDS, true)
        )));
    }

    /**
     * English and Singlish keywords must appear as whole words (a plural "s" is
     * allowed). Sinhala keywords match as stems, because endings are attached.
     */
    public static function mentions(string $text, string $keyword): bool
    {
        $text = mb_strtolower($text);
        $keyword = mb_strtolower(trim($keyword));
        if ($keyword === '') {
            return false;
        }

        if (preg_match('/^[\x20-\x7E]+$/', $keyword)) {
            return preg_match('/(?<![a-z0-9])' . preg_quote($keyword, '/') . 's?(?![a-z0-9])/', $text) === 1;
        }

        // At a word start, so that a short stem does not match inside another word
        return preg_match('/(?<![\p{L}\p{M}])' . preg_quote($keyword, '/') . '/u', $text) === 1;
    }

    /**
     * @return array ['concerns' => [], 'herbs' => [], 'products' => []] with in-stock products only.
     */
    public static function find(string $question, ?string $locale = null): array
    {
        $tokens = self::tokens($question);

        $concernIds = [];
        foreach (AssistantRepo::keywords() as $row) {
            if (self::mentions($question, $row['keyword'])) {
                $concernIds[(int)$row['concern_id']] = true;
            }
        }
        $concerns = array_values(array_filter(
            HerbRepo::getAllConcerns($locale),
            fn($c) => isset($concernIds[(int)$c['concern_id']])
        ));

        // Herbs named in the question come before herbs reached through a topic
        $herbs = [];
        foreach (HerbRepo::findAll(null, $locale) as $herb) {
            if (self::nameScore($tokens, $question, [$herb['name'], $herb['name_si'], $herb['scientific_name']]) > 0) {
                $herbs[(int)$herb['herb_id']] = $herb;
            }
        }
        $namedHerbIds = array_keys($herbs);
        foreach ($concerns as $concern) {
            foreach (HerbRepo::getHerbsByConcern((int)$concern['concern_id'], $locale) as $herb) {
                $herbs[(int)$herb['herb_id']] ??= $herb;
            }
        }

        $scores = [];
        $all = [];
        foreach (ProductRepo::findAll(null, $locale) as $product) {
            if ((int)$product['quantity'] <= 0) {
                continue;
            }
            $id = (int)$product['product_id'];
            $all[$id] = $product;
            $score = self::nameScore($tokens, $question, [$product['product_name'], $product['product_name_si'] ?? '']);
            if ($score > 0) {
                $scores[$id] = 10 * $score;
            }
        }
        foreach (array_keys($herbs) as $herbId) {
            $weight = in_array($herbId, $namedHerbIds, true) ? 5 : 1;
            foreach (HerbRepo::getProductsByHerb($herbId, $locale) as $product) {
                $id = (int)$product['product_id'];
                if (isset($all[$id])) {
                    $scores[$id] = ($scores[$id] ?? 0) + $weight;
                }
            }
        }

        uksort($scores, fn($a, $b) => ($scores[$b] <=> $scores[$a]) ?: ($a <=> $b));
        $products = [];
        foreach (array_slice(array_keys($scores), 0, self::MAX_PRODUCTS) as $id) {
            $products[] = $all[$id];
        }

        return [
            'concerns' => $concerns,
            'herbs' => array_slice(array_values($herbs), 0, self::MAX_HERBS),
            'products' => $products,
        ];
    }

    /** Published safety notes for the given herbs. */
    public static function safetyNotes(array $herbIds): array
    {
        if ($herbIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($herbIds), '?'));
        $stmt = Database::getConnection()->prepare("
            SELECT s.herb_id, s.severity, s.note_en, s.note_si, f.name_en AS condition_name
            FROM herb_safety s
            JOIN safety_flag f ON f.flag_id = s.flag_id
            WHERE s.published = 1 AND s.herb_id IN ({$placeholders})
            ORDER BY s.herb_id, f.flag_id
        ");
        $stmt->execute(array_map('intval', array_values($herbIds)));
        return $stmt->fetchAll();
    }

    private static function nameScore(array $tokens, string $question, array $names): int
    {
        $nameWords = [];
        foreach ($names as $name) {
            $nameWords = array_merge($nameWords, self::tokens((string)$name));
        }
        $nameWords = array_unique($nameWords);

        $score = count(array_intersect($tokens, $nameWords));
        // Sinhala names often carry endings, so also look for them inside the question
        foreach ($nameWords as $word) {
            if (!in_array($word, $tokens, true) && preg_match('/[^\x20-\x7E]/', $word) && self::mentions($question, $word)) {
                $score++;
            }
        }
        return $score;
    }
}
