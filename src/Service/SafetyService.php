<?php

namespace App\Service;

use App\Core\Lang;
use App\Repository\SafetyRepo;

/**
 * Safety checker.
 * PRIVACY: a customer's answers are kept in the PHP session only. Never write
 * them to the database, logs, URLs or emails.
 */
class SafetyService
{
    private const SESSION_KEY = 'safety_answers';
    private const RANK = ['avoid' => 3, 'caution' => 2, 'info' => 1];

    /** Remember the ticked flag codes for this session; unknown codes are dropped. */
    public static function setAnswers(mixed $codes): array
    {
        $known = array_column(SafetyRepo::flags(), 'code');
        $selected = is_array($codes) ? array_values(array_intersect($known, array_filter($codes, 'is_string'))) : [];
        $_SESSION[self::SESSION_KEY] = $selected;
        return $selected;
    }

    /** Null when the customer has not used the checker. */
    public static function answers(): ?array
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    public static function clearAnswers(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * Match answers against published entries for every herb in the products.
     *
     * 'unknown' lists products whose safety information is missing or incomplete.
     * An empty 'warnings' list never means a product is safe.
     */
    public static function check(array $productIds, array $flagCodes, ?string $locale = null): array
    {
        $isSi = ($locale ?? Lang::getLocale()) === 'si';
        $pick = fn(?string $si, ?string $en): string => ($isSi && !empty($si)) ? $si : (string)$en;

        $warnings = [];
        $names = [];
        $herbHasData = [];

        foreach (SafetyRepo::publishedRowsForProducts($productIds) as $row) {
            $pid = (int)$row['product_id'];
            $names[$pid] = $pick($row['product_name_si'], $row['product_name']);
            $herbHasData[$pid] ??= [];

            if ($row['herb_id'] === null) {
                continue;
            }

            $hid = (int)$row['herb_id'];
            $herbHasData[$pid][$hid] = ($herbHasData[$pid][$hid] ?? false) || $row['severity'] !== null;

            if ($row['severity'] !== null && in_array($row['flag_code'], $flagCodes, true)) {
                $warnings[] = [
                    'product_id' => $pid,
                    'product_name' => $names[$pid],
                    'herb_name' => $pick($row['herb_name_si'], $row['herb_name']),
                    'flag_name' => $pick($row['flag_name_si'], $row['flag_name_en']),
                    'severity' => $row['severity'],
                    'severity_label' => t_or('severity_', $row['severity']),
                    'note' => $pick($row['note_si'], $row['note_en']),
                ];
            }
        }

        usort($warnings, fn($a, $b) => self::RANK[$b['severity']] <=> self::RANK[$a['severity']]);

        $unknown = [];
        foreach ($herbHasData as $pid => $herbs) {
            // No linked herbs, or at least one herb without any published entry
            if ($herbs === [] || in_array(false, $herbs, true)) {
                $unknown[] = ['product_id' => $pid, 'product_name' => $names[$pid]];
            }
        }

        return [
            'selected' => count($flagCodes),
            'warnings' => $warnings,
            'unknown' => $unknown,
            'has_avoid' => in_array('avoid', array_column($warnings, 'severity'), true),
        ];
    }

    /** Result for the session's answers, or null if the checker was not used. */
    public static function currentResult(array $productIds): ?array
    {
        $answers = self::answers();
        return $answers === null ? null : self::check($productIds, $answers);
    }

    /**
     * Checkout rule: an "avoid" match must be acknowledged before paying.
     */
    public static function needsAcknowledgement(array $productIds, bool $acknowledged): bool
    {
        $result = self::currentResult($productIds);
        return $result !== null && $result['has_avoid'] && !$acknowledged;
    }
}
