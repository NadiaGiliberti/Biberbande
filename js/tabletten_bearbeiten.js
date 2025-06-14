// Javascript für die html seite tabletten_bearbeiten.html

document.addEventListener("DOMContentLoaded", () => {
    const params = new URLSearchParams(window.location.search);
    const name = params.get("name");

    const titelElement = document.getElementById("bearbeitenName");
    const container = document.querySelector(".interval_container");

    if (titelElement && name) {
        titelElement.textContent = decodeURIComponent(name);

        fetch(`etl/unload.php?action=medikament_details&name=${encodeURIComponent(name)}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && Object.keys(data.data).length > 0) {
                    let counter = 1;
                    for (const [intervallId, einnahmen] of Object.entries(data.data)) {
                        const currentDisplayNumber = counter;

                        const kachel = document.createElement("div");
                        kachel.classList.add("interval_kachel");

                        // Titel z.B. "Einnahmeintervall 1"
                        const title = document.createElement("h4");
                        title.textContent = `Intervall ${currentDisplayNumber}`;
                        title.classList.add("intervall_title");

                        // Icon-Container
                        const iconContainer = document.createElement("div");
                        iconContainer.classList.add("icon_container");

                        // Bearbeiten-Icon
                        const editIcon = document.createElement("img");
                        editIcon.src = "images/bearbeiten_rosa.png";
                        editIcon.classList.add("icon");
                        editIcon.addEventListener("click", () => {
                            window.location.href = `tablette_einnahmedaten_bearbeiten.html?name=${encodeURIComponent(name)}&intervall_id=${intervallId}`;
                        });

                        // Löschen-Icon
                        const deleteIcon = document.createElement("img");
                        deleteIcon.src = "images/loeschen_rosa.png";
                        deleteIcon.classList.add("icon");
                        deleteIcon.addEventListener("click", () => {
                            if (confirm(`Einnahmeintervall ${currentDisplayNumber} wirklich löschen?`)) {
                                fetch(`etl/delete_einnahmen.php?intervall_id=${intervallId}`)
                                    .then(res => res.json())
                                    .then(resp => {
                                        if (resp.success) {
                                            kachel.remove();
                                        } else {
                                            alert("Fehler beim Löschen.");
                                        }
                                    });
                            }
                        });

                        iconContainer.appendChild(editIcon);
                        iconContainer.appendChild(deleteIcon);

                        // Titel und Icons nebeneinander in einem Content-Container
                        const headerRow = document.createElement("div");
                        headerRow.classList.add("kachel_header");
                        headerRow.appendChild(title);
                        headerRow.appendChild(iconContainer);

                        // Content-Container erstellen (wie bei tabletten.js)
                        const contentContainer = document.createElement("div");
                        contentContainer.classList.add("interval_content");
                        contentContainer.appendChild(headerRow);

                        kachel.appendChild(contentContainer);
                        container.appendChild(kachel);

                        counter++; // Zähler erhöhen
                    }
                } else {
                    container.textContent = "Keine Einnahmeintervalle vorhanden.";
                }
            });
    }

    document.getElementById("weitere_einnahme")?.addEventListener("click", () => {
        window.location.href = `tablette_einnahmedaten_neuer_intervall.html?name=${encodeURIComponent(name)}`;
    });
});