// Javascript für die html seite tabelle_einnahmedaten.html

const params = new URLSearchParams(window.location.search);
const name = params.get("name");

if (name) {
    document.getElementById("anzeigeName").textContent = decodeURIComponent(name);
}


// Erfassen der Tablette

const radioButtons = document.querySelectorAll('input[name="rhythmus"]');
const wochentageBox = document.getElementById("wochentage_auswahl");
const abschliessenBtn = document.getElementById("abschliessen_button");

radioButtons.forEach(radio => {
    radio.addEventListener("change", () => {
        if (radio.value === "wöchentlich" && radio.checked) {
            wochentageBox.classList.add("show");
            wochentageBox.classList.remove("hidden"); // <-- wichtig!
        } else {
            wochentageBox.classList.remove("show");
            wochentageBox.classList.add("hidden"); // <-- wichtig!
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
        // Erfolgreich gespeichert -> Weiterleitung MIT Medikamentenname
        window.location.href = `tabletten_bearbeiten.html?name=${encodeURIComponent(name)}`;
    } else {
        alert("Fehler beim Speichern: " + data.message);
    }
})
.catch(error => {
    console.error("Fehler beim Senden der Daten:", error);
    alert("Technischer Fehler beim Speichern.");
});
});