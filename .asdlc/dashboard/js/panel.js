"use strict";

(function () {
  let _tabs        = [];
  let _activeTab   = 0;

  function openPanel(key, label, badge, tabs) {
    _tabs      = tabs;
    _activeTab = 0;

    document.getElementById("panel-key").textContent   = key;
    document.getElementById("panel-title").textContent  = label;

    const badgeEl = document.getElementById("panel-badge");
    badgeEl.innerHTML = badge || "";

    renderTabs();
    renderTabBody(0);

    document.getElementById("panel").classList.add("open");
  }

  function closePanel() {
    document.getElementById("panel").classList.remove("open");
    _tabs = [];
  }

  function renderTabs() {
    const el = document.getElementById("panel-tabs");
    el.innerHTML = _tabs.map((t, i) =>
      `<div class="ptab${i === 0 ? " active" : ""}" onclick="switchPanelTab(${i})">${t.label}</div>`
    ).join("");
  }

  function switchPanelTab(i) {
    _activeTab = i;
    document.querySelectorAll(".ptab").forEach((el, idx) =>
      el.classList.toggle("active", idx === i)
    );
    renderTabBody(i);
  }

  function renderTabBody(i) {
    const tab = _tabs[i];
    if (!tab) return;
    const body = document.getElementById("panel-body");
    if (typeof tab.render === "function") {
      body.innerHTML = '<div class="panel-loading">Memuat…</div>';
      Promise.resolve(tab.render()).then(html => { body.innerHTML = html; });
    } else {
      body.innerHTML = tab.content || "";
    }
  }

  window.openPanel     = openPanel;
  window.closePanel    = closePanel;
  window.switchPanelTab = switchPanelTab;
})();
