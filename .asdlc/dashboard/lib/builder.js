"use strict";

const readers = require("./readers");

let cache = {};

function getCache() { return cache; }

// ── Label maps ────────────────────────────────────────────────────────────────

const ARTIFACT_LABELS = {
  "prd":               "PRD",
  "arch-spec":         "Architecture Spec",
  "uiux-spec":         "UIUX Spec",
  "actor":             "Actor Catalog",
  "erd":               "ERD",
  "api-list":          "API List",
  "shared-catalog":    "Shared Catalog",
  "integration-spec":  "Integration Spec",
  "screen-index":      "Screen Index",
  "actor-index":       "Actor Index",
  "usecase-index":     "Use Case Index",
  "module-index":      "Module Index",
  "entity-catalog":    "Entity Catalog",
  "shared-decisions":  "Shared Decisions",
  "api-index":         "API Index",
  "scaffold":          "Scaffold",
  "entity-models":     "Entity Models",
  "shared-modules":    "Shared Modules",
  "screen":            "Screen Implementation",
};

const PHASE_LABELS = {
  "1-foundation":    "1 · Foundation",
  "2-business-spec": "2 · Business Spec",
  "3-tech-spec":     "3 · Tech Spec",
  "4-implement":     "4 · Implement",
};

const PHASE_ORDER = ["1-foundation", "2-business-spec", "3-tech-spec", "4-implement"];

// ── Build ─────────────────────────────────────────────────────────────────────

function buildData() {
  const ts = new Date();
  const pad = (n) => String(n).padStart(2, "0");
  const generatedAt = `${ts.getFullYear()}-${pad(ts.getMonth()+1)}-${pad(ts.getDate())}` +
                      `T${pad(ts.getHours())}:${pad(ts.getMinutes())}:${pad(ts.getSeconds())}`;

  const claude = readers.parseCLAUDE();
  const { projectEntries, moduleNodes, moduleIds } = readers.parseDepGraph();

  // Build artifact list from project entries
  const artifacts = (projectEntries || []).map(({ dotPath, node }) => {
    const parts    = dotPath.split(".");
    const phase    = parts[1];
    const artifact = parts[2];

    let status = "not_started";
    if (node !== null) {
      status = (node.stale === true) ? "stale" : "written";
    }

    return {
      key:         dotPath,
      phase,
      phase_label: PHASE_LABELS[phase] || phase,
      artifact,
      label:       ARTIFACT_LABELS[artifact] || artifact,
      status,
      ver:         node ? (node.ver || 0) : 0,
      updated_at:  node ? (node.updated_at || null) : null,
      stale:       node ? (node.stale || false) : false,
      stale_keys:  node ? (node.stale_keys || []) : [],
      depends_on:  node ? (node.depends_on || {}) : {},
    };
  });

  // Group by phase, sorted by PHASE_ORDER
  const phaseMap = {};
  for (const a of artifacts) {
    if (!phaseMap[a.phase]) {
      phaseMap[a.phase] = {
        id:      a.phase,
        label:   a.phase_label,
        keys:    [],
        written: 0,
        total:   0,
      };
    }
    phaseMap[a.phase].keys.push(a.key);
    phaseMap[a.phase].total++;
    if (a.status !== "not_started") phaseMap[a.phase].written++;
  }

  const phases = PHASE_ORDER
    .filter(p => phaseMap[p])
    .map(p => phaseMap[p]);

  // Stats
  const stats = {
    total:       artifacts.length,
    written:     artifacts.filter(a => a.status !== "not_started").length,
    stale:       artifacts.filter(a => a.status === "stale").length,
    not_started: artifacts.filter(a => a.status === "not_started").length,
  };

  // Module nodes (for future use when modules exist)
  const modules = moduleIds.map(mid => ({
    id:    mid,
    nodes: (moduleNodes || []).filter(n => n.moduleId === mid),
  }));

  return {
    _meta: {
      generated_at: generatedAt,
      artifact_count: artifacts.length,
    },
    project: {
      name: claude.name,
    },
    stats,
    phases,
    artifacts,
    modules,
    moduleNodes,
  };
}

// ── Cache refresh ─────────────────────────────────────────────────────────────

function refreshCache() {
  try {
    cache = buildData();
    const t = new Date().toLocaleTimeString();
    const s = cache.stats;
        console.log(`[${t}] ✅ Cache refreshed — ${s.written}/${s.total} artifacts written, ${s.stale} stale`);
  } catch (err) {
    console.error("❌ Build error:", err.message);
  }
}

module.exports = { getCache, buildData, refreshCache };