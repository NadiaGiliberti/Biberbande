<?php
require_once 'db_config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    $stmt = $pdo->query("SELECT COUNT(*) AS count FROM medikamente");
    $result = $stmt->fetch();

    echo json_encode([
        "success" => true,
        "count" => (int)$result['count']
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Datenbankfehler: " . $e->getMessage()
    ]);
}
