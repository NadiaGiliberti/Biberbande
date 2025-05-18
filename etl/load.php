<?php
/*************************************************************
 * Kapitel 12: Website2DB > Schritt 3: Website -> DB
 * load.php
 * Daten als JSON-String vom Formular sender.html (später vom MC) serverseitig empfangen und Daten in die Datenbank einfügen
 * Datenbank-Verbindung
 * Ersetze $db_host, $db_name, $db_user, $db_pass durch deine eigenen Daten. 
 * Lade diese Datei NICHT auf GitHub
 * Beispiel: https://fiessling.ch/im4/12_Website2DB/Schritt3_website_to_db/load.php 
 * GitHub: https://github.com/Interaktive-Medien/im_physical_computing/blob/main/12_Website2DB/Schritt3_website_to_db/load.php
 *************************************************************/


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once("db_config.php"); // Datenbank-Konfiguration
echo "This script receives HTTP POST messages and pushes their content into the database.";


###################################### connect to db

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    echo "</br> DB Verbindung ist erfolgreich";
} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "DB connection failed"]);
}


###################################### Empfangen der JSON-Daten

$inputJSON = file_get_contents('php://input'); // JSON-Daten aus dem Body der Anfrage
$input = json_decode($inputJSON, true); // Dekodieren der JSON-Daten in ein Array




###################################### Prüfen, ob die JSON-Daten erfolgreich dekodiert wurden
### folgender Block nicht zwingend notwendig, nur für Troubleshooting: Die rohen JSON-Daten in die Tabelle receiveddata einfügen
/*
if (json_last_error() === JSON_ERROR_NONE && !empty($input)) {
    $sql = "INSERT INTO receiveddata (msg) VALUES (?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$inputJSON]);
}

echo "</br></br> Zeig die letzten 5 empfangenen HTTP Requests";
$sql = "SELECT * FROM receiveddata ORDER BY id DESC LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$receiveddata = $stmt->fetchAll();

echo "<ul>";
foreach ($receiveddata as $data) {
    echo "<li>" . $data['msg'] . "</li>";
}
echo "</ul>";

*/
###################################### receiving a post request from a HTML form, later from ESP32 C6

if (isset($input["einnahme_erfolgt"])) {
    $einnahme_erfolgt = $input["einnahme_erfolgt"];

    $sql = "INSERT INTO einnahmedaten (einnahme_erfolgt) VALUES (?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$einnahme_erfolgt]);
} else {
    error_log("JSON fehlt 'einnahme_erfolgt'");
    echo json_encode(["status" => "error", "message" => "Missing 'einnahme_erfolgt' in JSON"]);
}

?>