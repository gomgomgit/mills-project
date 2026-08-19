import json
from ..commons.paths import (
    project_json, modules_json, module_json,
    artifact_path, artifact_template, artifact_schema,
    module_artifact_path, module_artifact_template, module_artifact_schema,
)
from ..commons.json_ops import read_file, write_file


# ── routing helpers ───────────────────────────────────────────────────────────────────────────────────────────────────

def _content_path(key: str):
    """Return the content file path for a given artifact key."""
    if key.startswith("project."):
        return artifact_path(key)
    return module_artifact_path(key)


def _schema_path(key: str):
    """Return the schema file path for a given artifact key."""
    if key.startswith("project."):
        return artifact_schema(key)
    return module_artifact_schema(key)


def _template_path(key: str):
    """Return the template file path for a given artifact key."""
    if key.startswith("project."):
        return artifact_template(key)
    return module_artifact_template(key)


# ── guard helper ──────────────────────────────────────────────────────────────────────────────────────────────────────

def _validate_artifact_key(key: str) -> dict | None:
    """Return {"error": ...} if key is invalid, else None.

    Valid artifact key rules:
      - Non-empty string
      - project keys  (starts with "project."): at least 3 dot-separated parts
      - module keys   (everything else):        exactly 3 dot-separated parts

    Examples:
        "project.1-foundation.prd"                    -> valid
        "module-001.screen-001--login.2-spec"         -> valid
        "project.prd"                                 -> invalid (only 2 parts)
        "module-001.screen-001.2-spec.extra"          -> invalid (module must be exactly 3)
        ""                                            -> invalid (empty)
    """
    if not key:
        return {"error": "Invalid artifact_key: must be a non-empty string"}
    parts = key.split(".")
    if len(parts) < 3:
        return {
            "error": (
                f"Invalid artifact_key '{key}': "
                "expected at least 3 dot-separated parts "
                "(e.g. 'project.1-foundation.prd')"
            )
        }
    if not key.startswith("project.") and len(parts) != 3:
        return {
            "error": (
                f"Invalid artifact_key '{key}': "
                f"module keys must have exactly 3 dot-separated parts (got {len(parts)})"
            )
        }
    return None


# ── diff helper ───────────────────────────────────────────────────────────────────────────────────────────────────────

def _diff_fields(old: dict, new: dict, schema: dict) -> list:
    """Return field names from schema['_tracked'] whose value changed between old and new.

    Serialises values via json.dumps for deep comparison.
    Returns [] if schema has no _tracked section.
    """
    tracked = schema.get("_tracked", [])
    changed = []
    for field in tracked:
        old_val = json.dumps(old.get(field), sort_keys=True, ensure_ascii=False)
        new_val = json.dumps(new.get(field), sort_keys=True, ensure_ascii=False)
        if old_val != new_val:
            changed.append(field)
    return changed


# ── structure validation ──────────────────────────────────────────────────────────────────────────────────────────────

def _validate_structure(data: dict, template: dict, path: str = "") -> list:
    """Recursively validate data structure against template shape.

    Rules:
    - All keys in template must be present in data (required)
    - No extra keys in data that are not in template (unknown)
    - Types must match per template value type:
        str   → data must be str
        int   → data must be int or float (not bool)
        bool  → data must be bool
        dict  → data must be dict; recurse into keys
        list  → data must be list; if template list is non-empty, validate items:
                  [{}]  → each item must be dict with same shape (recurse)
                  [""]  → each item must be str
    - null template value → skip (no constraint on that field)
    - Empty template list ([]) → only validates that data is a list; items not validated

    Returns:
        List of error strings. Empty list = valid.
    """
    errors = []

    # Unknown keys in data (not present in template)
    for key in data:
        if key not in template:
            loc = f"{path}.{key}" if path else key
            errors.append(f"unknown key: '{loc}'")

    # Check each key defined in template
    for key, tmpl_val in template.items():
        loc = f"{path}.{key}" if path else key

        if key not in data:
            errors.append(f"missing key: '{loc}'")
            continue

        data_val = data[key]

        # null → no constraint
        if tmpl_val is None:
            continue

        # bool — must be checked before int (bool is subclass of int in Python)
        if isinstance(tmpl_val, bool):
            if not isinstance(data_val, bool):
                errors.append(f"'{loc}': expected bool, got {type(data_val).__name__}")

        # dict → recurse (empty dict {} = unconstrained, only validates type)
        elif isinstance(tmpl_val, dict):
            if not isinstance(data_val, dict):
                errors.append(f"'{loc}': expected dict, got {type(data_val).__name__}")
            elif tmpl_val:  # non-empty template → recurse into keys
                errors.extend(_validate_structure(data_val, tmpl_val, loc))

        # list
        elif isinstance(tmpl_val, list):
            if not isinstance(data_val, list):
                errors.append(f"'{loc}': expected list, got {type(data_val).__name__}")
            elif tmpl_val:  # non-empty template → has item shape info
                item_tmpl = tmpl_val[0]
                if isinstance(item_tmpl, dict):
                    # List of dicts — validate each item against item_tmpl
                    for i, item in enumerate(data_val):
                        item_loc = f"{loc}[{i}]"
                        if not isinstance(item, dict):
                            errors.append(f"'{item_loc}': expected dict, got {type(item).__name__}")
                        else:
                            errors.extend(_validate_structure(item, item_tmpl, item_loc))
                elif isinstance(item_tmpl, str):
                    # List of strings
                    for i, item in enumerate(data_val):
                        if not isinstance(item, str):
                            errors.append(f"'{loc}[{i}]': expected str, got {type(item).__name__}")

        # str
        elif isinstance(tmpl_val, str):
            if not isinstance(data_val, str):
                errors.append(f"'{loc}': expected str, got {type(data_val).__name__}")

        # numeric (int or float, but not bool)
        elif isinstance(tmpl_val, (int, float)):
            if not isinstance(data_val, (int, float)) or isinstance(data_val, bool):
                errors.append(f"'{loc}': expected number, got {type(data_val).__name__}")

    return errors


# ── MCP tool functions ────────────────────────────────────────────────────────────────────────────────────────────────

def _list_artifacts() -> list:
    """List all known artifacts and their write status.

    Project artifacts are derived from project.json structure.
    Module artifacts are derived from modules.json + each module file.

    Returns:
        [
          {
            "key":    str,                      -- dot-notation key
            "type":   "project" | "module",
            "status": "written" | "not_started" -- whether content file exists
          },
          ...
        ]
    """
    result = []

    # Project artifacts — walk project.json
    project = read_file(project_json())
    for phase_group, artifacts in project.get("project", {}).items():
        if not isinstance(artifacts, dict):
            continue
        for artifact_key in artifacts:
            key    = f"project.{phase_group}.{artifact_key}"
            status = "written" if _content_path(key).exists() else "not_started"
            result.append({"key": key, "type": "project", "status": status})

    # Module artifacts — walk modules.json → module files
    for module_id in read_file(modules_json()).get("modules", []):
        path = module_json(module_id)
        if not path.exists():
            continue
        mod_data = read_file(path)
        for _mod_id, screens in mod_data.items():
            for screen_id, phases in screens.items():
                for phase in phases:
                    key    = f"{module_id}.{screen_id}.{phase}"
                    status = "written" if _content_path(key).exists() else "not_started"
                    result.append({"key": key, "type": "module", "status": status})

    return result


def _read_artifact(key: str):
    """Read the content file for an artifact.

    Args:
        key: Dot-notation artifact key.
             Project: "project.{phase}.{artifact}"
             Module:  "{module_id}.{screen_id}.{phase}"

    Returns:
        {"data": dict} if file exists.
        {"data": None} if file does not exist.
        {"error": str} if key is invalid.
    """
    err = _validate_artifact_key(key)
    if err:
        return err
    path = _content_path(key)
    if not path.exists():
        return {"data": None}
    return {"data": read_file(path)}


def _write_artifact(key: str, data: dict) -> dict:
    """Write content to an artifact file and return what changed.

    Diffs old vs new content on fields listed in schema['_tracked'].
    If no schema exists, changed_fields is always [].

    Args:
        key:  Dot-notation artifact key.
        data: Complete new artifact content.

    Returns:
        {"ok": True, "key": str, "path": str, "changed_fields": [...]}
        {"error": str} on failure.
    """
    err = _validate_artifact_key(key)
    if err:
        return err

    path = _content_path(key)

    # Validate structure against template (skip gracefully if template absent)
    tmpl_path = _template_path(key)
    if tmpl_path.exists():
        template  = read_file(tmpl_path)
        errors    = _validate_structure(data, template)
        if errors:
            return {"error": f"Validation failed for '{key}': " + "; ".join(errors)}

    # Read old content for diff
    old_data = read_file(path) if path.exists() else {}

    # Determine changed fields via schema
    schema_file = _schema_path(key)
    if schema_file.exists():
        schema         = read_file(schema_file)
        changed_fields = _diff_fields(old_data, data, schema)
    else:
        changed_fields = []

    try:
        write_file(path, data)
    except Exception as e:
        return {"error": str(e)}

    return {
        "ok":             True,
        "key":            key,
        "path":           str(path),
        "changed_fields": changed_fields,
    }


def _read_artifact_scheme(key: str):
    """Read the schema (field descriptions) for an artifact.

    Args:
        key: Dot-notation artifact key.

    Returns:
        {"data": dict} if schema file exists.
        {"data": None} if no schema file exists.
        {"error": str} if key is invalid.
    """
    err = _validate_artifact_key(key)
    if err:
        return err
    path = _schema_path(key)
    if not path.exists():
        return {"data": None}
    return {"data": read_file(path)}

# ── register ──────────────────────────────────────────────────────────────────

def register(mcp) -> None:

    @mcp.tool()
    def artifact__list():
        """List all known artifacts and their write status."""
        return _list_artifacts()

    @mcp.tool()
    def artifact__read(artifact_key: str):
        """Read the content of an artifact. Returns {"data": None} if not yet written."""
        return _read_artifact(artifact_key)

    @mcp.tool()
    def artifact__write(artifact_key: str, data: dict):
        """Write artifact content. Returns changed_fields for dep-graph tracking."""
        return _write_artifact(artifact_key, data)

    @mcp.tool()
    def artifact__read_scheme(artifact_key: str):
        """Read field descriptions for an artifact. Returns {"data": None} if no schema exists."""
        return _read_artifact_scheme(artifact_key)
