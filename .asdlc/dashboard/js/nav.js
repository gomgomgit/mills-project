"use strict";

(function () {
  let currentPage = "dashboard";
  let currentEl   = null;

  // ── Navigate to a page ───────────────────────────────────────────────────────
  function go(pageId, el, hashOverride) {
    // Update hash (tanpa trigger hashchange lagi)
    // Untuk screen-detail/screen-impl-detail/usecase-detail tanpa override: pertahankan hash yang sudah ada
    const targetHash = hashOverride || (["screen-detail", "screen-impl-detail", "usecase-detail"].includes(pageId) ? null : pageId);
    if (targetHash && window.location.hash !== "#" + targetHash) {
      history.pushState(null, "", "#" + targetHash);
    }

    const prev = document.querySelector(".page.active");
    if (prev) prev.classList.remove("active");

    const next = document.getElementById("page-" + pageId);
    if (next) next.classList.add("active");

    // Nav highlight — cari el dari data-page jika tidak diberikan
    if (!el) el = document.querySelector(`[data-page="${pageId}"]`);
    if (currentEl) currentEl.classList.remove("active");
    if (el) { el.classList.add("active"); currentEl = el; }

    currentPage = pageId;
    closePanel();

    if (window.loadPage) window.loadPage(pageId);
  }

  // ── Toggle collapsible section ───────────────────────────────────────────────
  function toggleSection(id) {
    const body  = document.getElementById("section-" + id);
    const arrow = document.getElementById("arrow-" + id);
    if (!body) return;
    const collapsed = body.classList.toggle("collapsed");
    if (arrow) arrow.textContent = collapsed ? "▸" : "▾";
  }

  // ── Toggle nav open/close ────────────────────────────────────────────────────
  const NAV_KEY = "asdlc-nav-collapsed";

  function toggleNav() {
    const collapsed = document.body.classList.toggle("nav-collapsed");
    localStorage.setItem(NAV_KEY, collapsed ? "1" : "0");
  }

  function initNavCollapsed() {
    if (localStorage.getItem(NAV_KEY) === "1") {
      document.body.classList.add("nav-collapsed");
    }
  }

  // ── Route: resolve current hash to page ──────────────────────────────────────
  const VALID_PAGES = new Set([
    "dashboard", "prd", "arch-spec", "uiux-spec", "test-strategy",
    "actor-index", "usecase-index", "usecase-detail", "screen-index", "screen-detail",
    "entity-catalog", "shared-decisions", "api-index",
    "scaffold", "entity-models", "shared-modules",
    "screen-impl-index", "screen-impl-detail",
    "usecase-test-spec", "screen-test-spec", "screen-test-result-3",
    "depgraph", "stale"
  ]);

  function currentHash() {
    const h = window.location.hash.replace("#", "").trim();
    if (h.startsWith("screen/")) return "screen-detail";
    if (h.startsWith("screen-impl/")) return "screen-impl-detail";
    if (h.startsWith("usecase/")) return "usecase-detail";
    return VALID_PAGES.has(h) ? h : "dashboard";
  }

  // ── Init ─────────────────────────────────────────────────────────────────────
  function initNav() {
    initNavCollapsed();

    // Navigate to hash on load (or default to dashboard)
    const startPage = currentHash();
    go(startPage);

    // Auto-expand section if startPage is a sub-item
    const sectionMap = {
      "prd": "foundation", "arch-spec": "foundation", "uiux-spec": "foundation", "test-strategy": "foundation",
      "actor-index": "business", "usecase-index": "business", "usecase-detail": "business",
      "screen-index": "modules", "screen-detail": "modules",
      "entity-catalog": "tech", "shared-decisions": "tech", "api-index": "tech",
      "scaffold": "impl", "entity-models": "impl", "shared-modules": "impl",
      "screen-impl-index": "impl", "screen-impl-detail": "impl",
      "usecase-test-spec": "testing", "screen-test-spec": "testing", "screen-test-result-3": "testing",
      "depgraph": "others", "stale": "others",
    };
    const sec = sectionMap[startPage];
    if (sec) {
      const body  = document.getElementById("section-" + sec);
      const arrow = document.getElementById("arrow-" + sec);
      if (body)  body.classList.remove("collapsed");
      if (arrow) arrow.textContent = "▾";
    }

    // Browser back/forward
    window.addEventListener("hashchange", function () {
      go(currentHash());
    });
  }

  function getCurrentPage() { return currentPage; }

  window.go             = go;
  window.toggleSection  = toggleSection;
  window.toggleNav      = toggleNav;
  window.getCurrentPage = getCurrentPage;
  window.initNav        = initNav;
})();
