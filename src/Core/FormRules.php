<?php

namespace App\Core;

use App\Repository\ProductRepo;
use App\Service\DeliveryZoneService;
use DateTimeImmutable;

/**
 * The rules of the forms, checked on the server. public/assets/js/forms.js shows
 * the same rules in the browser, but only these decide what is accepted.
 *
 * Each form method returns ['field' => 'message', ...]; an empty array means valid.
 */
class FormRules
{
    public const MAX_CART_QUANTITY = 99;

    /** A Sri Lankan phone number as 0XXXXXXXXX, or null. Spaces and dashes are allowed. */
    public static function phone(string $value): ?string
    {
        $digits = (string)preg_replace('/[\s\-()]/', '', trim($value));
        $digits = (string)preg_replace('/^(\+94|0094|94)(?=\d{9}$)/', '0', $digits);

        return preg_match('/^0[1-9]\d{8}$/', $digits) ? $digits : null;
    }

    /** Letters of any language (with Sinhala joiners), spaces, dots, apostrophes and hyphens. */
    public static function isPersonName(string $value): bool
    {
        $value = trim($value);

        return mb_strlen($value) >= 2 && mb_strlen($value) <= 100
            && preg_match('/^\p{L}[\p{L}\p{M}\x{200C}\x{200D} .\'’\-]*$/u', $value) === 1;
    }

    public static function isEmail(string $value): bool
    {
        $value = trim($value);

        return mb_strlen($value) <= 100 && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** The message for a new password that is too weak, or null. bcrypt reads 72 bytes at most. */
    public static function passwordProblem(string $password): ?string
    {
        if (mb_strlen($password) < 8) {
            return t('err_password_short');
        }
        if (strlen($password) > 72) {
            return t('err_password_long');
        }
        if (!preg_match('/\p{L}/u', $password) || !preg_match('/\d/', $password)) {
            return t('err_password_weak');
        }
        return null;
    }

    public static function registration(array $data): array
    {
        $get = self::reader($data);
        $errors = [];

        if (!self::isPersonName($get('name'))) {
            $errors['name'] = t('err_name_invalid');
        }
        if (!self::isEmail($get('email'))) {
            $errors['email'] = t('val_email');
        }
        if ($get('phone') !== '' && self::phone($get('phone')) === null) {
            $errors['phone'] = t('err_phone_invalid');
        }
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';
        if ($problem = self::passwordProblem($password)) {
            $errors['password'] = $problem;
        } elseif ($password !== ($data['password_confirmation'] ?? null)) {
            $errors['password_confirmation'] = t('err_password_mismatch');
        }
        return $errors;
    }

    /** Delivery details at checkout. */
    public static function shipping(array $data): array
    {
        $get = self::reader($data);
        $errors = [];

        if (!self::isPersonName($get('shipping_name'))) {
            $errors['shipping_name'] = t('err_name_invalid');
        }
        if (self::phone($get('shipping_phone')) === null) {
            $errors['shipping_phone'] = t('err_phone_invalid');
        }
        $length = mb_strlen($get('shipping_address'));
        if ($length < 10 || $length > 500) {
            $errors['shipping_address'] = t('err_address_invalid');
        }
        if (DeliveryZoneService::findByDistrict($get('district')) === null) {
            $errors['district'] = t('err_district_required');
        }
        return $errors;
    }

    public static function coupon(array $data): array
    {
        $get = self::reader($data);
        $errors = [];
        $code = strtoupper($get('code'));

        if (!preg_match('/^[A-Z0-9_-]{3,30}$/', $code)) {
            $errors['code'] = t('err_coupon_code');
        } else {
            $taken = Database::getConnection()->prepare('SELECT 1 FROM coupon WHERE UPPER(code) = :code');
            $taken->execute([':code' => $code]);
            if ($taken->fetchColumn()) {
                $errors['code'] = t('err_coupon_exists');
            }
        }

        $type = $get('type');
        $value = self::number($get('value'), 0.01, $type === 'percent' ? 90 : 1000000);
        if (!in_array($type, ['percent', 'fixed'], true) || $value === null) {
            $errors['value'] = t('err_coupon_value');
        }
        if (self::number($get('min_order') === '' ? '0' : $get('min_order'), 0, 10000000) === null) {
            $errors['min_order'] = t('err_amount');
        }
        if (self::whole($get('max_uses'), 1, 1000000) === null) {
            $errors['max_uses'] = t('err_whole', ['min' => 1, 'max' => 1000000]);
        }
        $ends = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $get('ends_at'));
        if (!$ends || $ends->format('Y-m-d\TH:i') !== $get('ends_at') || $ends <= new DateTimeImmutable()) {
            $errors['ends_at'] = t('err_future');
        }
        return $errors;
    }

    public static function deliveryZone(array $data): array
    {
        $get = self::reader($data);
        $errors = [];

        if (!preg_match('/^\p{L}[\p{L}\p{M}\x{200D} .\-]{1,99}$/u', $get('district'))) {
            $errors['district'] = t('err_district_name');
        }
        if (self::number($get('fee'), 0, 100000) === null) {
            $errors['fee'] = t('err_amount');
        }
        if (self::number($get('free_over'), 0, 10000000) === null) {
            $errors['free_over'] = t('err_amount');
        }
        return $errors;
    }

    /**
     * Check a wanted cart quantity against the shop.
     * @param int $alreadyInCart pieces of the product the cart holds and that stay
     * @return string|null the problem, or null
     */
    public static function cartProblem(int $productId, string $quantity, int $alreadyInCart = 0): ?string
    {
        $product = ProductRepo::findById($productId);
        if (!$product) {
            return t('err_product_404');
        }
        $wanted = self::whole($quantity, 1, self::MAX_CART_QUANTITY);
        if ($wanted === null) {
            return t('err_whole', ['min' => 1, 'max' => self::MAX_CART_QUANTITY]);
        }
        $total = $wanted + $alreadyInCart;
        if ($total > (int)$product['quantity']) {
            return t('err_stock', ['name' => $product['display_name'], 'available' => (int)$product['quantity']]);
        }
        if ($total > self::MAX_CART_QUANTITY) {
            return t('err_whole', ['min' => 1, 'max' => self::MAX_CART_QUANTITY]);
        }
        return null;
    }

    public static function number(string $value, float $min, float $max): ?float
    {
        $value = trim($value);

        return preg_match('/^\d+(\.\d+)?$/', $value) && (float)$value >= $min && (float)$value <= $max ? (float)$value : null;
    }

    public static function whole(string $value, int $min, int $max): ?int
    {
        $value = trim($value);

        return preg_match('/^\d{1,9}$/', $value) && (int)$value >= $min && (int)$value <= $max ? (int)$value : null;
    }

    private static function reader(array $data): \Closure
    {
        return fn(string $key): string => is_string($data[$key] ?? null) ? trim($data[$key]) : '';
    }
}
