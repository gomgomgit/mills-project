"use strict";

(function () {
  const KEY = "asdlc-theme";

  function apply(mode) {
    document.body.classList.toggle("light", mode === "light");
    const icon  = document.getElementById("theme-icon");
    const label = document.getElementById("theme-label");
    if (icon)  icon.textContent  = mode === "light" ? "☀️" : "🌙";
    if (label) label.textContent = mode === "light" ? "Light Mode" : "Dark Mode";
  }

  function toggleTheme() {
    const current = document.body.classList.contains("light") ? "light" : "dark";
    const next    = current === "light" ? "dark" : "light";
    localStorage.setItem(KEY, next);
    apply(next);
  }

  function initTheme() {
    const saved = localStorage.getItem(KEY) || "dark";
    apply(saved);
  }

  window.toggleTheme = toggleTheme;
  window.initTheme   = initTheme;
})();
