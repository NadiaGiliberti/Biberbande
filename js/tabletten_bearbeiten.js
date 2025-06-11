document.addEventListener("DOMContentLoaded", () => {
    const params = new URLSearchParams(window.location.search);
    const name = params.get("name");

    if (name) {
        const element = document.getElementById("bearbeitenName");
        if (element) {
            element.textContent = decodeURIComponent(name);
        }
    }
});