<?php
require_once("db_config.php");
header('Content-Type: application/json');

if (!isset($_GET['intervall_id'])) {
    echo json_encode(['success' => false, 'error' => 'intervall_id fehlt']);
    exit;
}

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    $stmt = $pdo->prepare("DELETE FROM einnahmedaten WHERE intervall_id = ?");
    $stmt->execute([$_GET['intervall_id']]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
