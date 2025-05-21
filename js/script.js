//SLIDER IM FOOTER

document.addEventListener('DOMContentLoaded', function () {
    const slider = document.getElementById('slider');
    const toggle = document.getElementById('pageToggle');

    const isOnHome =
        window.location.href.includes("index.html") ||
        window.location.pathname.endsWith("/") ||
        window.location.pathname.endsWith("/index");

    // Position setzen
    if (isOnHome) {
        slider.style.left = "0%";
    } else {
        slider.style.left = "50%";
    }

    // Beim Klicken animieren und nach kurzer Verzögerung wechseln
    toggle.addEventListener('click', () => {
        if (isOnHome) {
            slider.style.left = "50%";
            setTimeout(() => {
                window.location.href = "tabletten.html";
            }, 300); // Warte bis Animation fertig
        } else {
            slider.style.left = "0%";
            setTimeout(() => {
                window.location.href = "index.html";
            }, 300);
        }
    });
});