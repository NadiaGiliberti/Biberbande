<?php
header('Content-Type: application/json');
require_once 'db_config.php';

try {
    // Daten aus JSON abrufen
    $input = json_decode(file_get_contents('php://input'), true);

    $name = $input['name'] ?? '';
    $uhrzeit = $input['uhrzeit'] ?? '';
    $anzahl = (int) ($input['anzahl'] ?? 1);
    $rhythmus = $input['rhythmus'] ?? '';
    $wochentage = $input['wochentage'] ?? [];
    $action = $input['action'] ?? 'new_medication'; // 'new_medication' oder 'new_interval'

    $pdo = new PDO($dsn, $db_user, $db_pass, $options);

    if ($action === 'new_interval') {
        // Neuer Intervall für bestehendes Medikament
        
        // 1. Medikament-ID aus vorhandenem Medikament holen
        $stmt = $pdo->prepare("SELECT id FROM medikamente WHERE medikamente = ?");
        $stmt->execute([$name]);
        $medikament = $stmt->fetch();
        
        if (!$medikament) {
            throw new Exception('Medikament nicht gefunden.');
        }
        
        $medikamentId = $medikament['id'];
        
    } else {
        // Komplett neues Medikament erstellen (ursprüngliche Logik)
        
        // 1. Medikament (name) in 'medikamente' einfügen
        $stmt = $pdo->prepare("INSERT INTO medikamente (medikamente, start_date, end_daten, status) VALUES (?, NOW(), NULL, 1)");
        $stmt->execute([$name]);
        $medikamentId = $pdo->lastInsertId();
    }

    // 2. Neue intervall_id ermitteln (für beide Fälle gleich)
    $stmt = $pdo->query("SELECT MAX(intervall_id) AS max_id FROM einnahmedaten");
    $row = $stmt->fetch();
    $newIntervallId = ($row['max_id'] ?? 0) + 1;

    // 3. Einträge für jeden Wochentag in 'einnahmedaten' einfügen (für beide Fälle gleich)
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
        // Wenn "täglich", alle 7 Wochentage einzeln einfügen
        $alleTage = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
        foreach ($alleTage as $tag) {
            $stmt->execute([$medikamentId, $tag, $uhrzeit, $anzahl, $newIntervallId]);
        }
    }

    $message = $action === 'new_interval' ? 'Neuer Intervall hinzugefügt' : 'Neues Medikament gespeichert';
    echo json_encode(["success" => true, "message" => $message]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>