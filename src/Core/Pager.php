<?php

namespace App\Core;

/** Splitting long lists into pages. */
class Pager
{
    /** @return array{page: int, pages: int, per: int, total: int, offset: int} a page number that exists */
    public static function of(int $total, mixed $requested, int $perPage): array
    {
        $pages = max(1, (int)ceil($total / $perPage));
        $page = is_string($requested) && ctype_digit($requested) ? (int)$requested : (is_int($requested) ? $requested : 1);
        $page = min(max(1, $page), $pages);

        return ['page' => $page, 'pages' => $pages, 'per' => $perPage, 'total' => $total, 'offset' => ($page - 1) * $perPage];
    }

    /** The address of another page, keeping the filters. */
    public static function url(string $path, array $query, int $page): string
    {
        $query = array_filter($query, fn($v) => is_string($v) && $v !== '');
        unset($query['page']);
        if ($page > 1) {
            $query['page'] = (string)$page;
        }
        return $path . ($query ? '?' . http_build_query($query) : '');
    }
}
