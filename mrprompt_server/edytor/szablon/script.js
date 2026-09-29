document.addEventListener("DOMContentLoaded", () => {
    const btn = document.getElementById("actionBtn");
    const output = document.getElementById("output");

    btn.addEventListener("click", () => {
        output.textContent = "Przycisk został kliknięty!";
    });
});
