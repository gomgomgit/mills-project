"use strict";

const fs   = require("fs");
const path = require("path");
const url  = require("url");
const readers = require("./readers");

let DASHBOARD_DIR = "";
let PROJECT_ROOT  = "";
let GENERATED_DIR = "";

const MIME = {
  ".html": "text/html; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".css":  "text/css; charset=utf-8",
  ".js":   "application/javascript; charset=utf-8",
};

function init(config) {
  DASHBOARD_DIR = config.DASHBOARD_DIR;
  PROJECT_ROOT  = config.PROJECT_ROOT;
  GENERATED_DIR = config.GENERATED_DIR;
}

function json(res, data, status) {
  res.writeHead(status || 200, { "Content-Type": MIME[".json"], "Access-Control-Allow-Origin": "*" });
  res.end(JSON.stringify(data, null, 2));
}

function createHandler(getCache) {
  return function handler(req, res) {
    const parsed   = url.parse(req.url || "/");
    const pathname = parsed.pathname || "/";

    res.setHeader("Access-Control-Allow-Origin", "*");

    // ── GET /api/data ─────────────────────────────────────────────────────────
    if (pathname === "/api/data") {
      return json(res, getCache());
    }

    // ── GET /api/artifact?key=project.1-foundation.prd ───────────────────────
    if (pathname === "/api/artifact") {
      const params     = new URLSearchParams(parsed.query || "");
      const key        = params.get("key");
      if (!key) return json(res, { error: "Missing key" }, 400);

      const content    = readers.readArtifactContent(key);
      const schema     = readers.readArtifactSchema(key);
      const fieldNodes = readers.readFieldNodes(key);
      return json(res, { key, content, schema, fieldNodes });
    }

    // ── GET /api/screen-files ─────────────────────────────────────────────────
    if (pathname === "/api/screen-files") {
      const screensDir = path.join(GENERATED_DIR, "2-business-spec", "screens");
      let ids = [], htmlIds = [];
      try {
        ids = fs.readdirSync(screensDir)
          .filter(f => f.endsWith(".json"))
          .map(f => f.replace(".json", ""));
      } catch {}
      try {
        htmlIds = fs.readdirSync(path.join(screensDir, "html"))
          .filter(f => f.endsWith(".html"))
          .map(f => f.replace(".html", ""));
      } catch {}
      return json(res, { ids, htmlIds });
    }

    // ── GET /api/usecase-files ────────────────────────────────────────────────
    if (pathname === "/api/usecase-files") {
      const usecasesDir = path.join(GENERATED_DIR, "2-business-spec", "usecases");
      let ids = [];
      try {
        ids = fs.readdirSync(usecasesDir)
          .filter(f => f.endsWith(".json"))
          .map(f => f.replace(".json", ""));
      } catch {}
      return json(res, { ids });
    }

    // ── GET /api/impl-screen-files ────────────────────────────────────────────
    if (pathname === "/api/impl-screen-files") {
      const screensDir = path.join(GENERATED_DIR, "4-implement", "screens");
      let ids = [];
      try {
        ids = fs.readdirSync(screensDir)
          .filter(f => f.endsWith(".json"))
          .map(f => f.replace(".json", ""));
      } catch {}
      return json(res, { ids });
    }

    // ── GET /api/impl-screens ─────────────────────────────────────────────────
    if (pathname === "/api/impl-screens") {
      const screensDir = path.join(GENERATED_DIR, "4-implement", "screens");
      let screens = [];
      try {
        const files = fs.readdirSync(screensDir).filter(f => f.endsWith(".json"));
        for (const f of files) {
          try {
            const data = JSON.parse(fs.readFileSync(path.join(screensDir, f), "utf8"));
            screens.push({
              id:           data.id           || f.replace(".json", ""),
              name:         data.name         || "",
              module_id:    data.module_id    || "",
              status:       data.status       || "",
              test_results: data.test_results || null,
            });
          } catch {}
        }
      } catch {}
      return json(res, { screens });
    }

    // ── GET /api/uiux-preview ─────────────────────────────────────────────────
    if (pathname === "/api/uiux-preview") {
      const uiDir = path.join(GENERATED_DIR, "1-foundation", "uiux-spec");
      let files = [];
      try {
        files = fs.readdirSync(uiDir)
          .filter(f => f.endsWith(".html"))
          .map(f => ({ name: f, url: `/uiux-preview/${f}` }));
      } catch {}
      return json(res, { files });
    }

    // ── GET /uiux-preview/** (kept for compat) ───────────────────────────────
    const uiMatch = pathname.match(/^\/uiux-preview\/(.+)$/);
    if (uiMatch) {
      const rel      = uiMatch[1];
      if (rel.includes("..")) { res.writeHead(403); return res.end("Forbidden"); }
      const filePath = path.join(GENERATED_DIR, "1-foundation", "uiux-spec", rel);
      if (!fs.existsSync(filePath)) { res.writeHead(404); return res.end("Not found"); }
      const ext  = path.extname(filePath);
      const mime = MIME[ext] || "application/octet-stream";
      res.writeHead(200, { "Content-Type": mime });
      return res.end(fs.readFileSync(filePath));
    }

    // ── GET /preview/** (any file under GENERATED_DIR) ────────────────────────
    const previewMatch = pathname.match(/^\/preview\/(.+)$/);
    if (previewMatch) {
      const rel = previewMatch[1];
      if (rel.includes("..")) { res.writeHead(403); return res.end("Forbidden"); }
      const filePath = path.join(GENERATED_DIR, rel);
      if (!fs.existsSync(filePath)) { res.writeHead(404); return res.end("Not found"); }
      const ext  = path.extname(filePath);
      const mime = MIME[ext] || "application/octet-stream";
      res.writeHead(200, { "Content-Type": mime });
      return res.end(fs.readFileSync(filePath));
    }

    // ── Static: /css/*.css and /js/*.js ──────────────────────────────────────
    const staticMatch = pathname.match(/^\/(css|js)\/(.+)$/);
    if (staticMatch) {
      const [, subdir, filename] = staticMatch;
      if (filename.includes("..")) { res.writeHead(403); return res.end("Forbidden"); }
      const ext  = path.extname(filename);
      const mime = MIME[ext];
      if (!mime)   { res.writeHead(403); return res.end("Forbidden"); }
      const fp = path.join(DASHBOARD_DIR, subdir, filename);
      if (!fs.existsSync(fp)) { res.writeHead(404); return res.end("Not found"); }
      res.writeHead(200, { "Content-Type": mime });
      return res.end(fs.readFileSync(fp));
    }

    // ── Root ──────────────────────────────────────────────────────────────────
    if (pathname === "/" || pathname === "/index.html") {
      const indexPath = path.join(DASHBOARD_DIR, "index.html");
      if (!fs.existsSync(indexPath)) { res.writeHead(404); return res.end("index.html not found"); }
      res.writeHead(200, { "Content-Type": MIME[".html"] });
      return res.end(fs.readFileSync(indexPath));
    }

    res.writeHead(404);
    res.end("Not found");
  };
}

module.exports = { init, createHandler };
