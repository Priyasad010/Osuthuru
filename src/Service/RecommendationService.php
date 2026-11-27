<?php

namespace App\Service;

use App\Repository\QuizRepo;
use App\Repository\RecommendationRepo;

/**
 * Recommendations from the ML service, with SQL fallbacks.
 */
class RecommendationService
{
    /**
     * @param int|null   $userId       Signed-in customer, or null for a guest.
     * @param array|null $sessionDosha Quiz scores that exist only in this visit.
     * @return array ['products' => cards, 'source' => 'ml'|'fallback']
     */
    public static function forUser(?int $userId, ?array $sessionDosha = null, int $limit = 8, ?MlClient $client = null): array
    {
        $client ??= MlClient::fromEnv();
        $saved = $userId ? (QuizRepo::history($userId, 1)[0] ?? null) : null;
        // Scores the service cannot see: a guest's quiz, or one not saved yet
        $unsaved = $saved ? null : $sessionDosha;

        $data = $client->get('/recommend/user/' . (int)$userId . '?limit=' . $limit);
        $ids = $data['product_ids'] ?? null;
        $source = 'ml';

        if (!is_array($ids) || $ids === []) {
            $source = 'fallback';
            $categories = $userId ? RecommendationRepo::userCategories($userId) : [];
            $ids = RecommendationRepo::popular($categories, $limit);
            if ($saved) {
                $ids = array_merge(self::doshaIds($saved, $limit), $ids);
            }
        }
        if ($unsaved) {
            $ids = array_merge(self::doshaIds($unsaved, $limit), $ids);
        }

        $products = RecommendationRepo::cards($ids, $limit);
        // Sold-out suggestions can leave gaps
        if (count($products) < $limit) {
            $more = RecommendationRepo::popular([], $limit, array_column($products, 'product_id'));
            $products = array_merge($products, RecommendationRepo::cards($more, $limit - count($products)));
        }

        return ['products' => $products, 'source' => $source];
    }

    /** @return array ['similar' => cards, 'also_bought' => cards, 'source' => 'ml'|'fallback'] */
    public static function forProduct(array $product, int $limit = 4, ?MlClient $client = null): array
    {
        $client ??= MlClient::fromEnv();
        $id = (int)$product['product_id'];
        $data = $client->get('/recommend/product/' . $id . '?limit=' . max($limit, 6));
        $source = 'ml';

        if (!is_array($data['similar'] ?? null) || !is_array($data['also_bought'] ?? null)) {
            $source = 'fallback';
            $category = (int)($product['category_id'] ?? 0);
            $data = [
                'similar' => RecommendationRepo::popular($category ? [$category] : [], $limit, [$id]),
                'also_bought' => RecommendationRepo::boughtWith($id, $limit),
            ];
        }

        $without = fn(array $ids) => array_diff(array_map('intval', $ids), [$id]);
        return [
            'similar' => RecommendationRepo::cards($without($data['similar']), $limit),
            'also_bought' => RecommendationRepo::cards($without($data['also_bought']), $limit),
            'source' => $source,
        ];
    }

    /** The fields of a product that the public API returns. */
    public static function toApi(array $cards): array
    {
        return array_map(fn($p) => [
            'id' => (int)$p['product_id'],
            'name' => $p['display_name'],
            'price' => (float)$p['effective_price'],
            'url' => '/products/' . (int)$p['product_id'],
        ], $cards);
    }

    private static function doshaIds(array $scores, int $limit): array
    {
        return array_map('intval', array_column(QuizRepo::recommendedProducts($scores, $limit), 'product_id'));
    }
}
