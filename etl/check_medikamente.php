<?php

// DIESE DATEI IST FÜR DIE ETL-PRÜFUNG DER MEDIKAMENTE VORGESEHEN
require_once 'db_config.php';
header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    
    $action = $_GET['action'] ?? 'count';
    
    if ($action === 'full') {
        // ESP32 braucht komplette Daten - WICHTIG: Sortierung nach ERSTELLUNGSREIHENFOLGE!
        $sql = "SELECT 
                    e.id,                    
                    e.id_medikamente as id_medikament,
                    m.id as medikament_table_id,
                    m.medikamente,           
                    e.wochentag,
                    e.uhrzeit,
                    e.anzahl,
                    CASE 
                        WHEN e.einnahme_erfolgt = TRUE THEN 'ja'
                        ELSE 'nein'
                    END as einnahme_erfolgt
                FROM einnahmedaten e
                JOIN medikamente m ON e.id_medikamente = m.id
                WHERE m.status = 1
                ORDER BY m.start_date ASC, e.wochentag, e.uhrzeit ASC";
        
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug-Info hinzufügen (nur in Development)
        if (isset($_GET['debug'])) {
            error_log("Check Medikamente - Anzahl Datensätze: " . count($results));
            foreach ($results as $row) {
                error_log("Medikament: " . $row['medikamente'] . 
                         " (Table-ID: " . $row['medikament_table_id'] . 
                         ", FK-ID: " . $row['id_medikament'] . ")");
            }
        }
        
        echo json_encode($results);
        
    } else {
        // Original: Nur Count
        $stmt = $pdo->query("SELECT COUNT(*) AS count FROM medikamente WHERE status = 1");
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