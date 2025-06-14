<?php

// Update Einnahmedaten für ein Intervall
header('Content-Type: application/json');
require_once 'db_config.php';

try {
    // Daten aus JSON abrufen
    $input = json_decode(file_get_contents('php://input'), true);

    $intervall_id = $input['intervall_id'] ?? '';
    $uhrzeit = $input['uhrzeit'] ?? '';
    $anzahl = (int) ($input['anzahl'] ?? 1);
    $rhythmus = $input['rhythmus'] ?? '';
    $wochentage = $input['wochentage'] ?? [];

    if (!$intervall_id) {
        throw new Exception('Keine Intervall-ID übergeben.');
    }

    $pdo = new PDO($dsn, $db_user, $db_pass, $options);

    // 1. ZUERST Medikament-ID ermitteln (BEVOR wir löschen!)
    $stmt = $pdo->prepare("
        SELECT DISTINCT e.id_medikamente 
        FROM einnahmedaten e 
        WHERE e.intervall_id = ?
    ");
    $stmt->execute([$intervall_id]);
    $medikament = $stmt->fetch();
    
    if (!$medikament) {
        throw new Exception('Intervall nicht gefunden.');
    }
    
    $medikamentId = $medikament['id_medikamente'];

    // 2. DANN alte Einträge für dieses Intervall löschen
    $stmt = $pdo->prepare("DELETE FROM einnahmedaten WHERE intervall_id = ?");
    $stmt->execute([$intervall_id]);

    // 3. Neue Einträge einfügen
    $stmt = $pdo->prepare("
        INSERT INTO einnahmedaten 
        (id_medikamente, wochentag, uhrzeit, einnahme_erfolgt, anzahl, intervall_id) 
        VALUES (?, ?, ?, 'nein', ?, ?)
    ");

    if ($rhythmus === 'wöchentlich' && !empty($wochentage)) {
        foreach ($wochentage as $tag) {
            $stmt->execute([$medikamentId, $tag, $uhrzeit, $anzahl, $intervall_id]);
        }
    } else {
        // Wenn "täglich", alle 7 Wochentage einzeln einfügen
        $alleTage = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
        foreach ($alleTage as $tag) {
            $stmt->execute([$medikamentId, $tag, $uhrzeit, $anzahl, $intervall_id]);
        }
    }

    echo json_encode(["success" => true, "message" => "Daten aktualisiert"]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>