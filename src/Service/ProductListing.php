<?php

namespace App\Service;

use App\Core\Pager;
use App\Repository\ProductRepo;

/**
 * Sorting, price filtering and paging of the shop's product list. The catalogue
 * is small, so this works on the loaded list; prices include current discounts.
 */
class ProductListing
{
    public const PER_PAGE = 12;
    public const SORTS = ['newest', 'price_asc', 'price_desc', 'name'];

    public static function browse(array $query, ?string $locale = null): array
    {
        $text = fn(string $k) => trim(is_string($query[$k] ?? null) ? $query[$k] : '');
        $category = ctype_digit($text('category')) ? (int)$text('category') : null;
        $sort = in_array($text('sort'), self::SORTS, true) ? $text('sort') : 'newest';
        $price = fn(string $k) => preg_match('/^\d{1,8}(\.\d{1,2})?$/', $text($k)) ? (float)$text($k) : null;
        $min = $price('min');
        $max = $price('max');
        // A range typed the wrong way round still means the same range
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }
        $inStock = $text('stock') === '1';

        $products = array_values(array_filter(
            ProductRepo::findAll($category, $locale),
            fn($p) => ($min === null || $p['effective_price'] >= $min)
                && ($max === null || $p['effective_price'] <= $max)
                && (!$inStock || (int)$p['quantity'] > 0)
        ));

        if ($sort !== 'newest') {
            usort($products, fn($a, $b) => match ($sort) {
                'price_asc' => $a['effective_price'] <=> $b['effective_price'],
                'price_desc' => $b['effective_price'] <=> $a['effective_price'],
                default => strcoll(mb_strtolower($a['display_name']), mb_strtolower($b['display_name'])),
            } ?: $b['product_id'] <=> $a['product_id']);
        }

        $pager = Pager::of(count($products), $query['page'] ?? 1, self::PER_PAGE);
        $filters = array_filter([
            'category' => $category !== null ? (string)$category : '',
            'sort' => $sort !== 'newest' ? $sort : '',
            'min' => $min !== null ? (string)$min : '',
            'max' => $max !== null ? (string)$max : '',
            'stock' => $inStock ? '1' : '',
        ], fn($v) => $v !== '');

        return [
            'products' => array_slice($products, $pager['offset'], self::PER_PAGE),
            'pager' => $pager,
            'filters' => $filters,
            'category' => $category,
            'sort' => $sort,
        ];
    }
}
