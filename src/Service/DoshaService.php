<?php

namespace App\Service;

/**
 * Pure scoring rules for the dosha quiz.
 */
class DoshaService
{
    public const DOSHAS = ['vata', 'pitta', 'kapha'];

    /** The top two doshas form a combined type when this close (percentage points). */
    private const DUAL_MARGIN = 10;

    /**
     * @param array $questions Questions with their 'options' (option_id, vata, pitta, kapha).
     * @param array $answers   question_id => option_id
     * @return array ['ok' => true, 'result' => [...]] or ['ok' => false, 'error' => string]
     */
    public static function score(array $questions, array $answers): array
    {
        $points = array_fill_keys(self::DOSHAS, 0);

        foreach ($questions as $question) {
            $chosen = $answers[(int)$question['question_id']] ?? null;
            $option = null;
            foreach ($question['options'] as $candidate) {
                if (is_scalar($chosen) && (int)$candidate['option_id'] === (int)$chosen) {
                    $option = $candidate;
                    break;
                }
            }

            // Every question needs one of its own options
            if ($option === null) {
                return ['ok' => false, 'error' => t('quiz_err_incomplete')];
            }

            foreach (self::DOSHAS as $dosha) {
                $points[$dosha] += (int)$option[$dosha];
            }
        }

        if (array_sum($points) <= 0) {
            return ['ok' => false, 'error' => t('quiz_err_unavailable')];
        }

        $percent = self::percentages($points);

        return ['ok' => true, 'result' => $percent + ['dominant' => self::dominant($percent)]];
    }

    /**
     * Whole percentages that always add up to 100 (largest remainder method).
     */
    public static function percentages(array $points): array
    {
        $total = array_sum($points);
        $percent = [];
        $remainders = [];

        foreach (self::DOSHAS as $dosha) {
            $value = $points[$dosha] * 100;
            $percent[$dosha] = intdiv($value, $total);
            $remainders[$dosha] = $value % $total;
        }

        // arsort is stable, so equal remainders keep the vata, pitta, kapha order
        arsort($remainders);
        $missing = 100 - array_sum($percent);
        foreach (array_keys($remainders) as $dosha) {
            if ($missing <= 0) {
                break;
            }
            $percent[$dosha]++;
            $missing--;
        }

        return $percent;
    }

    /**
     * "pitta", or a two-dosha type such as "vata-pitta".
     */
    public static function dominant(array $percent): string
    {
        $ranked = [];
        foreach (self::DOSHAS as $dosha) {
            $ranked[$dosha] = (int)$percent[$dosha];
        }
        arsort($ranked);
        [$first, $second] = array_keys($ranked);

        if ($ranked[$first] - $ranked[$second] > self::DUAL_MARGIN) {
            return $first;
        }

        return implode('-', array_values(array_intersect(self::DOSHAS, [$first, $second])));
    }

    public static function label(string $dominant): string
    {
        return implode(' – ', array_map(fn($d) => t_or('dosha_', $d), explode('-', $dominant)));
    }
}
