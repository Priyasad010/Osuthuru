<?php

namespace App\Repository;

use App\Core\Database;
use PDO;

class AssistantRepo
{
    public const CHAT_RETENTION_DAYS = 30;
    public const CACHE_HOURS = 24;

    /** Reuse the visitor's chat session if it still exists, otherwise start one. */
    public static function ensureSession(?int $sessionId, ?int $userId, string $ipHash): int
    {
        $pdo = Database::getConnection();
        if ($sessionId) {
            $stmt = $pdo->prepare("SELECT 1 FROM chat_session WHERE session_id = :id");
            $stmt->execute([':id' => $sessionId]);
            if ($stmt->fetchColumn()) {
                return $sessionId;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO chat_session (user_id, ip_hash) VALUES (:user, :ip)");
        $stmt->execute([':user' => $userId, ':ip' => $ipHash]);
        return (int)$pdo->lastInsertId();
    }

    /** Questions sent from this address in the last hour. */
    public static function recentQuestionCount(string $ipHash): int
    {
        $stmt = Database::getConnection()->prepare("
            SELECT COUNT(*)
            FROM chat_message m
            JOIN chat_session s ON s.session_id = m.session_id
            WHERE s.ip_hash = :ip AND m.role = 'user' AND m.created_at >= (NOW() - INTERVAL 1 HOUR)
        ");
        $stmt->execute([':ip' => $ipHash]);
        return (int)$stmt->fetchColumn();
    }

    /** The last messages of a session, oldest first. */
    public static function history(int $sessionId, int $limit): array
    {
        $stmt = Database::getConnection()->prepare("
            SELECT role, content FROM chat_message
            WHERE session_id = :id
            ORDER BY message_id DESC
            LIMIT " . max(1, $limit));
        $stmt->execute([':id' => $sessionId]);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function addMessage(int $sessionId, string $role, string $content, bool $weak = false): void
    {
        $stmt = Database::getConnection()->prepare("
            INSERT INTO chat_message (session_id, role, content, weak_match) VALUES (:id, :role, :content, :weak)
        ");
        $stmt->execute([':id' => $sessionId, ':role' => $role, ':content' => $content, ':weak' => $weak ? 1 : 0]);
    }

    public static function cached(string $hash): ?array
    {
        $stmt = Database::getConnection()->prepare("
            SELECT answer_json FROM assistant_cache
            WHERE question_hash = :hash AND created_at >= (NOW() - INTERVAL " . self::CACHE_HOURS . " HOUR)
        ");
        $stmt->execute([':hash' => $hash]);
        $answer = json_decode((string)$stmt->fetchColumn(), true);
        return is_array($answer) ? $answer : null;
    }

    public static function cache(string $hash, array $answer): void
    {
        $stmt = Database::getConnection()->prepare("
            REPLACE INTO assistant_cache (question_hash, answer_json, created_at) VALUES (:hash, :answer, NOW())
        ");
        $stmt->execute([':hash' => $hash, ':answer' => json_encode($answer, JSON_UNESCAPED_UNICODE)]);
    }

    /** Delete chat older than the retention period and stale cached answers. */
    public static function purge(): void
    {
        $pdo = Database::getConnection();
        $days = self::CHAT_RETENTION_DAYS;
        $pdo->exec("DELETE FROM chat_message WHERE created_at < (NOW() - INTERVAL {$days} DAY)");
        $pdo->exec("
            DELETE FROM chat_session
            WHERE created_at < (NOW() - INTERVAL {$days} DAY)
              AND NOT EXISTS (SELECT 1 FROM chat_message m WHERE m.session_id = chat_session.session_id)
        ");
        $pdo->exec("DELETE FROM assistant_cache WHERE created_at < (NOW() - INTERVAL " . self::CACHE_HOURS . " HOUR)");
    }

    // ------------------------------------------------------------ admin

    public static function weakQuestions(int $limit = 100): array
    {
        return Database::getConnection()->query("
            SELECT message_id, content, created_at FROM chat_message
            WHERE role = 'user' AND weak_match = 1
            ORDER BY message_id DESC
            LIMIT " . max(1, $limit))->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function dismiss(int $messageId): void
    {
        $stmt = Database::getConnection()->prepare("UPDATE chat_message SET weak_match = 0 WHERE message_id = :id");
        $stmt->execute([':id' => $messageId]);
    }

    public static function keywords(): array
    {
        return Database::getConnection()
            ->query("SELECT * FROM concern_keyword ORDER BY concern_id, keyword")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function addKeyword(int $concernId, string $keyword): bool
    {
        $keyword = mb_strtolower(trim($keyword));
        if (mb_strlen($keyword) < 2 || mb_strlen($keyword) > 100) {
            return false;
        }

        // Inserts nothing for an unknown topic or a repeated word
        $stmt = Database::getConnection()->prepare("
            INSERT IGNORE INTO concern_keyword (concern_id, keyword)
            SELECT concern_id, :keyword FROM health_concern WHERE concern_id = :concern
        ");
        $stmt->execute([':keyword' => $keyword, ':concern' => $concernId]);
        return $stmt->rowCount() === 1;
    }

    public static function deleteKeyword(int $keywordId): void
    {
        $stmt = Database::getConnection()->prepare("DELETE FROM concern_keyword WHERE keyword_id = :id");
        $stmt->execute([':id' => $keywordId]);
    }
}
