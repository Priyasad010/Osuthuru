<?php

namespace App\Repository;

use App\Core\Database;
use App\Core\FormRules;
use PDO;

/** Shop details that only the owner knows: bank account, contact details. */
class SettingRepo
{
    /** name => longest allowed value */
    public const FIELDS = [
        'bank_name' => 100,
        'bank_branch' => 100,
        'bank_account_name' => 150,
        'bank_account_number' => 30,
        'support_email' => 100,
        'support_phone' => 16,
        'business_address' => 300,
        'policies_reviewed' => 1,
    ];

    private static ?array $cache = null;

    /** Every setting; the ones not entered are empty strings. */
    public static function all(): array
    {
        if (self::$cache === null) {
            $stored = Database::getConnection()->query('SELECT name, value FROM site_setting')->fetchAll(PDO::FETCH_KEY_PAIR);
            self::$cache = array_map(fn() => '', self::FIELDS);
            self::$cache = array_merge(self::$cache, array_intersect_key($stored, self::FIELDS));
        }
        return self::$cache;
    }

    public static function get(string $name): string
    {
        return self::all()[$name] ?? '';
    }

    /** Bank transfer is offered only when customers can be told where to pay. */
    public static function bankConfigured(): bool
    {
        return self::get('bank_name') !== '' && self::get('bank_account_name') !== '' && self::get('bank_account_number') !== '';
    }

    /** @return array ['ok' => true] or ['ok' => false, 'error' => string] */
    public static function save(array $data): array
    {
        $values = [];
        foreach (self::FIELDS as $name => $max) {
            $values[$name] = trim(is_string($data[$name] ?? null) ? $data[$name] : '');
        }
        $values['policies_reviewed'] = $values['policies_reviewed'] === '1' ? '1' : '';

        $errors = FormRules::settings($values);
        if ($errors !== []) {
            return ['ok' => false, 'error' => implode(' ', $errors)];
        }

        $save = Database::getConnection()->prepare(
            'INSERT INTO site_setting (name, value) VALUES (:name, :value) ON DUPLICATE KEY UPDATE value = :again'
        );
        foreach ($values as $name => $value) {
            $save->execute([':name' => $name, ':value' => $value, ':again' => $value]);
        }
        self::$cache = null;

        return ['ok' => true];
    }

    public static function forget(): void
    {
        self::$cache = null;
    }
}
