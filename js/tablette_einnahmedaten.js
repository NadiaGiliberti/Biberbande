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
        uhrzeit,
        anzahl,
        rhythmus,
        wochentage
    };

    console.log("Gespeicherte Daten:", daten);
});