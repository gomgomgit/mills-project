import json
import re
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


# ── surgical patch ────────────────────────────────────────────────────────────────────────────────────────────────────

_INDEX_RE = re.compile(r"\[(\d+)\]")


def _parse_edit_path(raw: str):
    """Parse a dotted/indexed edit path into a list of segments.

        "endpoints[34].screen_id"  -> ["endpoints", 34, "screen_id"]
        "ver"                      -> ["ver"]
        "a[0][1]"                  -> ["a", 0, 1]

    Returns {"error": str} if the path is malformed.
    """
    if not isinstance(raw, str) or not raw.strip():
        return {"error": "path must be a non-empty string"}

    segs = []
    for part in raw.split("."):
        if not part:
            return {"error": f"malformed path '{raw}': empty segment"}

        # "endpoints[34]" -> ["endpoints", "34", ""];  "ver" -> ["ver"]
        pieces = _INDEX_RE.split(part)
        name = pieces[0]
        if name:
            segs.append(name)
        elif len(pieces) == 1:
            return {"error": f"malformed path '{raw}': empty key"}

        for idx in pieces[1::2]:
            segs.append(int(idx))
        for trailing in pieces[2::2]:
            if trailing:
                return {"error": f"malformed path '{raw}': unexpected '{trailing}' after index"}

    if not segs:
        return {"error": f"malformed path '{raw}'"}
    return segs


def _apply_edit(doc, segs: list, value, raw: str):
    """Set value at segs inside doc, in place. The target must already exist.

    Requiring prior existence is deliberate: a typo'd path must fail loudly rather
    than silently grow a field no template or schema knows about. Use
    artifact__write to add a field.

    Returns {"error": str} on failure, else None.
    """
    cursor = doc

    for i, seg in enumerate(segs[:-1]):
        here = _render_path(segs[: i + 1])
        if isinstance(seg, int):
            if not isinstance(cursor, list):
                return {"error": f"path '{raw}': '{here}' indexes a {type(cursor).__name__}, not a list"}
            if seg >= len(cursor):
                return {"error": f"path '{raw}': index {seg} out of range at '{here}' (length {len(cursor)})"}
        else:
            if not isinstance(cursor, dict):
                return {"error": f"path '{raw}': '{here}' is not an object"}
            if seg not in cursor:
                return {"error": f"path '{raw}': '{here}' does not exist"}
        cursor = cursor[seg]

    last = segs[-1]
    if isinstance(last, int):
        if not isinstance(cursor, list):
            return {"error": f"path '{raw}': final index {last} applied to a {type(cursor).__name__}, not a list"}
        if last >= len(cursor):
            return {"error": f"path '{raw}': index {last} out of range (length {len(cursor)})"}
    else:
        if not isinstance(cursor, dict):
            return {"error": f"path '{raw}': final key '{last}' applied to a {type(cursor).__name__}, not an object"}
        if last not in cursor:
            return {
                "error": (
                    f"path '{raw}': key '{last}' does not exist — "
                    "patch only changes existing values; use artifact__write to add a field"
                )
            }

    cursor[last] = value
    return None


def _render_path(segs: list) -> str:
    """Render a parsed segment list back to display form: ["a", 3, "b"] -> "a[3].b"."""
    out = ""
    for seg in segs:
        if isinstance(seg, int):
            out += f"[{seg}]"
        else:
            out += seg if not out else f".{seg}"
    return out


def _patch_artifact(key: str, edits: list) -> dict:
    """Change specific values inside an already-written artifact.

    Exists because _write_artifact replaces the whole document: correcting a few
    fields in a large artifact otherwise means re-emitting every untouched entry,
    which is how content gets silently dropped. A patch runs the same template
    validation, the same _diff_fields, and the same write as a full write — it only
    narrows what the caller has to restate.

    All-or-nothing: edits are applied to a deep copy, and nothing is written if any
    edit or the resulting structure is invalid.

    Args:
        key:   Dot-notation artifact key. The artifact must already exist.
        edits: [{"path": "endpoints[34].screen_id", "value": "screen-031--x"}, ...]

    Returns:
        {"ok": True, "key", "path", "changed_fields", "edits_applied"}
        {"error": str} on failure.
    """
    err = _validate_artifact_key(key)
    if err:
        return err

    if not isinstance(edits, list) or not edits:
        return {"error": "edits must be a non-empty list of {'path': str, 'value': ...}"}

    path = _content_path(key)
    if not path.exists():
        return {
            "error": (
                f"Cannot patch '{key}': artifact has not been written yet — use artifact__write"
            )
        }

    old_data = read_file(path)
    if not isinstance(old_data, dict):
        return {"error": f"Cannot patch '{key}': content root is not an object"}

    # Deep copy so old_data stays pristine: _diff_fields below compares the two, and
    # aliasing them would make changed_fields come back empty on every patch. What makes
    # the call all-or-nothing on disk is the write ordering — write_file runs only after
    # every edit and the template check have passed.
    new_data = json.loads(json.dumps(old_data))

    for i, edit in enumerate(edits):
        if not isinstance(edit, dict):
            return {"error": f"edits[{i}] must be an object with 'path' and 'value'"}
        if "path" not in edit or "value" not in edit:
            return {"error": f"edits[{i}] must have both 'path' and 'value'"}

        segs = _parse_edit_path(edit["path"])
        if isinstance(segs, dict):
            return {"error": f"edits[{i}]: " + segs["error"]}

        failure = _apply_edit(new_data, segs, edit["value"], edit["path"])
        if failure:
            return {"error": f"edits[{i}]: " + failure["error"]}

    # Same template validation as a full write — a patch cannot bypass it.
    tmpl_path = _template_path(key)
    if tmpl_path.exists():
        errors = _validate_structure(new_data, read_file(tmpl_path))
        if errors:
            return {"error": f"Validation failed for '{key}': " + "; ".join(errors)}

    # Same changed_fields contract as a full write, so dep-graph tracking is identical.
    schema_file = _schema_path(key)
    if schema_file.exists():
        changed_fields = _diff_fields(old_data, new_data, read_file(schema_file))
    else:
        changed_fields = []

    try:
        write_file(path, new_data)
    except Exception as e:
        return {"error": str(e)}

    return {
        "ok":             True,
        "key":            key,
        "path":           str(path),
        "changed_fields": changed_fields,
        "edits_applied":  len(edits),
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
    def artifact__patch(artifact_key: str, edits: list):
        """Change specific values inside an already-written artifact.

        Prefer this over artifact__write when only a few fields change in a large
        artifact: write replaces the whole document, so restating every untouched
        entry is both wasteful and how content gets silently dropped. Same template
        validation and same changed_fields as a full write.

        edits: [{"path": "endpoints[34].screen_id", "value": "screen-031--x"}, ...]

        Paths use dots for keys and [n] for list indices ("a.b[2].c"). Every path
        must already exist — a typo fails loudly instead of adding a stray field;
        use artifact__write to add or remove fields. All-or-nothing: if any edit
        fails, nothing is written.
        """
        return _patch_artifact(artifact_key, edits)

    @mcp.tool()
    def artifact__read_scheme(artifact_key: str):
        """Read field descriptions for an artifact. Returns {"data": None} if no schema exists."""
        return _read_artifact_scheme(artifact_key)
