<?php
/*************************************************************
 * daily_reset.php
 * Setzt täglich alle Medikamenten-Einnahmen zurück
 * Kann manuell aufgerufen oder per Cron-Job ausgeführt werden
 *************************************************************/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once("db_config.php");
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    echo json_encode([
        "success" => false, 
        "message" => "Datenbankverbindung fehlgeschlagen",
        "error" => $e->getMessage()
    ]);
    exit;
}

// Parameter prüfen (für manuelle Aufrufe oder Debug)
$action = $_GET['action'] ?? 'auto';

try {
    // Alle einnahme_erfolgt auf FALSE zurücksetzen (für BOOLEAN-Spalte)
    $sql = "UPDATE einnahmedaten SET einnahme_erfolgt = FALSE WHERE einnahme_erfolgt = TRUE";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute();
    
    $affectedRows = $stmt->rowCount();
    
    // Optional: Log-Eintrag für das Reset (falls du eine Log-Tabelle hast)
    $logSql = "INSERT INTO system_logs (action, message, timestamp) VALUES (?, ?, NOW())";
    try {
        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([
            'daily_reset', 
            "Reset durchgeführt: {$affectedRows} Einträge zurückgesetzt"
        ]);
    } catch (Exception $e) {
        // Ignoriere Log-Fehler falls Tabelle nicht existiert
    }
    
    if ($result) {
        echo json_encode([
            "success" => true,
            "message" => "Tägliches Reset erfolgreich durchgeführt",
            "affected_rows" => $affectedRows,
            "timestamp" => date('Y-m-d H:i:s'),
            "action" => $action
        ]);
        
        // Debug-Info für manuelle Aufrufe
        if ($action === 'manual' || $action === 'debug') {
            echo "\n\n<!-- DEBUG INFO -->\n";
            echo "<!-- Reset Zeit: " . date('Y-m-d H:i:s') . " -->\n";
            echo "<!-- Zurückgesetzte Einträge: {$affectedRows} -->\n";
            echo "<!-- Aufruf-Typ: {$action} -->\n";
        }
        
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Reset fehlgeschlagen",
            "affected_rows" => 0
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Reset Error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Fehler beim Reset",
        "error" => $e->getMessage()
    ]);
}

// Optional: Statistiken anzeigen (nur bei manuellen Aufrufen)
if ($action === 'debug' || $action === 'manual') {
    try {
        $statsSql = "
            SELECT 
                COUNT(*) as total_einnahmen,
                SUM(CASE WHEN einnahme_erfolgt = 'ja' THEN 1 ELSE 0 END) as eingenommen,
                SUM(CASE WHEN einnahme_erfolgt = 'nein' THEN 1 ELSE 0 END) as offen
            FROM einnahmedaten e
            JOIN medikamente m ON e.id_medikamente = m.id
            WHERE m.status = 1
        ";
        
        $statsStmt = $pdo->query($statsSql);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        echo "\n<!-- AKTUELLE STATISTIK -->\n";
        echo "<!-- Total Einnahmen: " . $stats['total_einnahmen'] . " -->\n";
        echo "<!-- Bereits eingenommen: " . $stats['eingenommen'] . " -->\n";
        echo "<!-- Noch offen: " . $stats['offen'] . " -->\n";
        
    } catch (Exception $e) {
        echo "\n<!-- Statistik-Fehler: " . $e->getMessage() . " -->\n";
    }
}
?>