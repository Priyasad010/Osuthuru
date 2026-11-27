<?php

namespace App\Repository;

use App\Core\Database;
use App\Core\Lang;
use PDO;
use PDOException;

class BlogRepo
{
    private static function enrich(array $post, ?string $locale = null): array
    {
        $si = ($locale ?? Lang::getLocale()) === 'si';
        $post['display_title'] = ($si && !empty($post['title_si'])) ? $post['title_si'] : $post['title'];
        $post['display_content'] = ($si && !empty($post['content_si'])) ? $post['content_si'] : $post['content'];
        return $post;
    }

    /** Newest first, optionally only one tag. */
    public static function all(?int $tagId = null, int $limit = 100, ?string $locale = null): array
    {
        $sql = 'SELECT b.* FROM blog b';
        $params = [];
        if ($tagId) {
            $sql .= ' JOIN blog_tag_map m ON m.blog_id = b.blog_id AND m.tag_id = :tag';
            $params[':tag'] = $tagId;
        }
        $stmt = Database::getConnection()->prepare($sql . ' ORDER BY b.published_date DESC, b.blog_id DESC LIMIT ' . max(1, $limit));
        $stmt->execute($params);
        return array_map(fn($p) => self::enrich($p, $locale), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** A post with its tags and related products (in stock or not). */
    public static function find(int $blogId, ?string $locale = null): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM blog WHERE blog_id = :id');
        $stmt->execute([':id' => $blogId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$post) {
            return null;
        }

        $tags = $pdo->prepare('SELECT t.* FROM blog_tag t JOIN blog_tag_map m ON m.tag_id = t.tag_id WHERE m.blog_id = :id ORDER BY t.name_en');
        $tags->execute([':id' => $blogId]);
        $products = $pdo->prepare('SELECT product_id FROM blog_product WHERE blog_id = :id ORDER BY product_id');
        $products->execute([':id' => $blogId]);

        $post = self::enrich($post, $locale);
        $post['tags'] = array_map(fn($t) => self::tagName($t, $locale), $tags->fetchAll(PDO::FETCH_ASSOC));
        $post['product_ids'] = array_map('intval', $products->fetchAll(PDO::FETCH_COLUMN));
        $post['products'] = array_values(array_filter(array_map(
            fn($id) => ProductRepo::findById($id, $locale),
            $post['product_ids']
        )));
        return $post;
    }

    private static function tagName(array $tag, ?string $locale = null): array
    {
        $si = ($locale ?? Lang::getLocale()) === 'si';
        $tag['display_name'] = ($si && $tag['name_si'] !== '') ? $tag['name_si'] : $tag['name_en'];
        return $tag;
    }

    public static function tags(?string $locale = null): array
    {
        $rows = Database::getConnection()->query('SELECT * FROM blog_tag ORDER BY name_en')->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($t) => self::tagName($t, $locale), $rows);
    }

    /** Create (id 0) or update a post with its tags and related products. */
    public static function save(int $id, array $data): array
    {
        $text = fn(string $k) => trim(is_string($data[$k] ?? null) ? $data[$k] : '');
        if ($text('title') === '' || $text('content') === '' || mb_strlen($text('title')) > 255) {
            return ['ok' => false, 'error' => t('blog_err_required')];
        }
        if ($id > 0 && !self::find($id)) {
            return ['ok' => false, 'error' => t('blog_err_missing')];
        }

        $pdo = Database::getConnection();
        $values = [
            ':title' => $text('title'),
            ':title_si' => mb_substr($text('title_si'), 0, 255) ?: null,
            ':content' => $text('content'),
            ':content_si' => $text('content_si') ?: null,
        ];
        if ($id > 0) {
            $pdo->prepare('UPDATE blog SET title = :title, title_si = :title_si, content = :content, content_si = :content_si WHERE blog_id = :id')
                ->execute($values + [':id' => $id]);
        } else {
            $pdo->prepare('INSERT INTO blog (title, title_si, content, content_si, published_date) VALUES (:title, :title_si, :content, :content_si, NOW())')
                ->execute($values);
            $id = (int)$pdo->lastInsertId();
        }
        if (!empty($data['image'])) {
            $pdo->prepare('UPDATE blog SET image = :image WHERE blog_id = :id')->execute([':image' => $data['image'], ':id' => $id]);
        }

        foreach ([['blog_tag_map', 'tag_id', 'blog_tag', 'tag_ids'], ['blog_product', 'product_id', 'product', 'product_ids']] as [$map, $column, $source, $key]) {
            $pdo->prepare("DELETE FROM {$map} WHERE blog_id = :id")->execute([':id' => $id]);
            $insert = $pdo->prepare("INSERT IGNORE INTO {$map} (blog_id, {$column}) SELECT :blog, {$column} FROM {$source} WHERE {$column} = :value");
            foreach (is_array($data[$key] ?? null) ? $data[$key] : [] as $value) {
                $insert->execute([':blog' => $id, ':value' => (int)$value]);
            }
        }

        return ['ok' => true, 'id' => $id];
    }

    public static function delete(int $id): void
    {
        Database::getConnection()->prepare('DELETE FROM blog WHERE blog_id = :id')->execute([':id' => $id]);
    }

    public static function addTag(string $nameEn, string $nameSi): array
    {
        $nameEn = trim($nameEn);
        if ($nameEn === '' || mb_strlen($nameEn) > 80) {
            return ['ok' => false, 'error' => t('blog_err_tag')];
        }
        try {
            Database::getConnection()->prepare('INSERT INTO blog_tag (name_en, name_si) VALUES (:en, :si)')
                ->execute([':en' => $nameEn, ':si' => mb_substr(trim($nameSi), 0, 80)]);
        } catch (PDOException) {
            return ['ok' => false, 'error' => t('blog_err_tag')];
        }
        return ['ok' => true];
    }

    public static function deleteTag(int $id): void
    {
        Database::getConnection()->prepare('DELETE FROM blog_tag WHERE tag_id = :id')->execute([':id' => $id]);
    }
}
