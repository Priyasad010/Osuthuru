<?php

namespace App\Service;

use App\Core\Database;
use App\Core\Upload;
use PDO;
use PDOException;

class ReviewService
{
    public const MAX_PHOTOS = 3;
    private const MAX_COMMENT = 2000;
    private const DELIVERED = "SELECT 1 FROM orders o JOIN order_item i ON i.order_id = o.order_id
        WHERE o.user_id = %s AND i.product_id = %s AND o.status = 'Delivered'";

    public static function hasDeliveredOrder(int $userId, int $productId): bool
    {
        $stmt = Database::getConnection()->prepare(sprintf(self::DELIVERED, ':user', ':product') . ' LIMIT 1');
        $stmt->execute([':user' => $userId, ':product' => $productId]);
        return (bool)$stmt->fetchColumn();
    }

    /** As in V1: a customer reviews a product once, after it has been delivered. */
    public static function canReview(int $userId, int $productId): bool
    {
        $stmt = Database::getConnection()->prepare('SELECT 1 FROM review WHERE user_id = :user AND product_id = :product');
        $stmt->execute([':user' => $userId, ':product' => $productId]);
        return !$stmt->fetchColumn() && self::hasDeliveredOrder($userId, $productId);
    }

    /** Turn a "photos[]" upload field into one array per chosen file. */
    public static function files(array $field): array
    {
        $files = [];
        foreach ((array)($field['error'] ?? []) as $i => $error) {
            if ($error !== UPLOAD_ERR_NO_FILE) {
                $files[] = [
                    'name' => ((array)$field['name'])[$i] ?? '',
                    'tmp_name' => ((array)$field['tmp_name'])[$i] ?? '',
                    'size' => ((array)$field['size'])[$i] ?? 0,
                    'error' => $error,
                ];
            }
        }
        return $files;
    }

    public static function add(int $userId, int $productId, mixed $rating, mixed $comment, array $files = []): array
    {
        $rating = is_scalar($rating) ? (int)$rating : 0;
        $comment = is_string($comment) ? trim($comment) : '';

        if (!self::canReview($userId, $productId)) {
            return ['ok' => false, 'error' => t('review_err_not_allowed')];
        }
        if ($rating < 1 || $rating > 5 || $comment === '' || mb_strlen($comment) > self::MAX_COMMENT) {
            return ['ok' => false, 'error' => t('review_err_invalid')];
        }
        if (count($files) > self::MAX_PHOTOS) {
            return ['ok' => false, 'error' => t('review_err_photos', ['max' => self::MAX_PHOTOS])];
        }

        $paths = [];
        foreach ($files as $file) {
            $upload = Upload::process($file, 'reviews');
            if (!$upload['ok']) {
                self::removeFiles($paths);
                return $upload;
            }
            $paths[] = $upload['path'];
        }

        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO review (user_id, product_id, rating, comment, review_date) VALUES (:user, :product, :rating, :comment, NOW())');
            $stmt->execute([':user' => $userId, ':product' => $productId, ':rating' => $rating, ':comment' => $comment]);
            $id = (int)$pdo->lastInsertId();
            $image = $pdo->prepare('INSERT INTO review_image (review_id, file_path) VALUES (:id, :path)');
            foreach ($paths as $path) {
                $image->execute([':id' => $id, ':path' => $path]);
            }
            $pdo->commit();
        } catch (PDOException) {
            $pdo->rollBack();
            self::removeFiles($paths);
            return ['ok' => false, 'error' => t('review_err_not_allowed')];
        }

        return ['ok' => true, 'review_id' => $id];
    }

    /** Reviews of a product, most helpful first. */
    public static function forProduct(int $productId, ?int $viewerId = null): array
    {
        $pdo = Database::getConnection();
        $verified = sprintf(self::DELIVERED, 'r.user_id', 'r.product_id');
        $stmt = $pdo->prepare("
            SELECT r.review_id, r.user_id, r.rating, r.comment, r.review_date, u.name AS user_name,
                   EXISTS ({$verified}) AS verified,
                   (SELECT COUNT(*) FROM review_helpful h WHERE h.review_id = r.review_id) AS helpful,
                   EXISTS (SELECT 1 FROM review_helpful h WHERE h.review_id = r.review_id AND h.user_id = :viewer) AS voted
            FROM review r
            LEFT JOIN `user` u ON u.user_id = r.user_id
            WHERE r.product_id = :product
            ORDER BY helpful DESC, r.review_date DESC, r.review_id DESC
            LIMIT 100
        ");
        $stmt->execute([':product' => $productId, ':viewer' => (int)$viewerId]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $images = $pdo->prepare('SELECT file_path FROM review_image WHERE review_id = :id ORDER BY image_id');
        foreach ($reviews as &$review) {
            $images->execute([':id' => $review['review_id']]);
            $review['photos'] = $images->fetchAll(PDO::FETCH_COLUMN);
        }
        unset($review);
        return $reviews;
    }

    /** Add or take back a "helpful" vote. Authors cannot vote on their own review. */
    public static function toggleHelpful(int $reviewId, int $userId): ?int
    {
        $pdo = Database::getConnection();
        $find = $pdo->prepare('SELECT product_id, user_id FROM review WHERE review_id = :id');
        $find->execute([':id' => $reviewId]);
        $review = $find->fetch(PDO::FETCH_ASSOC);
        if (!$review) {
            return null;
        }
        if ((int)$review['user_id'] !== $userId) {
            $delete = $pdo->prepare('DELETE FROM review_helpful WHERE review_id = :id AND user_id = :user');
            $delete->execute([':id' => $reviewId, ':user' => $userId]);
            if ($delete->rowCount() === 0) {
                $pdo->prepare('INSERT IGNORE INTO review_helpful (review_id, user_id) VALUES (:id, :user)')
                    ->execute([':id' => $reviewId, ':user' => $userId]);
            }
        }
        return (int)$review['product_id'];
    }

    /** Newest reviews for moderation. */
    public static function latest(int $limit = 100): array
    {
        return Database::getConnection()->query('
            SELECT r.*, u.name AS user_name, p.product_name
            FROM review r
            LEFT JOIN `user` u ON u.user_id = r.user_id
            LEFT JOIN product p ON p.product_id = r.product_id
            ORDER BY r.review_id DESC LIMIT ' . max(1, $limit))->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function delete(int $reviewId): void
    {
        $pdo = Database::getConnection();
        $images = $pdo->prepare('SELECT file_path FROM review_image WHERE review_id = :id');
        $images->execute([':id' => $reviewId]);
        $paths = $images->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare('DELETE FROM review WHERE review_id = :id')->execute([':id' => $reviewId]);
        self::removeFiles($paths);
    }

    private static function removeFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (image_url($path) !== null) {
                @unlink(dirname(__DIR__, 2) . '/public' . $path);
            }
        }
    }
}
