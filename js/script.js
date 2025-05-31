document.addEventListener('DOMContentLoaded', function () {
    const slider = document.getElementById('slider');
    const toggle = document.getElementById('pageToggle');
    const homeIcon = document.getElementById('homeIcon');
    const pillIcon = document.getElementById('pillIcon');

    const isOnHome =
        window.location.href.includes("index.html") ||
        window.location.pathname.endsWith("/") ||
        window.location.pathname.endsWith("/index");

    // Bilder anpassen je nach Seite
    if (isOnHome) {
        homeIcon.src = "images/home_rot.png";
        pillIcon.src = "images/pille_rosa.png";
        slider.style.left = "0%";
    } else {
        homeIcon.src = "images/home_rosa.png";
        pillIcon.src = "images/pille_rot.png";
        slider.style.left = "calc(100% - 30%)";
    }

    // Toggle-Animation und Seitenwechsel
    toggle.addEventListener('click', () => {
        if (isOnHome) {
            slider.style.left = "calc(100% - 30%)";
            setTimeout(() => {
                window.location.href = "tabletten.html";
            }, 300);
        } else {
            slider.style.left = "0%";
            setTimeout(() => {
                window.location.href = "index.html";
            }, 300);
        }
    });
});
