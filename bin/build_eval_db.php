<?php

/**
 * Build the separate "suwoda_eval" database with SYNTHETIC customers and orders
 * for the recommender evaluation. The shop's own database is not touched.
 * The data is generated from a fixed seed, so every run gives the same result.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Env;
use App\Core\Database;
use App\Core\Migrator;
use App\Core\MlGrants;

const EVAL_DATABASE = 'suwoda_eval';

Env::load(dirname(__DIR__));
$root = dirname(__DIR__);

try {
    $admin = MlGrants::rootConnection();
    $appUser = $admin->quote((string)Env::get('DB_USER', 'app'));
    $admin->exec('DROP DATABASE IF EXISTS `' . EVAL_DATABASE . '`');
    $admin->exec('CREATE DATABASE `' . EVAL_DATABASE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $admin->exec('GRANT ALL PRIVILEGES ON `' . EVAL_DATABASE . "`.* TO {$appUser}@'%'");

    // Every later connection in this script uses the evaluation database
    $_ENV['DB_NAME'] = $_SERVER['DB_NAME'] = EVAL_DATABASE;
    $script = Database::createScriptConnection();
    if ($script->query('SELECT DATABASE()')->fetchColumn() !== EVAL_DATABASE) {
        throw new RuntimeException('Refusing to continue: not connected to the evaluation database.');
    }
    Migrator::migrate($script, $root . '/database/migrations');
    Migrator::runScript($script, (string)file_get_contents($root . '/database/seed/v1_catalog_seed.sql'));

    mt_srand(20261127);
    $pdo = $script;
    $pdo->beginTransaction();
    $categories = array_map('intval', $pdo->query('SELECT category_id FROM category ORDER BY category_id')->fetchAll(PDO::FETCH_COLUMN));
    $doshas = ['vata', 'pitta', 'kapha'];

    // Synthetic products, so that suggesting five is a real choice
    $addProduct = $pdo->prepare('INSERT INTO product (product_name, description, price, quantity, category_id) VALUES (?, ?, ?, 500, ?)');
    $addDosha = $pdo->prepare('INSERT INTO product_dosha (product_id, dosha, strength) VALUES (?, ?, ?)');
    $products = [];
    for ($i = 1; $i <= 48; $i++) {
        $category = $categories[$i % count($categories)];
        $dosha = $doshas[mt_rand(0, 2)];
        $addProduct->execute(["EVAL product {$i}", "Synthetic item of group {$category}", 200 + mt_rand(0, 18) * 100, $category]);
        $id = (int)$pdo->lastInsertId();
        $addDosha->execute([$id, $dosha, mt_rand(1, 3)]);
        $products[] = ['id' => $id, 'category' => $category, 'dosha' => $dosha, 'appeal' => mt_rand(1, 6)];
    }

    $addUser = $pdo->prepare("INSERT INTO user (name, email, password, status) VALUES (?, ?, 'no-login', 'inactive')");
    $addCategory = $pdo->prepare('INSERT INTO user_category (user_id, category_id) VALUES (?, ?)');
    $addResult = $pdo->prepare('INSERT INTO quiz_result (user_id, session_token, vata, pitta, kapha, dominant) VALUES (?, ?, ?, ?, ?, ?)');
    $addOrder = $pdo->prepare("INSERT INTO orders (user_id, order_date, total_price, payment_status, payment_method, status) VALUES (?, ?, 0, 'paid', 'cod', 'Delivered')");
    $addItem = $pdo->prepare('INSERT INTO order_item (order_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, 1, 100, 100)');

    $orders = 0;
    for ($u = 1; $u <= 200; $u++) {
        $addUser->execute(["Synthetic {$u}", "synthetic{$u}@eval.invalid"]);
        $user = (int)$pdo->lastInsertId();

        // Built-in taste: two favourite categories and one dosha
        $favourites = array_map(fn($k) => $categories[$k], (array)array_rand($categories, 2));
        $dosha = $doshas[mt_rand(0, 2)];
        foreach ($favourites as $category) {
            $addCategory->execute([$user, $category]);
        }
        $scores = ['vata' => 20, 'pitta' => 20, 'kapha' => 20];
        $scores[$dosha] = 60;
        $addResult->execute([$user, md5("eval{$u}"), $scores['vata'], $scores['pitta'], $scores['kapha'], $dosha]);

        $weights = [];
        foreach ($products as $index => $p) {
            $weights[$index] = $p['appeal'] * (in_array($p['category'], $favourites, true) ? 6 : 1) * ($p['dosha'] === $dosha ? 3 : 1);
        }

        $orderCount = mt_rand(2, 5);
        for ($o = 0; $o < $orderCount; $o++) {
            $date = date('Y-m-d H:i:s', strtotime('2026-11-26 12:00:00') - mt_rand(0, 119) * 86400);
            $addOrder->execute([$user, $date]);
            $order = (int)$pdo->lastInsertId();
            $orders++;

            $chosen = [];
            $items = mt_rand(1, 3);
            while (count($chosen) < $items) {
                $pick = mt_rand(1, array_sum($weights));
                foreach ($weights as $index => $weight) {
                    $pick -= $weight;
                    if ($pick <= 0) {
                        break;
                    }
                }
                $chosen[$products[$index]['id']] = true;
            }
            foreach (array_keys($chosen) as $productId) {
                $addItem->execute([$order, $productId]);
            }
        }
    }
    $pdo->commit();

    MlGrants::apply($admin, EVAL_DATABASE);

    echo 'Built ' . EVAL_DATABASE . ' with SYNTHETIC data: ' . count($products) . " extra products, 200 customers, {$orders} orders.\n";
    exit(0);
} catch (Throwable $e) {
    echo 'Could not build the evaluation database: ' . $e->getMessage() . "\n";
    exit(1);
}
