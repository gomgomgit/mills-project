"use strict";

const fs   = require("fs");
const path = require("path");

let PROJECT_ROOT = "";
let watchFiles   = [];
let watchDirs    = [];

// Last known mtime per path
const mtimes = {};

function init({ PROJECT_ROOT: root, WATCH_FILES, WATCH_DIRS }) {
  PROJECT_ROOT = root;
  watchFiles   = WATCH_FILES  || [];
  watchDirs    = WATCH_DIRS   || [];
}

function mtime(filePath) {
  try { return fs.statSync(filePath).mtimeMs; } catch { return 0; }
}

function dirMtime(dirPath) {
  try {
    const files = fs.readdirSync(dirPath);
    let latest = 0;
    for (const f of files) {
      const t = mtime(path.join(dirPath, f));
      if (t > latest) latest = t;
    }
    return latest;
  } catch { return 0; }
}

/**
 * Check whether any watched file/dir has changed.
 * If yes, call onChanged().
 */
function checkFiles(onChanged) {
  let changed = false;

  for (const f of watchFiles) {
    const t = mtime(path.join(PROJECT_ROOT, f));
    if (mtimes[f] !== t) { mtimes[f] = t; changed = true; }
  }

  for (const d of watchDirs) {
    const t = dirMtime(path.join(PROJECT_ROOT, d));
    if (mtimes[d] !== t) { mtimes[d] = t; changed = true; }
  }

  if (changed) onChanged();
}

module.exports = { init, checkFiles };
