<?php
/*************************************************************
 * Erweitertes load.php hier ist alles drin fürs Laden von Arduino/ESP 32C6
 * Kombiniert alle Funktionen:
 * 1. Sensor-Logging vom ESP32 (Original-Funktion)
 * 2. Medikamenten-Updates vom ESP32 (Smart System) 
 * 3. Kompatibel mit bestehender Website-Struktur
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
    echo json_encode(["status" => "error", "message" => "DB connection failed"]);
    exit;
}

// JSON-Daten empfangen
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON"]);
    exit;
}

// Optional: Alle HTTP-Requests für Debugging loggen
if (!empty($inputJSON)) {
    try {
        $sql = "INSERT INTO receiveddata (msg) VALUES (?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$inputJSON]);
    } catch (Exception $e) {
        // Ignoriere Fehler falls Tabelle nicht existiert
    }
}

// ===== ROUTE 1: ESP32 MEDIKAMENTEN-UPDATE (Smart System) =====
if (isset($input["action"]) && $input["action"] === "update_einnahme") {
    
    if (!isset($input['einnahme_id']) || !isset($input['einnahme_erfolgt'])) {
        echo json_encode(["status" => "error", "message" => "Missing einnahme_id or einnahme_erfolgt"]);
        exit;
    }
    
    $einnahme_id = (int)$input['einnahme_id'];
    $einnahme_erfolgt = $input['einnahme_erfolgt']; // "ja" oder "nein"
    
    $stmt = $pdo->prepare("
        UPDATE einnahmedaten 
        SET einnahme_erfolgt = ? 
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$einnahme_erfolgt, $einnahme_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode([
            "status" => "success",
            "message" => "Medikament-Status aktualisiert",
            "einnahme_id" => $einnahme_id,
            "neuer_status" => $einnahme_erfolgt
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Kein Datensatz aktualisiert - ID nicht gefunden"]);
    }

// ===== ROUTE 2: LEGACY SENSOR-LOGGING (für Debugging) =====  
} else if (isset($input["einnahme_erfolgt"]) && is_numeric($input["einnahme_erfolgt"]) && !isset($input["name"])) {
    
    // Dies ist ein einfacher Sensor-Wert (0 oder 1) vom ESP32 für Debugging
    $einnahme_erfolgt = (int)$input["einnahme_erfolgt"];
    
    // KEINE neue Tabelle - logge in bestehende Struktur (nur für Debugging)
    echo json_encode([
        "status" => "success", 
        "message" => "Sensor-Daten empfangen",
        "sensor_wert" => $einnahme_erfolgt,
        "info" => "Legacy sensor logging - keine DB-Aktion"
    ]);

// ===== ROUTE 3: WEBSITE MEDIKAMENTEN-ERSTELLUNG (dein bestehendes System) =====
} else if (isset($input["name"]) && isset($input["uhrzeit"])) {
    
    // Das kommt von deinem JavaScript / Website-Formular
    // Diese Logik sollte eigentlich in load_user.php bleiben
    // Aber falls du alles in einem Script willst:
    
    $name = $input['name'] ?? '';
    $uhrzeit = $input['uhrzeit'] ?? '';
    $anzahl = (int) ($input['anzahl'] ?? 1);
    $rhythmus = $input['rhythmus'] ?? '';
    $wochentage = $input['wochentage'] ?? [];
    $action = $input['action'] ?? 'new_medication';

    if ($action === 'new_interval') {
        // Neuer Intervall für bestehendes Medikament
        $stmt = $pdo->prepare("SELECT id FROM medikamente WHERE medikamente = ?");
        $stmt->execute([$name]);
        $medikament = $stmt->fetch();
        
        if (!$medikament) {
            echo json_encode(["success" => false, "message" => "Medikament nicht gefunden"]);
            exit;
        }
        $medikamentId = $medikament['id'];
        
    } else {
        // Neues Medikament erstellen
        $stmt = $pdo->prepare("INSERT INTO medikamente (medikamente, start_date, end_daten, status) VALUES (?, NOW(), NULL, 1)");
        $stmt->execute([$name]);
        $medikamentId = $pdo->lastInsertId();
    }

    // Neue intervall_id ermitteln
    $stmt = $pdo->query("SELECT MAX(intervall_id) AS max_id FROM einnahmedaten");
    $row = $stmt->fetch();
    $newIntervallId = ($row['max_id'] ?? 0) + 1;

    // Einträge erstellen
    $stmt = $pdo->prepare("
        INSERT INTO einnahmedaten 
        (id_medikamente, wochentag, uhrzeit, einnahme_erfolgt, anzahl, intervall_id) 
        VALUES (?, ?, ?, 'nein', ?, ?)
    ");

    if ($rhythmus === 'wöchentlich' && !empty($wochentage)) {
        foreach ($wochentage as $tag) {
            $stmt->execute([$medikamentId, $tag, $uhrzeit, $anzahl, $newIntervallId]);
        }
    } else {
        $alleTage = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
        foreach ($alleTage as $tag) {
            $stmt->execute([$medikamentId, $tag, $uhrzeit, $anzahl, $newIntervallId]);
        }
    }

    $message = $action === 'new_interval' ? 'Neuer Intervall hinzugefügt' : 'Neues Medikament gespeichert';
    echo json_encode(["success" => true, "message" => $message]);

// ===== FALLBACK: UNBEKANNTE DATEN =====
} else {
    echo json_encode([
        "status" => "error", 
        "message" => "Unbekannte Daten-Struktur",
        "received_keys" => array_keys($input ?? [])
    ]);
}
?>