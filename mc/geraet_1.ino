/**********************************************************************************************
*  Smart Medication System - ESP32-C6 - GERÄT 1
*  Reagiert NUR auf das ERSTE Medikament in der sortierten Reihenfolge (alphabetisch)
*  Prüft anhand der Datenbank, welches Medikament wann eingenommen werden soll
*  Aktiviert LED-Ring und Buzzer zur richtigen Zeit
*  Aktualisiert Einnahmestatus in der Datenbank
*  Führt tägliches Reset um 00:01 Uhr durch
***********************************************************************************************/

#include <Wire.h>
#include "Adafruit_VL6180X.h"
#include <Adafruit_NeoPixel.h>
#include <WiFi.h>
#include <Arduino_JSON.h>
#include <HTTPClient.h>
#include <time.h>

const char* ssid = "HIER WLAN EINGEBEN";
const char* password = "HIER PASSWORT EINGEBEN";

// Server-URLs - ANGEPASST für Gerät 1 (erstes Medikament in alphabetischer Reihenfolge)
const char* loadURL = "https://beavertime.nadiagiliberti.ch/etl/check_medikamente.php?action=full";
const char* updateURL = "https://beavertime.nadiagiliberti.ch/etl/load.php";
const char* resetURL = "https://beavertime.nadiagiliberti.ch/etl/daily_reset.php?action=manual";

#define PIN 2
#define NUM_PIXELS 12
#define BUZZER_PIN 8

// NTP Server für Zeitabfrage
const char* ntpServer = "pool.ntp.org";
const long gmtOffset_sec = 3600;  // GMT+1 (Schweiz Basis)
const int daylightOffset_sec = 3600;  // +1 Stunde Sommerzeit = GMT+2 total

Adafruit_VL6180X vl = Adafruit_VL6180X();
Adafruit_NeoPixel strip = Adafruit_NeoPixel(NUM_PIXELS, PIN, NEO_GRB + NEO_KHZ800);

// Aktuelle Einnahme-Daten
struct Medikament {
  int einnahme_id;
  int medikament_id;
  String name;
  String wochentag;
  String uhrzeit;
  int anzahl;
  String einnahme_erfolgt;
  bool sollEingenommenWerden;
};

Medikament aktuelleMedikamente[50];
int anzahlMedikamente = 0;
int zielMedikamentId = -1; // ID des ersten Medikaments (alphabetisch sortiert)
bool systemAktiv = false;
unsigned long letzteAbfrage = 0;
const unsigned long ABFRAGE_INTERVALL = 30000; // Alle 30 Sekunden prüfen

// Sensor-Zustand
bool becherDrauf = false;
bool letzterBecherZustand = false;
unsigned long becherZeitpunkt = 0;

// Reset-Status
bool resetHeuteAusgefuehrt = false;

void setup() {
  Serial.begin(115200);
  
  Serial.println("=== SMART MEDICATION SYSTEM - GERÄT 1 ===");
  Serial.println("Reagiert NUR auf das ERSTE Medikament (nach Erstellungsreihenfolge)");
  Serial.println("=========================================");
  
  // WLAN verbinden
  connectToWiFi();
  
  // Zeit konfigurieren
  configTime(gmtOffset_sec, daylightOffset_sec, ntpServer);
  Serial.println("Warte auf Zeitsynchronisation...");
  while(!time(nullptr)) {
    delay(1000);
    Serial.print(".");
  }
  Serial.println("\nZeit synchronisiert!");
  
  // Hardware initialisieren
  Wire.begin(6, 7);
  if (!vl.begin()) {
    Serial.println("Sensor nicht gefunden!");
    while (1);
  }
  
  strip.begin();
  strip.setBrightness(50);
  strip.show();
  
  pinMode(BUZZER_PIN, OUTPUT);
  digitalWrite(BUZZER_PIN, LOW);
  
  Serial.println("System bereit!");
  
  // Initiales Laden der Medikamentendaten
  if (WiFi.status() == WL_CONNECTED) {
    ladeMedikamentendaten();
  }
}

void loop() {
  // Alle 30 Sekunden Medikamentendaten abrufen
  if (millis() - letzteAbfrage > ABFRAGE_INTERVALL) {
    letzteAbfrage = millis();
    ladeMedikamentendaten();
    pruefeMedikamentenzeiten();
  }
  
  // Sensor kontinuierlich lesen
  leseSensor();
  
  // System-Status handhaben
  if (systemAktiv) {
    handleAktivesSystem();
  }
  
  delay(300);
}

void connectToWiFi() {
  Serial.print("Verbinde mit WLAN: ");
  Serial.println(ssid);
  WiFi.begin(ssid, password);
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 40) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nWLAN verbunden!");
    Serial.print("IP Adresse: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("\n❌ WLAN-Verbindung fehlgeschlagen!");
    Serial.println("System startet trotzdem (ohne Internet)...");
  }
}

void ladeMedikamentendaten() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("⚠️ Keine WLAN-Verbindung - überspringe Datenabfrage");
    return;
  }
  
  HTTPClient http;
  http.begin(loadURL);
  http.setTimeout(15000);
  
  int httpResponseCode = http.GET();
  
  if (httpResponseCode == 200) {
    String response = http.getString();
    Serial.println("📥 Medikamentendaten erfolgreich geladen");
    parseMedikamentendaten(response);
  } else {
    Serial.print("❌ HTTP Fehler beim Laden: ");
    Serial.println(httpResponseCode);
  }
  
  http.end();
}

void parseMedikamentendaten(String jsonString) {
  JSONVar jsonArray = JSON.parse(jsonString);
  
  if (JSON.typeof(jsonArray) == "undefined") {
    Serial.println("❌ JSON Parse Fehler!");
    Serial.println("Empfangene Daten: " + jsonString.substring(0, 200));
    return;
  }
  
  // Schritt 1: Sammle alle eindeutigen Medikamente (bereits alphabetisch sortiert von PHP)
  struct TempMedikament {
    int medikament_id;
    String name;
    int reihenfolge; // Position in alphabetisch sortierter Liste
  };
  
  TempMedikament alleMedikamente[10];
  int anzahlGefundeneMedikamente = 0;
  
  // Sammle alle eindeutigen Medikamente (PHP hat bereits alphabetisch sortiert!)
  for (int i = 0; i < jsonArray.length(); i++) {
    JSONVar item = jsonArray[i];
    int medId = (int)item["id_medikament"];
    String medName = (const char*)item["medikamente"];
    
    // Prüfe ob Medikament bereits in der Liste
    bool schonVorhanden = false;
    for (int j = 0; j < anzahlGefundeneMedikamente; j++) {
      if (alleMedikamente[j].medikament_id == medId) {
        schonVorhanden = true;
        break;
      }
    }
    
    if (!schonVorhanden && anzahlGefundeneMedikamente < 10) {
      alleMedikamente[anzahlGefundeneMedikamente].medikament_id = medId;
      alleMedikamente[anzahlGefundeneMedikamente].name = medName;
      alleMedikamente[anzahlGefundeneMedikamente].reihenfolge = anzahlGefundeneMedikamente + 1;
      anzahlGefundeneMedikamente++;
    }
  }
  
  Serial.print("📊 Gefundene Medikamente insgesamt: ");
  Serial.println(anzahlGefundeneMedikamente);
  
  // Debug: Zeige alle gefundenen Medikamente in alphabetischer Reihenfolge
  Serial.println("🔍 Medikamente in alphabetischer Reihenfolge:");
  for (int i = 0; i < anzahlGefundeneMedikamente; i++) {
    Serial.print("   ");
    Serial.print(i + 1);
    Serial.print(": ");
    Serial.print(alleMedikamente[i].name);
    Serial.print(" (Medikament-ID: ");
    Serial.print(alleMedikamente[i].medikament_id);
    Serial.println(")");
  }
  
  // GERÄT 1: Wähle das ERSTE Medikament in alphabetischer Reihenfolge
  if (anzahlGefundeneMedikamente > 0) {
    zielMedikamentId = alleMedikamente[0].medikament_id; // Erstes alphabetisch
    Serial.println("====================================");
    Serial.print("🎯 GERÄT 1 überwacht ERSTES Medikament (alphabetisch): ");
    Serial.print(alleMedikamente[0].name);
    Serial.print(" (Medikament-ID: ");
    Serial.print(zielMedikamentId);
    Serial.println(")");
    Serial.println("====================================");
  } else {
    zielMedikamentId = -1;
    Serial.println("⚠️ Keine Medikamente gefunden!");
    anzahlMedikamente = 0;
    return;
  }
  
  // Schritt 2: Lade NUR Einnahmedaten für das ERSTE Medikament (alphabetisch)
  anzahlMedikamente = 0;
  
  Serial.println("🔎 Filtere Einnahmedaten für ERSTES Medikament (nach Erstellung)...");
  for (int i = 0; i < jsonArray.length() && anzahlMedikamente < 50; i++) {
    JSONVar medikament = jsonArray[i];
    int medId = (int)medikament["id_medikament"];
    
    // SEHR WICHTIG: NUR Einnahmedaten für unser spezifisches Ziel-Medikament laden!
    if (medId == zielMedikamentId) {
      aktuelleMedikamente[anzahlMedikamente].einnahme_id = (int)medikament["id"];
      aktuelleMedikamente[anzahlMedikamente].medikament_id = medId;
      aktuelleMedikamente[anzahlMedikamente].name = (const char*)medikament["medikamente"];
      aktuelleMedikamente[anzahlMedikamente].wochentag = (const char*)medikament["wochentag"];
      aktuelleMedikamente[anzahlMedikamente].uhrzeit = (const char*)medikament["uhrzeit"];
      aktuelleMedikamente[anzahlMedikamente].anzahl = (int)medikament["anzahl"];
      aktuelleMedikamente[anzahlMedikamente].einnahme_erfolgt = (const char*)medikament["einnahme_erfolgt"];
      aktuelleMedikamente[anzahlMedikamente].sollEingenommenWerden = false;
      
      Serial.print("   ✓ Einnahme geladen: ");
      Serial.print(aktuelleMedikamente[anzahlMedikamente].name);
      Serial.print(" - ");
      Serial.print(aktuelleMedikamente[anzahlMedikamente].wochentag);
      Serial.print(" ");
      Serial.print(aktuelleMedikamente[anzahlMedikamente].uhrzeit);
      Serial.print(" (Medikament-ID: ");
      Serial.print(medId);
      Serial.print(", Einnahme-ID: ");
      Serial.print(aktuelleMedikamente[anzahlMedikamente].einnahme_id);
      Serial.println(")");
      
      anzahlMedikamente++;
    }
  }
  
  Serial.print("✅ GERÄT 1 - Einnahmedaten geladen für ERSTES Medikament (nach Erstellung): ");
  Serial.println(anzahlMedikamente);
  
  if (anzahlMedikamente == 0) {
    Serial.println("⚠️ WARNUNG: Keine Einnahmedaten für das erste Medikament gefunden!");
  }
}

void pruefeMedikamentenzeiten() {
  if (zielMedikamentId == -1) {
    return; // Kein Medikament zugewiesen
  }
  
  time_t now;
  struct tm timeinfo;
  time(&now);
  localtime_r(&now, &timeinfo);
  
  // Aktueller Wochentag und Uhrzeit
  String wochentage[] = {"Sonntag", "Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag", "Samstag"};
  String aktuellerTag = wochentage[timeinfo.tm_wday];
  
  char zeitBuffer[6];
  strftime(zeitBuffer, sizeof(zeitBuffer), "%H:%M", &timeinfo);
  String aktuelleZeit = String(zeitBuffer);
  
  // Tägliches Reset um 00:01 Uhr
  if (aktuelleZeit == "00:01" && !resetHeuteAusgefuehrt) {
    Serial.println("🌅 Tägliches Reset wird ausgeführt...");
    fuehreTaeglichesResetAus();
    resetHeuteAusgefuehrt = true;
  } else if (aktuelleZeit != "00:01") {
    resetHeuteAusgefuehrt = false;
  }
  
  Serial.print("🕐 Aktuell: ");
  Serial.print(aktuellerTag);
  Serial.print(" ");
  Serial.print(aktuelleZeit);
  Serial.print(" (überwache Medikament-ID: ");
  Serial.print(zielMedikamentId);
  Serial.println(")");
  
  // DEBUG: Zeige geladene Einnahmedaten (nur alle 5 Minuten)
  static unsigned long letzteDebugAusgabe = 0;
  if (millis() - letzteDebugAusgabe > 300000 || letzteDebugAusgabe == 0) {
    letzteDebugAusgabe = millis();
    Serial.println("=== GERÄT 1 - Einnahmedaten für ERSTES Medikament (nach Erstellung) ===");
    for (int i = 0; i < anzahlMedikamente; i++) {
      Serial.print(i + 1);
      Serial.print(": ");
      Serial.print(aktuelleMedikamente[i].name);
      Serial.print(" - ");
      Serial.print(aktuelleMedikamente[i].wochentag);
      Serial.print(" ");
      Serial.print(aktuelleMedikamente[i].uhrzeit);
      Serial.print(" (");
      Serial.print(aktuelleMedikamente[i].anzahl);
      Serial.print(" Stk.) - Status: ");
      Serial.println(aktuelleMedikamente[i].einnahme_erfolgt);
    }
    Serial.println("==============================================");
  }
  
  // Prüfe jede Einnahme für unser Ziel-Medikament
  bool medikamentGefunden = false;
  for (int i = 0; i < anzahlMedikamente; i++) {
    
    // Zeit-Format anpassen: "17:14:00" -> "17:14"
    String medikamentZeit = aktuelleMedikamente[i].uhrzeit;
    if (medikamentZeit.length() > 5) {
      medikamentZeit = medikamentZeit.substring(0, 5);
    }
    
    if (aktuelleMedikamente[i].wochentag == aktuellerTag && 
        medikamentZeit == aktuelleZeit &&
        aktuelleMedikamente[i].einnahme_erfolgt != "ja") {
      
      aktuelleMedikamente[i].sollEingenommenWerden = true;
      medikamentGefunden = true;
      
      Serial.print("✅ GERÄT 1 - MATCH! Zeit für Medikament: ");
      Serial.print(aktuelleMedikamente[i].name);
      Serial.print(" (");
      Serial.print(aktuelleMedikamente[i].anzahl);
      Serial.println(" Stk.)");
      
    } else if (aktuelleMedikamente[i].wochentag == aktuellerTag && 
               medikamentZeit == aktuelleZeit &&
               aktuelleMedikamente[i].einnahme_erfolgt == "ja") {
      Serial.println("ℹ️ Medikament bereits heute eingenommen - kein Alarm");
    }
  }
  
  if (medikamentGefunden && !systemAktiv) {
    systemAktiv = true;
    aktiviereAlarm();
  }
}

// Rest des Codes bleibt unverändert...
void leseSensor() {
  uint8_t range = vl.readRange();
  uint8_t status = vl.readRangeStatus();
  
  if (status == VL6180X_ERROR_NONE) {
    bool neuerZustand = (range < 20 && range > 1);
    
    if (neuerZustand != becherDrauf) {
      becherDrauf = neuerZustand;
      becherZeitpunkt = millis();
      
      if (becherDrauf) {
        Serial.println("🥤 Becher aufgesetzt");
      } else {
        Serial.println("🥤 Becher weggenommen");
        if (systemAktiv) {
          medikamentEingenommen();
        }
      }
    }
  } else {
    static unsigned long letzterSensorFehler = 0;
    if (millis() - letzterSensorFehler > 60000) {
      letzterSensorFehler = millis();
      Serial.print("⚠️ Sensor-Fehler: ");
      Serial.println(status);
    }
  }
}

void handleAktivesSystem() {
  // LED-Ring blinken lassen
  static unsigned long letztesBlinken = 0;
  static bool ledZustand = false;
  
  if (millis() - letztesBlinken > 1000) {
    letztesBlinken = millis();
    ledZustand = !ledZustand;
    
    strip.clear();
    if (ledZustand) {
      for (int i = 0; i < NUM_PIXELS; i++) {
        strip.setPixelColor(i, strip.Color(0, 255, 0)); // Grün für "Zeit zum Einnehmen"
      }
    }
    strip.show();
  }
  
  // Buzzer im Intervall piepsen
  static unsigned long letzterPiep = 0;
  if (millis() - letzterPiep > 2000) {
    letzterPiep = millis();
    tone(BUZZER_PIN, 1000, 200);
  }
}

void aktiviereAlarm() {
  Serial.println("🚨 GERÄT 1 - ALARM AKTIVIERT - Zeit für ERSTES Medikament (nach Erstellung)!");
  
  // LED-Ring dauerhaft grün
  strip.clear();
  for (int i = 0; i < NUM_PIXELS; i++) {
    strip.setPixelColor(i, strip.Color(0, 255, 0));
  }
  strip.show();
  
  // Kurzer Bestätigungspiep
  tone(BUZZER_PIN, 1500, 500);
  
  // Zeige welche Medikamente eingenommen werden sollen
  Serial.println("📋 Einzunehmende Medikamente:");
  for (int i = 0; i < anzahlMedikamente; i++) {
    if (aktuelleMedikamente[i].sollEingenommenWerden) {
      Serial.print("   • ");
      Serial.print(aktuelleMedikamente[i].name);
      Serial.print(" (");
      Serial.print(aktuelleMedikamente[i].anzahl);
      Serial.println(" Stk.)");
    }
  }
}

void medikamentEingenommen() {
  Serial.println("✅ GERÄT 1 - Medikament eingenommen!");
  
  // Erfolg-Feedback
  strip.clear();
  for (int i = 0; i < NUM_PIXELS; i++) {
    strip.setPixelColor(i, strip.Color(0, 0, 255)); // Blau für "Erfolgreich"
  }
  strip.show();
  
  tone(BUZZER_PIN, 2000, 300);
  delay(1000);
  
  // System deaktivieren
  systemAktiv = false;
  strip.clear();
  strip.show();
  noTone(BUZZER_PIN);
  
  // In Datenbank als eingenommen markieren
  aktualisiereEinnahmestatus();
}

void aktualisiereEinnahmestatus() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("⚠️ Keine WLAN-Verbindung - kann Status nicht aktualisieren");
    return;
  }
  
  for (int i = 0; i < anzahlMedikamente; i++) {
    if (aktuelleMedikamente[i].sollEingenommenWerden) {
      
      JSONVar updateData;
      updateData["action"] = "update_einnahme";
      updateData["einnahme_id"] = aktuelleMedikamente[i].einnahme_id;
      updateData["einnahme_erfolgt"] = true;
      
      String jsonString = JSON.stringify(updateData);
      
      HTTPClient http;
      http.begin(updateURL);
      http.addHeader("Content-Type", "application/json");
      http.setTimeout(10000);
      
      int httpResponseCode = http.POST(jsonString);
      
      if (httpResponseCode > 0) {
        String response = http.getString();
        Serial.print("✅ GERÄT 1 - Update erfolgreich für ");
        Serial.print(aktuelleMedikamente[i].name);
        Serial.print(": ");
        Serial.println(response);
      } else {
        Serial.print("❌ Update Fehler für ");
        Serial.print(aktuelleMedikamente[i].name);
        Serial.print(": ");
        Serial.println(httpResponseCode);
      }
      
      http.end();
      
      aktuelleMedikamente[i].sollEingenommenWerden = false;
      aktuelleMedikamente[i].einnahme_erfolgt = "ja";
    }
  }
}

void fuehreTaeglichesResetAus() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("⚠️ Keine WLAN-Verbindung - kann Reset nicht durchführen");
    return;
  }
  
  HTTPClient http;
  http.begin(resetURL);
  http.setTimeout(15000);
  
  int httpResponseCode = http.GET();
  
  if (httpResponseCode == 200) {
    String response = http.getString();
    Serial.print("✅ Reset erfolgreich: ");
    Serial.println(response);
    
    delay(2000);
    ladeMedikamentendaten();
    
  } else {
    Serial.print("❌ Reset Fehler: ");
    Serial.println(httpResponseCode);
  }
  
  http.end();
}