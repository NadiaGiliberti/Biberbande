<?php
require_once 'db_config.php';
header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    
    // Parameter prüfen
    $action = $_GET['action'] ?? 'count';
    
    if ($action === 'full') {
        // ESP32 braucht komplette Daten
        $sql = "SELECT 
                    e.id,                    -- ID aus einnahmedaten (für Updates)
                    m.medikamente,           -- Name aus medikamente-Tabelle
                    e.wochentag,
                    e.uhrzeit,
                    e.anzahl,
                    e.einnahme_erfolgt
                FROM einnahmedaten e
                JOIN medikamente m ON e.id_medikamente = m.id
                WHERE m.status = 1
                ORDER BY e.uhrzeit ASC";
        
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($results);
        
    } else {
        // Original: Nur Count (für deine Website)
        $stmt = $pdo->query("SELECT COUNT(*) AS count FROM medikamente");
        $result = $stmt->fetch();

        echo json_encode([
            "success" => true,
            "count" => (int)$result['count']
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Datenbankfehler: " . $e->getMessage()
    ]);
}
?>