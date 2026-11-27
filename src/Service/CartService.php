<?php

namespace App\Service;

use App\Core\Auth;
use App\Core\Database;
use App\Core\FormRules;
use App\Repository\ProductRepo;
use PDO;

/**
 * The shopping cart. A signed-in customer's cart is in the database; a visitor's
 * cart stays in the session and moves into the account at sign-in.
 */
class CartService
{
    private const SESSION = 'guest_cart';
    private const MAX_LINES = 50;

    /** @return array<int, int> product id => quantity */
    public static function quantities(): array
    {
        if (!Auth::check()) {
            return $_SESSION[self::SESSION] ?? [];
        }
        $stmt = Database::getConnection()->prepare(
            'SELECT product_id, SUM(quantity) FROM cart WHERE user_id = :uid GROUP BY product_id'
        );
        $stmt->execute([':uid' => Auth::id()]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    public static function productIds(): array
    {
        return array_map('intval', array_keys(self::quantities()));
    }

    /** @return array{items: array, subtotal: float} the cart priced as of now */
    public static function contents(): array
    {
        $items = [];
        $subtotal = 0.0;
        foreach (self::quantities() as $productId => $quantity) {
            $product = ProductRepo::findById((int)$productId);
            if (!$product) {
                continue;
            }
            $product['stock'] = (int)$product['quantity'];
            $product['quantity'] = $quantity;
            $product['line_total'] = round((float)$product['effective_price'] * $quantity, 2);
            $subtotal += $product['line_total'];
            $items[] = $product;
        }
        return ['items' => $items, 'subtotal' => $subtotal];
    }

    /** @return string|null why the product could not be added */
    public static function add(int $productId, string $quantity): ?string
    {
        $cart = self::quantities();
        if ($problem = FormRules::cartProblem($productId, $quantity, $cart[$productId] ?? 0)) {
            return $problem;
        }
        if (!isset($cart[$productId]) && count($cart) >= self::MAX_LINES) {
            return t('err_cart_full');
        }
        self::store($productId, ($cart[$productId] ?? 0) + (int)$quantity);
        return null;
    }

    /** @return string|null why the quantity could not be changed */
    public static function update(int $productId, string $quantity): ?string
    {
        if ($problem = FormRules::cartProblem($productId, $quantity)) {
            return $problem;
        }
        if (isset(self::quantities()[$productId])) {
            self::store($productId, (int)$quantity);
        }
        return null;
    }

    public static function remove(int $productId): void
    {
        if (!Auth::check()) {
            unset($_SESSION[self::SESSION][$productId]);
            return;
        }
        Database::getConnection()->prepare('DELETE FROM cart WHERE user_id = :uid AND product_id = :pid')
            ->execute([':uid' => Auth::id(), ':pid' => $productId]);
    }

    /** After sign-in: add what was chosen as a visitor, never beyond the stock. */
    public static function mergeGuestCart(): void
    {
        $guest = $_SESSION[self::SESSION] ?? [];
        if (!Auth::check() || $guest === []) {
            return;
        }
        unset($_SESSION[self::SESSION]);

        $cart = self::quantities();
        foreach ($guest as $productId => $quantity) {
            $product = ProductRepo::findById((int)$productId);
            if (!$product) {
                continue;
            }
            $total = min(($cart[$productId] ?? 0) + (int)$quantity, (int)$product['quantity'], FormRules::MAX_CART_QUANTITY);
            if ($total > 0) {
                self::store((int)$productId, $total);
            }
        }
    }

    /** Set the quantity of one product. */
    private static function store(int $productId, int $quantity): void
    {
        if (!Auth::check()) {
            $_SESSION[self::SESSION][$productId] = $quantity;
            return;
        }
        $pdo = Database::getConnection();
        $find = $pdo->prepare('SELECT cart_id FROM cart WHERE user_id = :uid AND product_id = :pid ORDER BY cart_id');
        $find->execute([':uid' => Auth::id(), ':pid' => $productId]);
        $ids = $find->fetchAll(PDO::FETCH_COLUMN);

        if (!$ids) {
            $pdo->prepare('INSERT INTO cart (user_id, product_id, quantity) VALUES (:uid, :pid, :qty)')
                ->execute([':uid' => Auth::id(), ':pid' => $productId, ':qty' => $quantity]);
            return;
        }
        $pdo->prepare('UPDATE cart SET quantity = :qty WHERE cart_id = :id')->execute([':qty' => $quantity, ':id' => $ids[0]]);
        // Older data may hold the same product twice
        foreach (array_slice($ids, 1) as $extra) {
            $pdo->prepare('DELETE FROM cart WHERE cart_id = :id')->execute([':id' => $extra]);
        }
    }
}
