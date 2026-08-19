#!/usr/bin/env node
/**
 * Agentic SDLC v101 -- Dashboard Server
 *
 * Usage (from project root):
 *   node .asdlc/dashboard/server.js
 *   node .asdlc/dashboard/server.js --port 7702
 *   node .asdlc/dashboard/server.js --generated-folder generated_example
 *   node .asdlc/dashboard/server.js --generated-folder generated_example/SimpleTodo
 */

"use strict";

const http = require("http");
const path = require("path");

const args = process.argv.slice(2);
function getArg(flag, def) {
  const i = args.indexOf(flag);
  return i !== -1 && args[i + 1] ? args[i + 1] : def;
}

const PORT       = parseInt(getArg("--port", "7701"));
const GEN_FOLDER = getArg("--generated-folder", "generated");

const PROJECT_ROOT  = path.resolve(__dirname, "../..");
const ASDLC_ROOT    = path.resolve(__dirname, "..");
const DASHBOARD_DIR = __dirname;
const GENERATED_DIR = path.join(ASDLC_ROOT, GEN_FOLDER);

const readers = require("./lib/readers");
const builder = require("./lib/builder");
const routes  = require("./lib/routes");
const watcher = require("./lib/watcher");

const sharedConfig = { PROJECT_ROOT, ASDLC_ROOT, DASHBOARD_DIR, GENERATED_DIR };
readers.init(sharedConfig);
routes.init(sharedConfig);

const G = ".asdlc/" + GEN_FOLDER;
const WATCH_FILES = [
  "CLAUDE.md",
  G + "/internal/dep-graph/project.json",
  G + "/internal/dep-graph/modules.json",
];
const WATCH_DIRS = [
  G + "/1-foundation",
  G + "/2-business-spec",
  G + "/3-tech-spec",
  G + "/internal/dep-graph",
];

watcher.init({ PROJECT_ROOT, WATCH_FILES, WATCH_DIRS });

const server = http.createServer(routes.createHandler(builder.getCache));

console.log("");
console.log("  +----------------------------------------------+");
console.log("  |   Agentic SDLC v101 -- Dashboard Server      |");
console.log("  +----------------------------------------------+");
console.log("  Dashboard  ->  http://localhost:" + PORT);
console.log("  API        ->  http://localhost:" + PORT + "/api/data");
console.log("  Generated  ->  " + GENERATED_DIR);
console.log("");

builder.refreshCache();
setInterval(function() { watcher.checkFiles(builder.refreshCache); }, 1000);

server.listen(PORT, "127.0.0.1", function() {
  console.log("  Watching " + WATCH_FILES.length + " files + " + WATCH_DIRS.length + " dirs...\n");
});

server.on("error", function(err) {
  if (err.code === "EADDRINUSE") {
    console.error("\n  Port " + PORT + " in use. Try: node server.js --port 7702");
  } else {
    console.error("\n  Server error:", err.message);
  }
  process.exit(1);
});

process.on("SIGINT", function() {
  console.log("\n  Server stopped.");
  process.exit(0);
});
