<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/Database.php';

use App\Core\Env;
use App\Core\Database;

Env::load(dirname(__DIR__));

echo "==========================================\n";
echo "Osuthuru - Create Initial Admin\n";
echo "==========================================\n";

$name = Env::get('ADMIN_NAME', 'Osuthuru Administrator');
$email = Env::get('ADMIN_EMAIL');
$password = Env::get('ADMIN_PASSWORD');

if (empty($email) || empty($password)) {
    echo "[ERROR] ADMIN_EMAIL and ADMIN_PASSWORD must be configured in .env\n";
    exit(1);
}

try {
    $pdo = Database::getConnection();

    // Check if admin already exists
    $stmt = $pdo->prepare("SELECT admin_id FROM `admin` WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $existing = $stmt->fetch();

    $hash = password_hash($password, PASSWORD_DEFAULT);

    if ($existing) {
        $update = $pdo->prepare("UPDATE `admin` SET name = :name, password = :password WHERE email = :email");
        $update->execute([
            ':name' => $name,
            ':password' => $hash,
            ':email' => $email
        ]);
        echo "[OK] Admin account updated for: {$email}\n";
    } else {
        $insert = $pdo->prepare("INSERT INTO `admin` (name, email, password) VALUES (:name, :email, :password)");
        $insert->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => $hash
        ]);
        echo "[OK] Initial admin account created for: {$email}\n";
    }

    exit(0);

} catch (Throwable $e) {
    echo "[ERROR] Failed to create admin: " . $e->getMessage() . "\n";
    exit(1);
}
