from pathlib import Path

# Root of the .asdlc directory -- four levels up from this file
# (lib/commons/paths.py -> lib/commons/ -> lib/ -> mcp/ -> .asdlc/)
ASDLC_ROOT = Path(__file__).parents[3]


# ── path templates ────────────────────────────────────────────────────────────────────────────────────────────────────
# Single source of truth for every file/folder location in the project.
# Values are str.format() templates; placeholders are filled by each function.

_TEMPLATES: dict[str, str] = {
    # dep-graph base dirs
    "dep_graph_dir":            "generated/internal/dep-graph",
    "template_dir":             "template/internal/dep-graph",
    # dep-graph files
    "project_json":             "generated/internal/dep-graph/project.json",
    "modules_json":             "generated/internal/dep-graph/modules.json",
    "module_json":              "generated/internal/dep-graph/{module_id}.json",
    "module_template":          "template/internal/dep-graph/module.json",
    # project artifact paths  (key format: "project.{phase}.{artifact}")
    "artifact_path":            "generated/{phase}/{artifact}.json",
    "artifact_template":        "template/{phase}/{artifact}.json",
    "artifact_schema":          "template/{phase}/{artifact}.schema.json",
    # project item artifact paths (key format: "project.{phase}.{subfolder}.{item_id}")
    # e.g. "project.2-business-spec.usecases.usecase-001--name"
    #       -> generated/2-business-spec/usecases/usecase-001--name.json
    # template/schema use the subfolder name (shared across all items of that type)
    "item_artifact_path":       "generated/{phase}/{subfolder}/{item_id}.json",
    "item_artifact_template":   "template/{phase}/{subfolder}.json",
    "item_artifact_schema":     "template/{phase}/{subfolder}.schema.json",
    # module artifact paths   (key format: "{module_id}.{screen_id}.{phase}")
    "module_artifact_path":     "generated/{phase}/screens/{screen_id}.json",
    "module_artifact_template": "template/{phase}/screen.json",
    "module_artifact_schema":   "template/{phase}/screen.schema.json",
}


def _r(name: str, **kwargs) -> Path:
    """Resolve a named template to an absolute Path under ASDLC_ROOT."""
    return ASDLC_ROOT / _TEMPLATES[name].format(**kwargs)


# ── base dirs ─────────────────────────────────────────────────────────────────────────────────────────────────────────

def dep_graph_dir() -> Path:
    """Path to the dep-graph directory (project.json, modules.json, module-*.json)."""
    return _r("dep_graph_dir")


def template_dir() -> Path:
    """Path to the internal dep-graph template directory."""
    return _r("template_dir")


# ── dep-graph files ───────────────────────────────────────────────────────────────────────────────────────────────────

def project_json() -> Path:
    """Path to project.json (pre-seeded, always present)."""
    return _r("project_json")


def modules_json() -> Path:
    """Path to modules.json index (pre-seeded, always present)."""
    return _r("modules_json")


def module_json(module_id: str) -> Path:
    """Path to module-{id}.json."""
    return _r("module_json", module_id=module_id)


# ── dep-graph templates ───────────────────────────────────────────────────────────────────────────────────────────────

def module_template() -> Path:
    """Path to the module.json template (defines screen phase structure)."""
    return _r("module_template")


# ── project artifact files ────────────────────────────────────────────────────────────────────────────────────────────
# Two key formats are supported:
#
#   3-part flat:  "project.{phase}.{artifact}"
#                 e.g. "project.1-foundation.prd"
#                      -> generated/1-foundation/prd.json
#
#   4-part item:  "project.{phase}.{subfolder}.{item_id}"
#                 e.g. "project.2-business-spec.usecases.usecase-001--name"
#                      -> generated/2-business-spec/usecases/usecase-001--name.json
#                 template/schema use the subfolder name (shared for all items of that type):
#                      -> template/2-business-spec/usecases.json
#                      -> template/2-business-spec/usecases.schema.json

def _project_parts(key: str) -> dict:
    """Parse a 3-part project key into {phase, artifact}.

    Only used for flat (3-part) keys. For item (4-part) keys,
    call _project_item_parts() instead.
    """
    parts = key.split(".")
    if len(parts) < 3:
        raise ValueError(
            f"project artifact key must have at least 3 parts (got {len(parts)}): '{key}'"
        )
    return {"phase": parts[1], "artifact": parts[2]}


def _project_item_parts(key: str) -> dict:
    """Parse a 4-part project item key into {phase, subfolder, item_id}.

    For keys like "project.2-business-spec.usecases.usecase-001--name".
    item_id joins any remaining parts so dots in item IDs are preserved.
    """
    parts = key.split(".")
    if len(parts) < 4:
        raise ValueError(
            f"project item key must have at least 4 parts (got {len(parts)}): '{key}'"
        )
    return {"phase": parts[1], "subfolder": parts[2], "item_id": ".".join(parts[3:])}


def _is_item_key(key: str) -> bool:
    """Return True if this is a 4-part project item key."""
    return key.startswith("project.") and len(key.split(".")) >= 4


def artifact_path(key: str) -> Path:
    """Content file for a project artifact.

    3-part: "project.1-foundation.prd"
                -> generated/1-foundation/prd.json
    4-part: "project.2-business-spec.usecases.usecase-001--name"
                -> generated/2-business-spec/usecases/usecase-001--name.json
    """
    if _is_item_key(key):
        return _r("item_artifact_path", **_project_item_parts(key))
    return _r("artifact_path", **_project_parts(key))


def artifact_template(key: str) -> Path:
    """Blank content template for a project artifact.

    3-part: "project.1-foundation.prd"
                -> template/1-foundation/prd.json
    4-part: "project.2-business-spec.usecases.usecase-001--name"
                -> template/2-business-spec/usecases.json  (shared per subfolder type)
    """
    if _is_item_key(key):
        parts = _project_item_parts(key)
        return _r("item_artifact_template", phase=parts["phase"], subfolder=parts["subfolder"])
    return _r("artifact_template", **_project_parts(key))


def artifact_schema(key: str) -> Path:
    """Field descriptions for a project artifact.

    3-part: "project.1-foundation.prd"
                -> template/1-foundation/prd.schema.json
    4-part: "project.2-business-spec.usecases.usecase-001--name"
                -> template/2-business-spec/usecases.schema.json  (shared per subfolder type)
    """
    if _is_item_key(key):
        parts = _project_item_parts(key)
        return _r("item_artifact_schema", phase=parts["phase"], subfolder=parts["subfolder"])
    return _r("artifact_schema", **_project_parts(key))


# ── module artifact files ─────────────────────────────────────────────────────────────────────────────────────────────
# key format: "{module_id}.{screen_id}.{phase}"
# module_id is ignored -- content is indexed by phase + screen only
# e.g. "module-001.screen-001--login.2-business-spec"
#       -> generated/2-business-spec/screens/screen-001--login.json

def _module_parts(key: str) -> dict:
    parts = key.split(".")
    if len(parts) != 3:
        raise ValueError(
            f"module artifact key must have exactly 3 parts (got {len(parts)}): '{key}'"
        )
    return {"screen_id": parts[1], "phase": parts[2]}


def module_artifact_path(key: str) -> Path:
    """Content file for a module screen artifact.

    "module-001.screen-001--login.2-business-spec"
        -> generated/2-business-spec/screens/screen-001--login.json
    """
    return _r("module_artifact_path", **_module_parts(key))


def module_artifact_template(key: str) -> Path:
    """Blank content template for a module screen artifact (shared per phase).

    "module-001.screen-001--login.2-business-spec"
        -> template/2-business-spec/screen.json
    """
    return _r("module_artifact_template", **_module_parts(key))


def module_artifact_schema(key: str) -> Path:
    """Field descriptions for a module screen artifact (shared per phase).

    "module-001.screen-001--login.2-business-spec"
        -> template/2-business-spec/screen.schema.json
    """
    return _r("module_artifact_schema", **_module_parts(key))
