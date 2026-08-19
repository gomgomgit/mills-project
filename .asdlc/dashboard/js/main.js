"use strict";

(async function () {
  initTheme();
  initNav();

  // Load data from server
  let data;
  try {
    data = await api.fetchData();
  } catch (e) {
    document.getElementById("main").innerHTML =
      `<div style="padding:40px;color:var(--red)">
        ❌ Tidak bisa terhubung ke server.<br>
        <small style="color:var(--text3)">Pastikan server berjalan: node .asdlc/dashboard/server.js</small>
      </div>`;
    return;
  }

  // Update nav
  document.getElementById("nav-app").textContent  = data.project.name;
  document.getElementById("nb-stale").textContent = data.stats.stale > 0 ? data.stats.stale : "✓";
  updateNavDots(data);
  document.getElementById("nav-footer").textContent = `Updated: ${data._meta.generated_at}`;
  if (window.updateModuleNav) window.updateModuleNav();

  // Render halaman sesuai hash saat ini (bukan selalu dashboard)
  const startPage = window.getCurrentPage ? window.getCurrentPage() : "dashboard";
  if (window.loadPage) window.loadPage(startPage);

  // Track last known data timestamp — only re-render when files actually change
  let lastGeneratedAt = data._meta.generated_at;

  setInterval(async () => {
    try {
      const fresh = await api.fetchData();

      // Always update lightweight nav labels
      document.getElementById("nav-app").textContent  = fresh.project.name;
      document.getElementById("nb-stale").textContent = fresh.stats.stale > 0 ? fresh.stats.stale : "✓";
      document.getElementById("nav-footer").textContent = `Updated: ${fresh._meta.generated_at}`;

      // Only re-render page content when data has actually changed
      if (fresh._meta.generated_at !== lastGeneratedAt) {
        lastGeneratedAt = fresh._meta.generated_at;
        updateNavDots(fresh);
        if (window.updateModuleNav) window.updateModuleNav();
        const current = window.getCurrentPage ? window.getCurrentPage() : "dashboard";
        if (window.loadPage) window.loadPage(current);
      }
    } catch {}
  }, 3000);
})();
