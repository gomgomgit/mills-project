"use strict";

const fs   = require("fs");
const path = require("path");

let PROJECT_ROOT  = "";
let ASDLC_ROOT    = "";
let GENERATED_DIR = "";

function init(config) {
  PROJECT_ROOT  = config.PROJECT_ROOT;
  ASDLC_ROOT    = config.ASDLC_ROOT;
  GENERATED_DIR = config.GENERATED_DIR || path.join(config.ASDLC_ROOT, "generated");
}

function readJson(filePath) {
  try { return JSON.parse(fs.readFileSync(filePath, "utf8").replace(/\0+$/, "")); } catch { return null; }
}

function readText(filePath) {
  try { return fs.readFileSync(filePath, "utf8"); } catch { return null; }
}

function parseCLAUDE() {
  // 1. Try prd.json meta.title first
  const prd = readJson(path.join(GENERATED_DIR, "1-foundation/prd.json"));
  if (prd && prd.meta && prd.meta.title && prd.meta.title.trim()) {
    return { name: prd.meta.title.trim() };
  }

  // 2. Fallback: parse CLAUDE.md
  const content = readText(path.join(PROJECT_ROOT, "CLAUDE.md"));
  if (!content) return { name: "Project Baru" };
  const m = content.match(/\*\*Name[^:]*:\*\*\s*(.+)/i) || content.match(/\*\*Nama[^:]*:\*\*\s*(.+)/i);
  let name = m ? m[1].replace(/\[|\]/g, "").trim() : "Project Baru";
  if (!name || name.includes("Application Name") || name.includes("Nama Aplikasi") || name === "CLAUDE") {
    name = "Project Baru";
  }
  return { name };
}

function walkProjectNodes(projectObj, prefix) {
  const results = [];
  // Walk exactly 2 levels: phase → artifact
  for (const [phaseId, phaseVal] of Object.entries(projectObj)) {
    if (!phaseVal || typeof phaseVal !== "object") continue;
    for (const [artifactId, artifactVal] of Object.entries(phaseVal)) {
      const dotPath = prefix + "." + phaseId + "." + artifactId;

      if (artifactVal === null) {
        // Not started
        results.push({ dotPath, node: null });
      } else if (typeof artifactVal === "object" && "ver" in artifactVal) {
        // Artifact-level node
        results.push({ dotPath, node: artifactVal });
      } else if (typeof artifactVal === "object") {
        // Field-level nodes — aggregate into one artifact entry
        const fieldNodes = Object.values(artifactVal)
          .filter(v => v && typeof v === "object" && "ver" in v);
        if (fieldNodes.length === 0) {
          results.push({ dotPath, node: null });
        } else {
          const stale      = fieldNodes.some(n => n.stale === true);
          const ver        = Math.max(...fieldNodes.map(n => n.ver || 0));
          const updated_at = fieldNodes.map(n => n.updated_at || "").sort().reverse()[0] || null;
          const depends_on = Object.assign({}, ...fieldNodes.map(n => n.depends_on || {}));
          const stale_keys = [...new Set(fieldNodes.flatMap(n => n.stale_keys || []))];
          results.push({ dotPath, node: { ver, updated_at, stale, stale_keys, depends_on } });
        }
      }
    }
  }
  return results;
}

function parseDepGraph() {
  const projectJson = readJson(path.join(GENERATED_DIR, "internal/dep-graph/project.json"));
  const modulesJson = readJson(path.join(GENERATED_DIR, "internal/dep-graph/modules.json"));
  const projectEntries = projectJson ? walkProjectNodes(projectJson.project || {}, "project") : [];
  const moduleIds = (modulesJson && modulesJson.modules) || [];
  const moduleNodes = [];

  for (const moduleId of moduleIds) {
    const modPath = path.join(GENERATED_DIR, "internal/dep-graph/" + moduleId + ".json");
    const modData = readJson(modPath);
    if (!modData) continue;
    for (const [_modId, screens] of Object.entries(modData)) {
      for (const [screenId, phases] of Object.entries(screens)) {
        for (const [phase, node] of Object.entries(phases)) {
          moduleNodes.push({ dotPath: moduleId + "." + screenId + "." + phase, moduleId, screenId, phase, node: node || null });
        }
      }
    }
  }

  return { projectEntries, moduleNodes, moduleIds };
}

function readArtifactContent(key) {
  const parts = key.split(".");
  if (parts.length < 3) return null;

  // Usecase item: project.2-business-spec.usecases.usecase-001--login
  if (parts[0] === "project" && parts[1] === "2-business-spec" && parts[2] === "usecases") {
    return readJson(path.join(GENERATED_DIR, "2-business-spec", "usecases", parts[3] + ".json"));
  }

  // Project-level: project.phase.artifact
  if (parts[0] === "project") {
    return readJson(path.join(GENERATED_DIR, parts[1], parts[2] + ".json"));
  }

  // Screen-level: moduleId.screenId.2-business-spec
  if (parts[2] === "2-business-spec") {
    return readJson(path.join(GENERATED_DIR, "2-business-spec", "screens", parts[1] + ".json"));
  }

  // Screen-level: moduleId.screenId.3-tech-spec
  if (parts[2] === "3-tech-spec") {
    return readJson(path.join(GENERATED_DIR, "3-tech-spec", "screens", parts[1] + ".json"));
  }

  // Screen-level: moduleId.screenId.4-implement
  if (parts[2] === "4-implement") {
    return readJson(path.join(GENERATED_DIR, "4-implement", "screens", parts[1] + ".json"));
  }

  return null;
}

function readArtifactSchema(key) {
  const parts = key.split(".");
  if (parts.length < 3) return null;

  // Usecase item: project.2-business-spec.usecases.usecase-001--login
  if (parts[0] === "project" && parts[1] === "2-business-spec" && parts[2] === "usecases") {
    return readJson(path.join(ASDLC_ROOT, "template", "2-business-spec", "usecases.schema.json"));
  }

  // Project-level: project.phase.artifact
  if (parts[0] === "project") {
    return readJson(path.join(ASDLC_ROOT, "template", parts[1], parts[2] + ".schema.json"));
  }

  // Screen-level: moduleId.screenId.2-business-spec → template/2-business-spec/screen.schema.json
  if (parts[2] === "2-business-spec") {
    return readJson(path.join(ASDLC_ROOT, "template", "2-business-spec", "screen.schema.json"));
  }

  // Screen-level: moduleId.screenId.3-tech-spec → template/3-tech-spec/screen.schema.json
  if (parts[2] === "3-tech-spec") {
    return readJson(path.join(ASDLC_ROOT, "template", "3-tech-spec", "screen.schema.json"));
  }

  // Screen-level: moduleId.screenId.4-implement → template/4-implement/screen.schema.json
  if (parts[2] === "4-implement") {
    return readJson(path.join(ASDLC_ROOT, "template", "4-implement", "screen.schema.json"));
  }

  return null;
}

/**
 * Read field-level dep-graph nodes for an artifact.
 * key = "project.1-foundation.prd"
 * Returns { fieldName: { ver, updated_at, stale, stale_keys, depends_on }, ... }
 */
function readFieldNodes(key) {
  const parts = key.split(".");
  if (parts.length < 3 || parts[0] !== "project") return {};
    const projectJson = readJson(path.join(GENERATED_DIR, "internal/dep-graph/project.json"));
  if (!projectJson) return {};
  const phase    = parts[1];
  const artifact = parts[2];
  const nodes    = (projectJson.project || {})[phase];
  if (!nodes) return {};
  return nodes[artifact] || {};
}

module.exports = { init, parseCLAUDE, parseDepGraph, readArtifactContent, readArtifactSchema, readFieldNodes, readJson, readText };