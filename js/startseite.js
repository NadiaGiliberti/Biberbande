//aktuelles Datum oben Anzeigen

const heute = new Date();

    const monat = heute.toLocaleDateString('de-DE', { month: 'long' });
    const tag = heute.getDate();

    document.getElementById('monat').textContent = monat;
    document.getElementById('tag').textContent = tag;


///////////////// abfrage Datenbank

fetch('https://beavertime.nadiagiliberti.ch/etl/unload.php') // Pfad zur PHP-Datei anpassen
  .then(response => response.json())
  .then(data => {
    const container = document.getElementById('medikamentContainer');
    container.innerHTML = ''; // Leeren, falls bereits Inhalte da sind

    // Gruppieren nach Medikamenten-ID (falls mehrere Uhrzeiten pro Medikament)
    const grouped = {};

    data.forEach(item => {
      if (!grouped[item.medi_id]) {
        grouped[item.medi_id] = {
          name: item.medikamente,
          anzahl: item.anzahl,
          uhrzeiten: []
        };
      }

      grouped[item.medi_id].uhrzeiten.push(item.uhrzeit);
    });

    Object.values(grouped).forEach((medikament, index) => {
      const mediDiv = document.createElement('div');
      mediDiv.className = 'medikament';
      mediDiv.id = `medi${index + 1}`;

      const detailsDiv = document.createElement('div');
      detailsDiv.className = 'details_medikament';
      detailsDiv.innerHTML = `
        <h3>${medikament.name}</h3>
        <p>${medikament.anzahl ?? '-'} Stk.</p>
      `;

      const uhrzeitDiv = document.createElement('div');
      uhrzeitDiv.className = 'uhrzeit_feld';
      uhrzeitDiv.innerHTML = medikament.uhrzeiten.map((zeit, i) => 
        `<p class="uhrzeit" id="uhrzeit${index + 1}_${i + 1}">${zeit}</p>`
      ).join('');

      mediDiv.appendChild(detailsDiv);
      mediDiv.appendChild(uhrzeitDiv);
      container.appendChild(mediDiv);
    });
  })
  .catch(error => {
    console.error('Fehler beim Laden der Medikamentendaten:', error);
  });



