<?php
require_once __DIR__ . '/../public/includes/bootstrap.php';
$db = Database::connect();
$userModel = new User($db);
$user = $userModel->findByEmail('jayjaypantaleon@gmail.com');
foreach (['admin', 'admin123', 'password123', 'password', '123456'] as $pass) {
    if (password_verify($pass, $user['password_hash'])) {
        echo "MATCH: $pass\n";
    }
}
