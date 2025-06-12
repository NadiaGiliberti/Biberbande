// Aktuelles Datum oben anzeigen
const heute = new Date();
const monat = heute.toLocaleDateString('de-DE', { month: 'long' });
const tag = heute.getDate();
document.getElementById('monat').textContent = monat;
document.getElementById('tag').textContent = tag;

// Hilfsfunktionen
function getWochentagName(date) {
  return date.toLocaleDateString('de-DE', { weekday: 'long' }); // z.B. "Dienstag"
}

function uhrzeitZuDate(uhrzeit) {
  const [stunden, minuten] = uhrzeit.split(':').map(Number);
  const datum = new Date();
  datum.setHours(stunden, minuten, 0, 0);
  return datum;
}

// Abfrage Datenbank
fetch('etl/unload.php?action=aktive_medikamente')
  .then(response => response.json())
  .then(data => {
    const container = document.getElementById('medikamentContainer');
    container.innerHTML = ''; // Leeren, falls bereits Inhalte da sind

    const heuteWochentag = getWochentagName(heute);
    const jetzt = new Date();

    const groupedByMedi = {};

    data.forEach(item => {
      if (item.wochentag === heuteWochentag) {
        const einnahmeZeit = uhrzeitZuDate(item.uhrzeit);

        if (einnahmeZeit > jetzt) {
          if (!groupedByMedi[item.medi_id]) {
            groupedByMedi[item.medi_id] = [];
          }
          groupedByMedi[item.medi_id].push({
            zeit: einnahmeZeit,
            uhrzeit: item.uhrzeit,
            name: item.medikamente,
            anzahl: item.anzahl
          });
        }
      }
    });

    const mediIDs = Object.keys(groupedByMedi);

    if (mediIDs.length === 0) {
      container.innerHTML = '<p>Keine weiteren Einnahmen für heute.</p>';
      return;
    }

    mediIDs.forEach((mediId) => {
      const einnahmen = groupedByMedi[mediId];

      if (einnahmen.length === 0) return;

      // Nächstes Ereignis für dieses Medikament
      einnahmen.sort((a, b) => a.zeit - b.zeit);
      const naechste = einnahmen[0];

      const mediDiv = document.createElement('div');
      mediDiv.className = 'medikament';

      mediDiv.innerHTML = `
        <div class="details_medikament">
          <h3>${naechste.name}</h3>
          <p>${naechste.anzahl ?? '-'} Stk.</p>
        </div>
        <div class="uhrzeit_feld">
          <p class="uhrzeit">${naechste.uhrzeit}</p>
        </div>
      `;

      container.appendChild(mediDiv);
    });
  })
  .catch(error => {
    console.error('Fehler beim Laden der Medikamentendaten:', error);
  });
