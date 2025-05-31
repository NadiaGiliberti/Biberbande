document.getElementById("weiter_button").addEventListener("click", function () {
    const name = document.getElementById("name_tablette").value;
    const encodedName = encodeURIComponent(name);
    window.location.href = `tablette_einnahmedaten.html?name=${encodedName}`;
});