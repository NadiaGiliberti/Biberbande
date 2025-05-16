<?php
// DB-Zugangsdaten
$host = "localhost";
$user = "dein_benutzername";
$password = "dein_passwort";
$dbname = "deine_datenbank";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}

// Aktuelle Uhrzeit aus Tabelle2 holen
$sql = "SELECT Uhrzeit FROM Tabelle2 ORDER BY ID DESC LIMIT 1";
$result = $conn->query($sql);

if ($result && $row = $result->fetch_assoc()) {
    echo json_encode(["uhrzeit" => $row["Uhrzeit"]]);
} else {
    echo json_encode(["uhrzeit" => "Keine Daten"]);
}

$conn->close();
?>
