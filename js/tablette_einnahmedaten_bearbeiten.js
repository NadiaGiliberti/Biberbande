const params = new URLSearchParams(window.location.search);
const name = params.get("name");
const intervall_id = params.get("intervall_id");

if (name) {
    document.getElementById("anzeigeName").textContent = decodeURIComponent(name);
}

// Wenn intervall_id vorhanden ist, lade die vorhandenen Daten
if (intervall_id) {
    fetch(`etl/unload.php?action=intervall_details&intervall_id=${intervall_id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Formulardaten setzen
                document.getElementById("uhrzeit").value = data.data.uhrzeit;
                document.getElementById("anzahl").value = data.data.anzahl;
                
                // Rhythmus setzen
                const rhythmusRadio = document.querySelector(`input[name="rhythmus"][value="${data.data.rhythmus}"]`);
                if (rhythmusRadio) {
                    rhythmusRadio.checked = true;
                    
                    // Wochentage-Box anzeigen falls wöchentlich
                    if (data.data.rhythmus === 'wöchentlich') {
                        const wochentageBox = document.getElementById("wochentage_auswahl");
                        wochentageBox.classList.add("show");
                        wochentageBox.classList.remove("hidden");
                        
                        // Wochentage ankreuzen
                        data.data.wochentage.forEach(tag => {
                            const checkbox = document.querySelector(`#wochentage_auswahl input[value="${tag}"]`);
                            if (checkbox) checkbox.checked = true;
                        });
                    }
                }
            }
        })
        .catch(error => {
            console.error("Fehler beim Laden der Daten:", error);
        });
}

// Radio-Button-Logik (unverändert)
const radioButtons = document.querySelectorAll('input[name="rhythmus"]');
const wochentageBox = document.getElementById("wochentage_auswahl");
const abschliessenBtn = document.getElementById("abschliessen_button");

radioButtons.forEach(radio => {
    radio.addEventListener("change", () => {
        if (radio.value === "wöchentlich" && radio.checked) {
            wochentageBox.classList.add("show");
            wochentageBox.classList.remove("hidden");
        } else {
            wochentageBox.classList.remove("show");
            wochentageBox.classList.add("hidden");
        }
    });
});

abschliessenBtn.addEventListener("click", () => {
    const uhrzeit = document.getElementById("uhrzeit").value;
    const anzahl = document.getElementById("anzahl").value;
    const rhythmus = document.querySelector('input[name="rhythmus"]:checked').value;

    let wochentage = [];
    if (rhythmus === "wöchentlich") {
        const checkedBoxes = document.querySelectorAll('#wochentage_auswahl input[type="checkbox"]:checked');
        checkedBoxes.forEach(box => wochentage.push(box.value));
    }

    const daten = {
        name,
        uhrzeit,
        anzahl,
        rhythmus,
        wochentage
    };

    // Wenn intervall_id vorhanden ist, UPDATE verwenden, sonst CREATE
    if (intervall_id) {
        daten.intervall_id = intervall_id;
        
        fetch('etl/update_einnahmen.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(daten)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = `tabletten_bearbeiten.html?name=${encodeURIComponent(name)}`;
            } else {
                alert("Fehler beim Aktualisieren: " + data.message);
            }
        })
        .catch(error => {
            console.error("Fehler beim Senden der Daten:", error);
            alert("Technischer Fehler beim Aktualisieren.");
        });
    } else {
        // Neues Intervall erstellen (ursprüngliche Logik)
        fetch('etl/load_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(daten)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = `tabletten_bearbeiten.html?name=${encodeURIComponent(name)}`;
            } else {
                alert("Fehler beim Speichern: " + data.message);
            }
        })
        .catch(error => {
            console.error("Fehler beim Senden der Daten:", error);
            alert("Technischer Fehler beim Speichern.");
        });
    }
});