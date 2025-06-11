<?php
require_once("db_config.php");

header("Content-Type: application/json");

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);

    $stmt = $pdo->query("SELECT id, medikamente FROM medikamente");
    $result = $stmt->fetchAll();

    echo json_encode(["success" => true, "data" => $result]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
