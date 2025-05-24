<?php
require 'db_config.php';
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);

    $sql = "SELECT 
                m.id AS medi_id,
                m.medikamente,
                m.start_date,
                m.end_daten,
                m.status,
                e.wochentag,
                e.uhrzeit,
                e.anzahl,
                e.einnahme_erfolgt
            FROM medikamente m
            LEFT JOIN einnahmedaten e ON m.id = e.id_medikamente
            WHERE m.status = 1
            ORDER BY m.id ASC, e.uhrzeit ASC";

    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll();

    echo json_encode($results);

} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Fehler beim Datenbankzugriff',
        'message' => $e->getMessage()
    ]);
}
?>