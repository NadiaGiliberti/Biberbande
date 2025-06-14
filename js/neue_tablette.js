// Javascript für die html seite neue_tablette.html

document.getElementById("weiter_button").addEventListener("click", function () {
    const nameField = document.getElementById("name_tablette");
    const errorMessage = document.getElementById("error_message");
    const name = nameField.value.trim();

    if (name === "") {
        errorMessage.textContent = "Bitte Tablettennamen eingeben.";
        nameField.classList.add("input_error");
        nameField.focus();
        return;
    }

    errorMessage.textContent = "";
    nameField.classList.remove("input_error");

    const encodedName = encodeURIComponent(name);
    window.location.href = `tablette_einnahmedaten.html?name=${encodedName}`;
});