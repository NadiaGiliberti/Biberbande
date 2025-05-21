//aktuelles Datum oben Anzeigen

const heute = new Date();

    const monat = heute.toLocaleDateString('de-DE', { month: 'long' });
    const tag = heute.getDate();

    document.getElementById('monat').textContent = monat;
    document.getElementById('tag').textContent = tag;


// Uhrzeit Medikamenteneinnahme laden
function ladeUhrzeit() {
  fetch('get_data.php')
    .then(response => response.json())
    .then(data => {
      document.getElementById('uhrzeit-feld').innerText = data.uhrzeit;
    })
    .catch(error => {
      console.error('Fehler beim Laden der Daten:', error);
    });
}

// Sofort beim Laden abrufen
window.onload = () => {
  ladeUhrzeit();

  // Alle 5 Sekunden neu laden (5000 ms)
  setInterval(ladeUhrzeit, 5000);
};


