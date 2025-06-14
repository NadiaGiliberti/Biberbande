<?php

// Ladet die  Medikamentenverwaltung und Einnahmedaten von der DB
require_once("db_config.php");
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Parameter für verschiedene Aktionen
$action = $_GET['action'] ?? '';

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    
    switch($action) {
        case 'medikament_details':
            // Spezifische Einnahmedaten für ein bestimmtes Medikament
            if (!isset($_GET['name'])) {
                throw new Exception('Kein Medikamentenname übergeben.');
            }

            // Medikament-ID holen
            $stmt = $pdo->prepare("SELECT id FROM medikamente WHERE medikamente = ?");
            $stmt->execute([$_GET['name']]);
            $medikament = $stmt->fetch();

            if (!$medikament) {
                throw new Exception('Medikament nicht gefunden.');
            }

            // Daten gruppiert nach intervall_id holen
            $stmt = $pdo->prepare("
                SELECT intervall_id, id, wochentag, uhrzeit 
                FROM einnahmedaten 
                WHERE id_medikamente = ? 
                ORDER BY intervall_id, FIELD(wochentag, 'Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag')
            ");
            $stmt->execute([$medikament['id']]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Gruppieren nach intervall_id
            $gruppen = [];
            foreach ($rows as $row) {
                $gruppen[$row['intervall_id']][] = $row;
            }

            echo json_encode(['success' => true, 'data' => $gruppen]);
            break;
            
        case 'alle_medikamente':
            // Alle Medikamente (einfache Liste)
            $stmt = $pdo->query("SELECT id, medikamente FROM medikamente");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(["success" => true, "data" => $result]);
            break;
            
        case 'aktive_medikamente':
            // Alle aktiven Medikamente mit ihren Einnahmedaten
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
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($results);
            break;
            
        case 'intervall_details':
            // Daten für ein bestimmtes Intervall laden (zum Bearbeiten)
            if (!isset($_GET['intervall_id'])) {
                throw new Exception('Keine Intervall-ID übergeben.');
            }
            
            $stmt = $pdo->prepare("
                SELECT wochentag, uhrzeit, anzahl 
                FROM einnahmedaten 
                WHERE intervall_id = ?
                ORDER BY FIELD(wochentag, 'Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag')
            ");
            $stmt->execute([$_GET['intervall_id']]);
            $einnahmen = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($einnahmen)) {
                throw new Exception('Intervall nicht gefunden.');
            }
            
            // Bestimme Rhythmus (täglich = alle 7 Tage, wöchentlich = weniger)
            $rhythmus = count($einnahmen) === 7 ? 'täglich' : 'wöchentlich';
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'uhrzeit' => $einnahmen[0]['uhrzeit'],
                    'anzahl' => $einnahmen[0]['anzahl'],
                    'rhythmus' => $rhythmus,
                    'wochentage' => array_column($einnahmen, 'wochentag')
                ]
            ]);
            break;
            
        default:
            throw new Exception('Ungültige Aktion. Verfügbare Aktionen: medikament_details, alle_medikamente, aktive_medikamente, intervall_details');
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Datenbankfehler',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>