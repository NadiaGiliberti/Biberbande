<?php

// Delete Medikament und zusammengehörige Einnahmedaten
header('Content-Type: application/json');
require_once 'db_config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Ungültige ID']);
    exit;
}

$id = (int)$_GET['id'];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    $pdo->beginTransaction();

    // Einnahmedaten löschen
    $stmt1 = $pdo->prepare("DELETE FROM einnahmedaten WHERE id_medikamente = ?");
    $stmt1->execute([$id]);

    // Medikament löschen
    $stmt2 = $pdo->prepare("DELETE FROM medikamente WHERE id = ?");
    $stmt2->execute([$id]);

    $pdo->commit();

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
