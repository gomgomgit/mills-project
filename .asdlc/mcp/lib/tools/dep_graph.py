from datetime import datetime, timezone
from ..commons.paths import (
    project_json, modules_json, module_json,
    module_template,
)
from ..commons.json_ops import read_file, write_file


# ── helpers ───────────────────────────────────────────────────────────────────────────────────────────────────────────

def _now() -> str:
    """Return current UTC time as ISO 8601 string."""
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def _blank_leaf_node() -> dict:
    """Return a fresh leaf node with ver=0 and empty dependency state."""
    return {
        "ver":        0,
        "updated_at": None,
        "depends_on": {},
        "stale":      False,
        "stale_keys": [],
    }


def _init_module_file(module_id: str) -> None:
    """Create module-{id}.json and register it in modules.json if not yet present."""
    if not module_json(module_id).exists():
        write_file(module_json(module_id), {module_id: {}})
    data = read_file(modules_json())
    if module_id not in data["modules"]:
        data["modules"].append(module_id)
        write_file(modules_json(), data)


def _init_screen(module_id: str, screen_id: str) -> None:
    """Add screen_id entry to module file if not yet present.

    Phase structure is read from the module template so adding phases
    requires only a template update, not a code change.
    """
    _init_module_file(module_id)
    data    = read_file(module_json(module_id))
    screens = data.get(module_id, {})
    if screen_id not in screens:
        tmpl         = read_file(module_template())
        outer        = next(iter(tmpl.values()))
        blank_screen = {phase: None for phase in next(iter(outer.values()))}
        screens[screen_id] = blank_screen
        data[module_id]    = screens
        write_file(module_json(module_id), data)


def _resolve_dep_ver(key: str, project: dict, screen_phases: dict):
    """Resolve a dep key to the current ver of that node.

    Supports three prefixes:
      "self.*"    -- another phase on the same screen
      "module.*"  -- a phase on a different screen (module-*.json)
      "project.*" -- a node in project.json
    Returns None if the dep cannot be resolved.
    """
    if key.startswith("self."):
        phase_name = key.removeprefix("self.")
        node = screen_phases.get(phase_name)
        if node is None:
            return None
        return node.get("ver")

    if key.startswith("module."):
        parts = key.split(".", 3)
        if len(parts) != 4:
            return None
        _, module_id, screen_id, phase = parts
        path = module_json(module_id)
        if not path.exists():
            return None
        try:
            data = read_file(path)
            for _mod_id, screens in data.items():
                if screen_id in screens:
                    node = screens[screen_id].get(phase)
                    if node is None:
                        return None
                    return node.get("ver")
        except Exception:
            return None
        return None

    # project.* -- navigate nested dicts to the leaf node
    parts = key.split(".")
    val   = project
    for part in parts:
        if isinstance(val, dict) and part in val:
            val = val[part]
        else:
            return None
    if isinstance(val, dict):
        return val.get("ver")
    return val


def _compute_stale(depends_on: dict, project: dict, screen_phases: dict) -> tuple:
    """Compare recorded dep vers against current vers.

    Returns (stale: bool, stale_keys: list[str]).
    Deps that cannot be resolved (not yet started) are skipped.
    """
    stale_keys = []
    for dep_key, recorded_ver in depends_on.items():
        current_ver = _resolve_dep_ver(dep_key, project, screen_phases)
        if current_ver is None:
            continue
        if recorded_ver != current_ver:
            stale_keys.append(dep_key)
    return (len(stale_keys) > 0, stale_keys)



def _dep_key_exists(key: str, project: dict, screen_phases: dict) -> bool:
    """Return True if the dep key is registered in the structure, even if not yet written (null).

    This distinguishes two cases that _resolve_dep_ver cannot tell apart:
      - NOT STARTED: key path exists, value is None  → True  (silent skip in snapshot)
      - NOT FOUND:   key path does not exist at all  → False (error: unknown key)

    Supports the same three prefixes as _resolve_dep_ver:
      "self.*"    — check phase name in screen_phases
      "module.*"  — check module file → screen → phase path on disk
      "project.*" — check key path in project dict
    """
    if key.startswith("self."):
        return key.removeprefix("self.") in screen_phases

    if key.startswith("module."):
        parts = key.split(".", 3)
        if len(parts) != 4:
            return False
        _, module_id, screen_id, phase = parts
        path = module_json(module_id)
        if not path.exists():
            return False
        try:
            data = read_file(path)
            for _mod_id, screens in data.items():
                if screen_id in screens:
                    return phase in screens[screen_id]
        except Exception:
            return False
        return False

    # project.* — traverse nested dicts; return True even if leaf is None (not started)
    parts = key.split(".")
    val   = project
    for part in parts:
        if isinstance(val, dict) and part in val:
            val = val[part]
        else:
            return False
    return True


def _snapshot_depends_on(node: dict, depends_on: list, project: dict, screen_phases: dict = None) -> list:
    """Resolve dep vers and write them into node.depends_on. Reset stale state.

    Distinguishes two cases when a dep key cannot be resolved:
      - NOT STARTED (registered but null) → silently skipped; dep will be captured later
      - NOT FOUND   (not in structure)    → returned as unknown; caller must error out

    Returns:
        List of unknown dep keys (not registered in the structure at all).
        Empty list = all deps are either resolved or not-started (silently skipped).
        If non-empty, the caller MUST return an error and NOT write to disk.

    Mutates node in-place ONLY when unknown list is empty.
    """
    if screen_phases is None:
        screen_phases = {}

    unknown = [k for k in depends_on if not _dep_key_exists(k, project, screen_phases)]
    if unknown:
        return unknown   # caller handles error; node NOT modified

    resolved = {}
    for dep_key in depends_on:
        ver = _resolve_dep_ver(dep_key, project, screen_phases)
        if ver is not None:
            resolved[dep_key] = ver
    node["depends_on"] = resolved
    node["stale"]      = False
    node["stale_keys"] = []
    return []


def _walk_project_nodes(data: dict, path_parts: list = None):
    """Recursively yield (dot_path, node) for every leaf node in a project.json sub-tree.

    A leaf node is any dict containing a "ver" key.
    Null values (not-started) are skipped.
    """
    if path_parts is None:
        path_parts = []
    for key, val in data.items():
        current_path = path_parts + [key]
        if val is None:
            continue
        if isinstance(val, dict) and "ver" in val:
            yield ".".join(current_path), val
        elif isinstance(val, dict):
            yield from _walk_project_nodes(val, current_path)


def _validate_node_key(key: str) -> dict | None:
    """Validate a dep-graph node key at MCP entry point.

    Returns {"error": ...} if invalid, None if valid.
    Valid forms:
      "project.{phase}.{artifact}"       (>= 3 parts, starts with 'project.')
      "{module_id}.{screen_id}.{phase}"  (exactly 3 parts, first part starts with 'module-')
    """
    if not key:
        return {"error": "artifact_key must be a non-empty string"}
    parts = key.split(".")
    if len(parts) < 3:
        return {"error": f"Invalid artifact_key '{key}': expected at least 3 dot-separated parts (got {len(parts)})"}
    if key.startswith("project."):
        return None
    if not parts[0].startswith("module-"):
        return {"error": f"Invalid artifact_key '{key}': must start with 'project.' or 'module-'"}
    if len(parts) != 3:
        return {"error": f"Invalid artifact_key '{key}': module node key must have exactly 3 dot-separated parts (got {len(parts)})"}
    return None


# ── write helpers ─────────────────────────────────────────────────────────────────────────────────────────────────────

def _write_project_node(key: str, changed_fields: list, depends_on: list) -> dict:
    """Bump ver and snapshot depends_on for a project.json node.

    changed_fields == [] : single-node -- bump the node at key directly.
    changed_fields == [...]: field-group -- bump each named field under key.
    """
    project = read_file(project_json())

    parts    = key.split(".")
    leaf_key = parts[-1]
    parent   = project
    for part in parts[:-1]:
        if isinstance(parent, dict) and part in parent:
            parent = parent[part]
        else:
            return {"error": f"Path not found in project.json: '{key}'"}

    bumped = []

    if not changed_fields:
        # Single-node: validate deps first (sentinel), then init and mutate
        _sentinel = _blank_leaf_node()
        unknown = _snapshot_depends_on(_sentinel, depends_on, project)
        if unknown:
            return {"error": f"Unknown dep key(s) in depends_on for '{key}': {', '.join(unknown)}"}
        if parent.get(leaf_key) is None:
            parent[leaf_key] = _blank_leaf_node()
        node = parent[leaf_key]
        if not isinstance(node, dict):
            return {"error": f"Not a leaf node: '{key}'"}
        if "ver" not in node:
            # Field-group container (e.g. usecase-index → {"usecases": {ver,...}}).
            # Auto-bump all child leaf nodes instead of failing.
            for field, child in node.items():
                if isinstance(child, dict) and "ver" in child:
                    child["ver"]        = (child.get("ver") or 0) + 1
                    child["updated_at"] = _now()
                    _snapshot_depends_on(child, depends_on, project)
                    bumped.append({"node": f"{key}.{field}", "ver": child["ver"]})
            if not bumped:
                return {"error": f"Not a leaf node and no child leaf nodes found: '{key}'"}
        else:
            node["ver"]        = (node.get("ver") or 0) + 1
            node["updated_at"] = _now()
            _snapshot_depends_on(node, depends_on, project)   # already validated above
            bumped.append({"node": key, "ver": node["ver"]})

    else:
        # Field-group: init container if null, then bump each field
        # Validate depends_on once before the loop — deps are identical for all fields
        _sentinel = _blank_leaf_node()
        unknown = _snapshot_depends_on(_sentinel, depends_on, project)
        if unknown:
            return {"error": f"Unknown dep key(s) in depends_on for '{key}': {', '.join(unknown)}"}

        if parent.get(leaf_key) is None:
            parent[leaf_key] = {}
        container = parent[leaf_key]
        for field in changed_fields:
            if container.get(field) is None:
                container[field] = _blank_leaf_node()
            node               = container[field]
            node["ver"]        = (node.get("ver") or 0) + 1
            node["updated_at"] = _now()
            _snapshot_depends_on(node, depends_on, project)
            bumped.append({"node": f"{key}.{field}", "ver": node["ver"]})

    write_file(project_json(), project)
    return {"ok": True, "bumped": bumped}


def _write_module_node(key: str, depends_on: list, files: dict = None) -> dict:
    """Bump ver and snapshot depends_on for a module screen phase node.

    key format: "{module_id}.{screen_id}.{phase}"
    All init (module file, screen entry) is automatic.
    For 4-implement nodes: also records files produced.
    """
    parts = key.split(".")
    if len(parts) != 3:
        return {
            "error": (
                f"Module node key must be '{{module_id}}.{{screen_id}}.{{phase}}', "
                f"got: '{key}'"
            )
        }

    module_id, screen_id, phase = parts

    _init_screen(module_id, screen_id)

    mod_data = read_file(module_json(module_id))
    project  = read_file(project_json())

    for _mod_id, screens in mod_data.items():
        if screen_id not in screens:
            continue
        phases   = screens[screen_id]
        existing = phases.get(phase) or {}
        now      = _now()
        new_ver  = (existing.get("ver") or 0) + 1

        node = {
            "ver":        new_ver,
            "updated_at": now,
            "depends_on": {},
            "stale":      False,
            "stale_keys": [],
        }

        unknown = _snapshot_depends_on(node, depends_on, project, phases)
        if unknown:
            return {"error": f"Unknown dep key(s) in depends_on for '{key}': {', '.join(unknown)}"}

        if phase == "4-implement":
            node["files"] = files or {
                "backend": [], "frontend": [], "migration": [], "test": []
            }

        phases[phase]       = node
        mod_data[_mod_id]   = screens
        write_file(module_json(module_id), mod_data)
        return {"ok": True, "bumped": [{"node": key, "ver": new_ver}]}

    return {"error": f"Screen '{screen_id}' not found in {module_id}.json"}


# ── MCP tool functions ────────────────────────────────────────────────────────────────────────────────────────────────

def _write_node(key: str, changed_fields: list, depends_on: list, files: dict = None) -> dict:
    """Write a dep-graph node and snapshot its depends_on.

    Routes to project.json or module-{id}.json based on key prefix.
    All initialisation is automatic -- no separate init call needed.

    Args:
        key:            Dot-notation path.
                        Project node:  "project.{phase}.{artifact}"
                        Field-group:   "project.{phase}.{artifact}" + non-empty changed_fields
                        Module node:   "{module_id}.{screen_id}.{phase}"
        changed_fields: []            -- single-node: bump the node at key.
                        ["f1", "f2"]  -- field-group: bump only the named fields.
        depends_on:     Keys (str list) this node was built from.
                        Versions are resolved live from the dep-graph.
        files:          Only for module 4-implement nodes.
                        {"backend": [...], "frontend": [...], "migration": [...], "test": [...]}

    Returns:
        {"ok": True, "bumped": [{"node": str, "ver": int}, ...]}
        {"error": str} on failure.
    """
    if key.startswith("project."):
        return _write_project_node(key, changed_fields, depends_on)

    if key.split(".")[0].startswith("module-"):
        return _write_module_node(key, depends_on, files)

    return {
        "error": (
            f"Cannot determine node type from key: '{key}'. "
            f"Expected 'project.*' or '{{module_id}}.{{screen_id}}.{{phase}}'."
        )
    }


def _sync_stale_status() -> dict:
    """Compute and persist stale status for every node in the dep-graph.

    Reads project.json and all module files listed in modules.json.
    Writes stale/stale_keys back to any node whose state has changed.

    Returns:
        {
          "stale":       [{"path": str, "stale_keys": [...]}],
          "clean":       ["dot.path", ...],
          "not_started": ["dot.path", ...]
        }
    """
    project       = read_file(project_json())
    result        = {"stale": [], "clean": [], "not_started": []}
    project_dirty = False

    # Walk project.json leaf nodes
    for dot_path, node in _walk_project_nodes(project.get("project", {}), ["project"]):
        depends_on = node.get("depends_on", {})
        if not depends_on:
            if node.get("stale") or node.get("stale_keys"):
                node["stale"]      = False
                node["stale_keys"] = []
                project_dirty      = True
            result["clean"].append(dot_path)
            continue
        stale, stale_keys = _compute_stale(depends_on, project, {})
        if node.get("stale") != stale or node.get("stale_keys") != stale_keys:
            node["stale"]      = stale
            node["stale_keys"] = stale_keys
            project_dirty      = True
        if stale:
            result["stale"].append({"path": dot_path, "stale_keys": stale_keys})
        else:
            result["clean"].append(dot_path)

    # Collect not_started project entries (null nodes)
    for phase_group, artifacts in project.get("project", {}).items():
        if not isinstance(artifacts, dict):
            continue
        for artifact_key, artifact_val in artifacts.items():
            if artifact_val is None:
                result["not_started"].append(f"project.{phase_group}.{artifact_key}")

    if project_dirty:
        write_file(project_json(), project)

    # Walk module nodes via modules.json index
    for module_id in read_file(modules_json()).get("modules", []):
        path = module_json(module_id)
        if not path.exists():
            continue
        mod_data     = read_file(path)
        module_dirty = False
        for _mod_id, screens in mod_data.items():
            for screen_id, phases in screens.items():
                for phase, node in phases.items():
                    label = f"{module_id}.{screen_id}.{phase}"
                    if node is None:
                        result["not_started"].append(label)
                        continue
                    depends_on = node.get("depends_on", {})
                    stale, stale_keys = _compute_stale(depends_on, project, phases)
                    if node.get("stale") != stale or node.get("stale_keys") != stale_keys:
                        node["stale"]      = stale
                        node["stale_keys"] = stale_keys
                        module_dirty       = True
                    if stale:
                        result["stale"].append({"path": label, "stale_keys": stale_keys})
                    else:
                        result["clean"].append(label)
        if module_dirty:
            write_file(path, mod_data)

    return {"ok": True, **result}


def _get_stale_nodes() -> list:
    """Read stored stale status from dep-graph files. No computation, no write.

    Walks project.json and all module files and returns every node
    whose stored stale field is True.

    Returns:
        [{"path": str, "stale_keys": [...]}, ...]
    """
    result  = []
    project = read_file(project_json())

    for dot_path, node in _walk_project_nodes(project.get("project", {}), ["project"]):
        if node.get("stale"):
            result.append({"path": dot_path, "stale_keys": node.get("stale_keys", [])})

    for module_id in read_file(modules_json()).get("modules", []):
        path = module_json(module_id)
        if not path.exists():
            continue
        for _mod_id, screens in read_file(path).items():
            for screen_id, phases in screens.items():
                for phase, node in phases.items():
                    if node and node.get("stale"):
                        result.append({
                            "path":       f"{module_id}.{screen_id}.{phase}",
                            "stale_keys": node.get("stale_keys", []),
                        })
    return result


# ── register ──────────────────────────────────────────────────────────────────

def register(mcp) -> None:

    @mcp.tool()
    def dep_graph__track_node(artifact_key: str, changed_fields: list, depends_on: list, files: dict = None):
        """Track a dep-graph node: bump version and snapshot depends_on.

        Performs two tightly-coupled operations as one atomic transaction:
          1. Bump ver of the node (and named field-group if changed_fields given).
          2. Snapshot depends_on — resolve current vers of deps and store in node.

        These are always performed together; there is no use case for one without
        the other. Splitting them would risk an inconsistent intermediate state
        (node with new ver but stale depends_on snapshot).
        """
        err = _validate_node_key(artifact_key)
        if err:
            return err
        return _write_node(artifact_key, changed_fields, depends_on, files)

    @mcp.tool()
    def dep_graph__sync_stale_status():
        """Compute and persist stale status for every node in the dep-graph."""
        return _sync_stale_status()

    @mcp.tool()
    def dep_graph__get_stale_nodes():
        """Return all nodes currently marked as stale. Read-only — no computation."""
        return _get_stale_nodes()
