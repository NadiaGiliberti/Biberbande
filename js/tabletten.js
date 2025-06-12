document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("tablettenListe");
    const errorMessage = document.getElementById("error_message");
    const plusLink = document.querySelector('.plusbutton')?.closest('a');

    // ✅ Medikamente laden und Kacheln erzeugen
    if (container) {
        fetch('etl/unload.php?action=alle_medikamente')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.data.forEach(tablette => {
                        const kachel = document.createElement("div");
                        kachel.classList.add("tablette_kachel");

                        const titel = document.createElement("span");
                        titel.textContent = tablette.medikamente;
                        titel.classList.add("tablette_titel");

                        const iconContainer = document.createElement("div");
                        iconContainer.classList.add("icon_container");

                        // 🛠️ Bearbeiten
                        const bearbeitenIcon = document.createElement("img");
                        bearbeitenIcon.src = "images/bearbeiten_schwarz.png";
                        bearbeitenIcon.alt = "Bearbeiten";
                        bearbeitenIcon.classList.add("icon");
                        bearbeitenIcon.addEventListener("click", () => {
                            const url = new URL('tabletten_bearbeiten.html', window.location.href);
                            url.searchParams.set("name", tablette.medikamente);
                            document.location.href = url.toString();
                        });

                        // ❌ Löschen
                        const loeschenIcon = document.createElement("img");
                        loeschenIcon.src = "images/loeschen_schwarz.png";
                        loeschenIcon.alt = "Löschen";
                        loeschenIcon.classList.add("icon");
                        loeschenIcon.addEventListener("click", () => {
                            if (confirm(`Möchtest du "${tablette.medikamente}" wirklich löschen?`)) {
                                fetch(`etl/delete_tablette.php?id=${encodeURIComponent(tablette.id)}`)
                                    .then(res => res.json())
                                    .then(response => {
                                        if (response.success) {
                                            kachel.remove(); // Kachel entfernen
                                        } else {
                                            alert("Fehler beim Löschen: " + (response.error || ""));
                                        }
                                    })
                                    .catch(err => {
                                        console.error("Löschfehler:", err);
                                        alert("Technischer Fehler beim Löschen.");
                                    });
                            }
                        });

                        iconContainer.appendChild(bearbeitenIcon);
                        iconContainer.appendChild(loeschenIcon);

                        const contentContainer = document.createElement("div");
                        contentContainer.classList.add("tablette_content");
                        contentContainer.appendChild(titel);
                        contentContainer.appendChild(iconContainer);
                        kachel.appendChild(contentContainer);
                        container.appendChild(kachel);
                    });
                } else {
                    console.error("Fehler:", data.error);
                    container.textContent = "Fehler beim Laden der Tabletten.";
                }
            })
            .catch(error => {
                console.error("Netzwerkfehler:", error);
                container.textContent = "Verbindung zur Datenbank fehlgeschlagen.";
            });
    }

    // ✅ Klick auf Plus-Button
    if (plusLink) {
        plusLink.addEventListener("click", function (e) {
            e.preventDefault();

            if (errorMessage) errorMessage.textContent = "";

            fetch('etl/check_medikamente.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.count < 2) {
                            window.location.href = 'neue_tablette.html';
                        } else {
                            if (errorMessage) {
                                errorMessage.textContent = "Du kannst maximal zwei Medikamente hinzufügen. Bitte lösche zuerst eines.";
                            } else {
                                alert("Du kannst maximal zwei Medikamente hinzufügen.");
                            }
                        }
                    } else {
                        if (errorMessage) {
                            errorMessage.textContent = "Fehler beim Prüfen der Medikamentenanzahl.";
                        } else {
                            alert("Fehler beim Prüfen der Medikamentenanzahl.");
                        }
                    }
                })
                .catch(error => {
                    console.error("Fehler beim Abrufen:", error);
                    if (errorMessage) {
                        errorMessage.textContent = "Technischer Fehler beim Überprüfen der Medikamentenanzahl.";
                    } else {
                        alert("Technischer Fehler beim Überprüfen der Medikamentenanzahl.");
                    }
                });
        });
    }
});
