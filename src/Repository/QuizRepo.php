<?php

namespace App\Repository;

use App\Core\Database;
use App\Core\Lang;
use App\Service\DoshaService;
use PDO;

class QuizRepo
{
    public const MIN_OPTIONS = 3;
    public const MAX_OPTIONS = 4;
    private const MAX_POINTS = 5;

    // ------------------------------------------------------------ questions

    /** All questions in order, each with its options and display text. */
    public static function questions(?string $locale = null): array
    {
        $pdo = Database::getConnection();
        $isSi = ($locale ?? Lang::getLocale()) === 'si';
        $display = function (array $row) use ($isSi): array {
            $row['display_text'] = ($isSi && $row['text_si'] !== '') ? $row['text_si'] : $row['text_en'];
            return $row;
        };

        $questions = [];
        foreach ($pdo->query("SELECT * FROM quiz_question ORDER BY sort_order, question_id")->fetchAll(PDO::FETCH_ASSOC) as $q) {
            $questions[(int)$q['question_id']] = $display($q) + ['options' => []];
        }
        foreach ($pdo->query("SELECT * FROM quiz_option ORDER BY option_id")->fetchAll(PDO::FETCH_ASSOC) as $o) {
            if (isset($questions[(int)$o['question_id']])) {
                $questions[(int)$o['question_id']]['options'][] = $display($o);
            }
        }

        return array_values($questions);
    }

    /** Reasons the quiz cannot be shown to customers; empty when it is complete. */
    public static function problems(array $questions): array
    {
        if ($questions === []) {
            return [t('quiz_problem_empty')];
        }

        $problems = [];
        foreach ($questions as $number => $q) {
            $count = count($q['options']);
            if ($count < self::MIN_OPTIONS || $count > self::MAX_OPTIONS) {
                $problems[] = t('quiz_problem_options', ['number' => $number + 1]);
            }
            foreach ($q['options'] as $o) {
                if ((int)$o['vata'] + (int)$o['pitta'] + (int)$o['kapha'] === 0) {
                    $problems[] = t('quiz_problem_points', ['number' => $number + 1]);
                    break;
                }
            }
        }

        return $problems;
    }

    public static function isPublished(): bool
    {
        return (int)Database::getConnection()->query("SELECT published FROM quiz_setting WHERE id = 1")->fetchColumn() === 1;
    }

    /** Published and still complete after any later edits. */
    public static function isLive(): bool
    {
        return self::isPublished() && self::problems(self::questions()) === [];
    }

    public static function setPublished(bool $published): array
    {
        if ($published) {
            $problems = self::problems(self::questions());
            if ($problems !== []) {
                return ['ok' => false, 'error' => implode(' ', $problems)];
            }
        }

        $stmt = Database::getConnection()->prepare("UPDATE quiz_setting SET published = :p WHERE id = 1");
        $stmt->execute([':p' => $published ? 1 : 0]);
        return ['ok' => true];
    }

    /** Create (id 0) or update a question. */
    public static function saveQuestion(int $id, string $textEn, string $textSi, int $sortOrder): bool
    {
        if (trim($textEn) === '') {
            return false;
        }

        $pdo = Database::getConnection();
        $values = [':en' => trim($textEn), ':si' => trim($textSi), ':sort' => $sortOrder];

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE quiz_question SET text_en = :en, text_si = :si, sort_order = :sort WHERE question_id = :id");
            return $stmt->execute($values + [':id' => $id]);
        }

        $stmt = $pdo->prepare("INSERT INTO quiz_question (text_en, text_si, sort_order) VALUES (:en, :si, :sort)");
        return $stmt->execute($values);
    }

    public static function deleteQuestion(int $id): void
    {
        $stmt = Database::getConnection()->prepare("DELETE FROM quiz_question WHERE question_id = :id");
        $stmt->execute([':id' => $id]);
    }

    /** Create (id 0) or update an option. */
    public static function saveOption(int $id, int $questionId, string $textEn, string $textSi, array $points): bool
    {
        if (trim($textEn) === '') {
            return false;
        }

        $pdo = Database::getConnection();
        $values = [':en' => trim($textEn), ':si' => trim($textSi)];
        foreach (DoshaService::DOSHAS as $dosha) {
            $values[":{$dosha}"] = max(0, min(self::MAX_POINTS, (int)($points[$dosha] ?? 0)));
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE quiz_option SET text_en = :en, text_si = :si, vata = :vata, pitta = :pitta, kapha = :kapha WHERE option_id = :id");
            return $stmt->execute($values + [':id' => $id]);
        }

        $count = $pdo->prepare("SELECT COUNT(*) FROM quiz_option WHERE question_id = :q");
        $count->execute([':q' => $questionId]);
        $exists = $pdo->prepare("SELECT 1 FROM quiz_question WHERE question_id = :q");
        $exists->execute([':q' => $questionId]);
        if (!$exists->fetchColumn() || (int)$count->fetchColumn() >= self::MAX_OPTIONS) {
            return false;
        }

        $stmt = $pdo->prepare("INSERT INTO quiz_option (question_id, text_en, text_si, vata, pitta, kapha) VALUES (:q, :en, :si, :vata, :pitta, :kapha)");
        return $stmt->execute($values + [':q' => $questionId]);
    }

    public static function deleteOption(int $id): void
    {
        $stmt = Database::getConnection()->prepare("DELETE FROM quiz_option WHERE option_id = :id");
        $stmt->execute([':id' => $id]);
    }

    // ------------------------------------------------------------ profiles

    /** Profiles keyed by dosha, with display text in the current language. */
    public static function profiles(?string $locale = null): array
    {
        $isSi = ($locale ?? Lang::getLocale()) === 'si';
        $rows = Database::getConnection()->query("SELECT * FROM dosha_profile ORDER BY dosha")->fetchAll(PDO::FETCH_ASSOC);

        $profiles = [];
        foreach ($rows as $p) {
            $p['display_description'] = ($isSi && !empty($p['description_si'])) ? $p['description_si'] : ($p['description_en'] ?? '');
            $p['display_tips'] = ($isSi && !empty($p['tips_si'])) ? $p['tips_si'] : ($p['tips_en'] ?? '');
            $profiles[$p['dosha']] = $p;
        }
        return $profiles;
    }

    public static function saveProfile(string $dosha, array $data): bool
    {
        if (!in_array($dosha, DoshaService::DOSHAS, true)) {
            return false;
        }

        $values = [':dosha' => $dosha];
        foreach (['description_en', 'description_si', 'tips_en', 'tips_si'] as $field) {
            $text = trim((string)($data[$field] ?? ''));
            $values[":{$field}"] = $text !== '' ? $text : null;
        }

        $stmt = Database::getConnection()->prepare("
            UPDATE dosha_profile
            SET description_en = :description_en, description_si = :description_si, tips_en = :tips_en, tips_si = :tips_si
            WHERE dosha = :dosha
        ");
        return $stmt->execute($values);
    }

    // ------------------------------------------------------------ results

    /** Save scores for a logged-in user. Saving the same attempt twice keeps one row. */
    public static function saveResult(int $userId, array $result): void
    {
        $stmt = Database::getConnection()->prepare("
            INSERT IGNORE INTO quiz_result (user_id, session_token, vata, pitta, kapha, dominant)
            VALUES (:user, :token, :vata, :pitta, :kapha, :dominant)
        ");
        $stmt->execute([
            ':user' => $userId,
            ':token' => $result['token'],
            ':vata' => $result['vata'],
            ':pitta' => $result['pitta'],
            ':kapha' => $result['kapha'],
            ':dominant' => $result['dominant'],
        ]);
    }

    /** Newest first. */
    public static function history(int $userId, int $limit = 10): array
    {
        $stmt = Database::getConnection()->prepare("SELECT * FROM quiz_result WHERE user_id = :user ORDER BY result_id DESC LIMIT " . max(1, $limit));
        $stmt->execute([':user' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function isSaved(int $userId, string $token): bool
    {
        $stmt = Database::getConnection()->prepare("SELECT 1 FROM quiz_result WHERE user_id = :user AND session_token = :token");
        $stmt->execute([':user' => $userId, ':token' => $token]);
        return (bool)$stmt->fetchColumn();
    }

    // ------------------------------------------------------------ products

    /** product_id => [dosha => strength] */
    public static function productStrengths(): array
    {
        $rows = Database::getConnection()->query("SELECT * FROM product_dosha")->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['product_id']][$row['dosha']] = (int)$row['strength'];
        }
        return $map;
    }

    /** Replace a product's mapping. Strength 0 means not mapped. */
    public static function saveProductStrengths(int $productId, array $strengths): bool
    {
        if (!ProductRepo::findById($productId)) {
            return false;
        }

        $pdo = Database::getConnection();
        // Join the caller's transaction if there is one
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }
        $delete = $pdo->prepare("DELETE FROM product_dosha WHERE product_id = :id");
        $delete->execute([':id' => $productId]);

        $insert = $pdo->prepare("INSERT INTO product_dosha (product_id, dosha, strength) VALUES (:id, :dosha, :strength)");
        foreach (DoshaService::DOSHAS as $dosha) {
            $strength = (int)($strengths[$dosha] ?? 0);
            if ($strength >= 1 && $strength <= 3) {
                $insert->execute([':id' => $productId, ':dosha' => $dosha, ':strength' => $strength]);
            }
        }
        if ($ownTransaction) {
            $pdo->commit();
        }
        return true;
    }

    /**
     * In-stock products ranked by (dosha percentage x product strength).
     */
    public static function recommendedProducts(array $scores, int $limit = 4, ?string $locale = null): array
    {
        $stmt = Database::getConnection()->prepare("
            SELECT p.*, c.name AS category_name, c.name_si AS category_name_si,
                   (SELECT MAX(d.discount_percentage)
                    FROM discount d
                    WHERE d.product_id = p.product_id
                      AND (d.start_date IS NULL OR d.start_date <= NOW())
                      AND (d.end_date IS NULL OR d.end_date >= NOW())
                   ) AS active_discount_percentage
            FROM product p
            JOIN (
                SELECT product_id,
                       SUM(strength * CASE dosha WHEN 'vata' THEN :vata WHEN 'pitta' THEN :pitta ELSE :kapha END) AS score
                FROM product_dosha
                GROUP BY product_id
            ) ranked ON ranked.product_id = p.product_id
            LEFT JOIN category c ON c.category_id = p.category_id
            WHERE p.quantity > 0 AND ranked.score > 0
            ORDER BY ranked.score DESC, p.product_id ASC
            LIMIT " . max(1, $limit));
        $stmt->execute([
            ':vata' => (int)$scores['vata'],
            ':pitta' => (int)$scores['pitta'],
            ':kapha' => (int)$scores['kapha'],
        ]);

        return array_map(
            fn($p) => ProductRepo::enrichWithSalePrice(ProductRepo::enrichLocale($p, $locale)),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}
