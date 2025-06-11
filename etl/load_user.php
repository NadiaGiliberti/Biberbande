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

    $pdo = new PDO($dsn, $db_user, $db_pass, $options);

    // 1. Medikament (name) in 'medikamente' einfügen
    $stmt = $pdo->prepare("INSERT INTO medikamente (medikamente, start_date, end_daten, status) VALUES (?, NOW(), NULL, 1)");
    $stmt->execute([$name]);
    $medikamentId = $pdo->lastInsertId();

    // 2. Einträge für jeden Wochentag in 'einnahmedaten' einfügen
    if ($rhythmus === 'wöchentlich' && !empty($wochentage)) {
        $stmt = $pdo->prepare("INSERT INTO einnahmedaten (id_medikamente, wochentag, uhrzeit, einnahme_erfolgt, anzahl) VALUES (?, ?, ?, 'nein', ?)");
        foreach ($wochentage as $tag) {
            $stmt->execute([$medikamentId, $tag, $uhrzeit, $anzahl]);
        }
    } else {
        // Wenn "täglich", alle 7 Wochentage einzeln einfügen
        $alleTage = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
        $stmt = $pdo->prepare("INSERT INTO einnahmedaten (id_medikamente, wochentag, uhrzeit, einnahme_erfolgt, anzahl) VALUES (?, ?, ?, 'nein', ?)");
        foreach ($alleTage as $tag) {
            $stmt->execute([$medikamentId, $tag, $uhrzeit, $anzahl]);
        }
    }

    echo json_encode(["success" => true, "message" => "Daten gespeichert"]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}