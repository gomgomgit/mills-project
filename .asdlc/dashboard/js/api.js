"use strict";

(function () {
  let _cache = null;

  async function fetchData() {
    const res  = await fetch("/api/data");
    _cache     = await res.json();
    return _cache;
  }

  async function fetchArtifact(key) {
    const res = await fetch(`/api/artifact?key=${encodeURIComponent(key)}`);
    return res.json();
  }

  async function fetchUiuxPreviews() {
    const res = await fetch("/api/uiux-preview");
    return res.json();
  }

  async function fetchScreenFiles() {
    const res = await fetch("/api/screen-files");
    return res.json();
  }

  async function fetchUsecaseFiles() {
    const res = await fetch("/api/usecase-files");
    return res.json();
  }

  async function fetchImplScreenFiles() {
    const res = await fetch("/api/impl-screen-files");
    return res.json();
  }

  async function fetchImplScreens() {
    const res = await fetch("/api/impl-screens");
    return res.json();
  }

  function getCached() { return _cache; }

  window.api = { fetchData, fetchArtifact, fetchUiuxPreviews, fetchScreenFiles, fetchUsecaseFiles, fetchImplScreenFiles, fetchImplScreens, getCached };
})();
