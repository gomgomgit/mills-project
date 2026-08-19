"""
Unit tests for mcp.lib.tools.dep_graph

Coverage: 84 acceptance criteria across 13 groups
  WN-R  — Routing
  AI    — Auto-Init
  WN-S  — Project single-node
  WN-F  — Project field-group
  WN-M  — Module node
  SN    — depends_on snapshot
  SD    — Silent drop fix (unknown vs not-started dep key)
  SNV   — Single-node validate-before-mutate contract
  RV    — _resolve_dep_ver
  CS    — _compute_stale
  GS    — sync_stale_status
  GN    — get_stale_nodes
  VNK   — _validate_node_key
  TNG   — track_node guard (MCP entry point)

Run:
    cd Agentic-SDLC-v101/.asdlc
    python3 -m pytest mcp/tests/test_dep_graph.py -v
"""
import sys
import time
from pathlib import Path

import pytest

# Make .asdlc/ the package root so relative imports inside mcp work
sys.path.insert(0, str(Path(__file__).parents[3]))

import mcp.lib.tools.dep_graph as dg
from mcp.lib.tools.dep_graph import (
    _blank_leaf_node,
    _resolve_dep_ver,
    _compute_stale,
    _snapshot_depends_on,
    _write_node,
    _write_project_node,
    _write_module_node,
    _sync_stale_status,
    _get_stale_nodes,
    _validate_node_key,
)
from mcp.lib.commons.json_ops import read_file, write_file


# ── canonical templates (mirrors real template files) ─────────────────────────

PROJECT_TEMPLATE = {
    "project": {
        "1-foundation": {
            "prd":       None,
            "arch-spec": None,
            "uiux-spec":   None,
        },
        "2-business-spec": {
            "actor":         None,
            "usecase-index": None,   # field-group container in real project.json
        },
        "3-tech-spec": {
            "erd":              None,
            "api-list":         None,
            "shared-catalog":   None,
            "integration-spec": None,
        },
    }
}

MODULE_TEMPLATE = {
    "module-001--name": {
        "screen-001--name": {
            "2-business-spec": None,
            "3-tech-spec":     None,
            "4-implement":     None,
        }
    }
}


# ── shared fixture ─────────────────────────────────────────────────────────────

@pytest.fixture
def fs(tmp_path, monkeypatch):
    """Redirect all path functions to tmp_path.

    project.json and modules.json are pre-seeded (mirrors real generated/ state).
    module_template is seeded for _init_screen to read phase structure.
    """
    gen_dir  = tmp_path / "generated/internal/dep-graph"
    tmpl_dir = tmp_path / "template/internal/dep-graph"
    gen_dir.mkdir(parents=True)
    tmpl_dir.mkdir(parents=True)

    # pre-seeded files (no template copy needed)
    write_file(gen_dir / "project.json", PROJECT_TEMPLATE)
    write_file(gen_dir / "modules.json", {"modules": []})
    write_file(tmpl_dir / "module.json", MODULE_TEMPLATE)

    monkeypatch.setattr(dg, "project_json",    lambda: gen_dir / "project.json")
    monkeypatch.setattr(dg, "modules_json",    lambda: gen_dir / "modules.json")
    monkeypatch.setattr(dg, "module_json",     lambda mid: gen_dir / f"{mid}.json")
    monkeypatch.setattr(dg, "module_template", lambda: tmpl_dir / "module.json")

    return gen_dir  # tests inspect files here


# ── _blank_leaf_node (baseline state) ─────────────────────────────────────────

class TestBlankLeafNode:
    def test_ver_is_zero(self):
        assert _blank_leaf_node()["ver"] == 0

    def test_updated_at_is_none(self):
        assert _blank_leaf_node()["updated_at"] is None

    def test_depends_on_is_empty_dict(self):
        assert _blank_leaf_node()["depends_on"] == {}

    def test_stale_is_false(self):
        assert _blank_leaf_node()["stale"] is False

    def test_stale_keys_is_empty_list(self):
        assert _blank_leaf_node()["stale_keys"] == []

    def test_each_call_returns_new_object(self):
        a = _blank_leaf_node()
        b = _blank_leaf_node()
        a["ver"] = 99
        assert b["ver"] == 0


# ── Routing (WN-R) ─────────────────────────────────────────────────────────────

class TestRouting:
    def test_wnr1_project_key_routes_to_project_json(self, fs):
        result = _write_node("project.1-foundation.prd", [], [])
        assert "ok" in result

    def test_wnr2_module_key_routes_to_module_json(self, fs):
        result = _write_node("module-001.screen-001.3-tech-spec", [], [])
        assert "ok" in result

    def test_wnr3_unknown_prefix_returns_error(self, fs):
        result = _write_node("artifact.some.node", [], [])
        assert "error" in result

    def test_wnr4_module_key_with_2_parts_returns_error(self, fs):
        # routed to _write_module_node which validates 3-part format
        result = _write_node("module-001.screen-001", [], [])
        assert "error" in result

    def test_wnr5_module_key_with_4_parts_returns_error(self, fs):
        result = _write_node("module-001.screen-001.3-tech-spec.extra", [], [])
        assert "error" in result


# ── Auto-Init (AI) ─────────────────────────────────────────────────────────────

class TestAutoInit:
    def test_ai1_project_json_pre_seeded_with_null_structure(self, fs):
        # project.json is pre-seeded — always exists with null artifact nodes
        data = read_file(fs / "project.json")
        assert data["project"]["1-foundation"]["prd"] is None

    def test_ai2_modules_json_pre_seeded_as_empty(self, fs):
        # modules.json is pre-seeded — always exists, starts empty
        data = read_file(fs / "modules.json")
        assert data == {"modules": []}

    def test_ai3_module_file_created_when_missing(self, fs):
        assert not (fs / "module-001.json").exists()
        _write_node("module-001.screen-001.3-tech-spec", [], [])
        assert (fs / "module-001.json").exists()

    def test_ai4_new_module_registered_in_modules_json(self, fs):
        _write_node("module-001.screen-001.3-tech-spec", [], [])
        data = read_file(fs / "modules.json")
        assert "module-001" in data["modules"]

    def test_ai5_module_not_duplicated_in_modules_json(self, fs):
        _write_node("module-001.screen-001.3-tech-spec", [], [])
        _write_node("module-001.screen-001.4-implement", [], [])
        data = read_file(fs / "modules.json")
        assert data["modules"].count("module-001") == 1

    def test_ai6_new_screen_initialized_with_all_phases_null(self, fs):
        _write_node("module-001.screen-001.3-tech-spec", [], [])
        mod = read_file(fs / "module-001.json")
        phases = mod["module-001"]["screen-001"]
        # 2-business-spec was not written; should exist from template init
        assert "2-business-spec" in phases

    def test_ai7_init_screen_idempotent_does_not_overwrite_existing_data(self, fs):
        _write_node("module-001.screen-001.3-tech-spec", [], [])
        ver_first = read_file(fs / "module-001.json")["module-001"]["screen-001"]["3-tech-spec"]["ver"]
        # writing another phase on same screen should not reset 3-tech-spec
        _write_node("module-001.screen-001.4-implement", [], [])
        ver_after = read_file(fs / "module-001.json")["module-001"]["screen-001"]["3-tech-spec"]["ver"]
        assert ver_first == ver_after

    def test_ai8_two_distinct_modules_both_registered(self, fs):
        _write_node("module-001.screen-001.3-tech-spec", [], [])
        _write_node("module-002.screen-A.2-business-spec", [], [])
        modules = read_file(fs / "modules.json")["modules"]
        assert "module-001" in modules
        assert "module-002" in modules


# ── Project Single-Node (WN-S) ─────────────────────────────────────────────────

class TestProjectSingleNode:
    def test_wns1_first_write_sets_ver_to_1(self, fs):
        _write_node("project.1-foundation.prd", [], [])
        node = read_file(fs / "project.json")["project"]["1-foundation"]["prd"]
        assert node["ver"] == 1

    def test_wns2_second_write_increments_ver_to_2(self, fs):
        _write_node("project.1-foundation.prd", [], [])
        _write_node("project.1-foundation.prd", [], [])
        node = read_file(fs / "project.json")["project"]["1-foundation"]["prd"]
        assert node["ver"] == 2

    def test_wns2_ver_never_resets(self, fs):
        for _ in range(5):
            _write_node("project.1-foundation.prd", [], [])
        node = read_file(fs / "project.json")["project"]["1-foundation"]["prd"]
        assert node["ver"] == 5

    def test_wns3_updated_at_is_set(self, fs):
        _write_node("project.1-foundation.prd", [], [])
        node = read_file(fs / "project.json")["project"]["1-foundation"]["prd"]
        assert node["updated_at"] is not None

    def test_wns4_unknown_phase_group_returns_error(self, fs):
        result = _write_node("project.9-nonexistent.prd", [], [])
        assert "error" in result

    def test_wns5_container_with_no_ver_children_returns_error(self, fs):
        # A dict-without-ver whose children also have no "ver" → still an error.
        # Distinguishes the real-field-group case (children DO have ver → auto-bump)
        # from a truly malformed node (no ver anywhere → error).
        proj = read_file(fs / "project.json")
        proj["project"]["1-foundation"]["prd"] = {"label": "not a leaf"}
        write_file(fs / "project.json", proj)
        result = _write_node("project.1-foundation.prd", [], [])
        assert "error" in result

    def test_wns7_container_node_auto_bumps_child_leaf_nodes(self, fs):
        # Regression: usecase-index is a field-group container in project.json
        # ({"usecases": {ver,...}}). Calling with changed_fields=[] (single-node
        # mode) must auto-bump all child leaf nodes instead of returning "Not a
        # leaf node" error.
        #
        # Setup: establish the container via field-group write first (mirrors
        # what dep_graph__track_node does when changed_fields=["usecases"]).
        _write_node("project.2-business-spec.usecase-index", ["usecases"], [])
        container = read_file(fs / "project.json")["project"]["2-business-spec"]["usecase-index"]
        assert container["usecases"]["ver"] == 1   # sanity: child exists

        # Act: single-node mode with changed_fields=[] on the same container key
        result = _write_node("project.2-business-spec.usecase-index", [], [])
        assert "ok" in result, f"expected ok, got: {result}"

    def test_wns8_container_node_bumps_child_ver(self, fs):
        # The child leaf's ver must be incremented, not the container itself.
        _write_node("project.2-business-spec.usecase-index", ["usecases"], [])
        _write_node("project.2-business-spec.usecase-index", [], [])         # single-node auto-bump
        container = read_file(fs / "project.json")["project"]["2-business-spec"]["usecase-index"]
        assert container["usecases"]["ver"] == 2

    def test_wns9_container_node_return_format_uses_child_paths(self, fs):
        # bumped entries must use the full child path, not the container key.
        _write_node("project.2-business-spec.usecase-index", ["usecases"], [])
        result = _write_node("project.2-business-spec.usecase-index", [], [])
        assert result == {
            "ok": True,
            "bumped": [{"node": "project.2-business-spec.usecase-index.usecases", "ver": 2}],
        }

    def test_wns10_container_node_second_auto_bump_increments_further(self, fs):
        # Two consecutive single-node auto-bumps on a container → ver goes 1→2→3.
        _write_node("project.2-business-spec.usecase-index", ["usecases"], [])
        _write_node("project.2-business-spec.usecase-index", [], [])
        _write_node("project.2-business-spec.usecase-index", [], [])
        container = read_file(fs / "project.json")["project"]["2-business-spec"]["usecase-index"]
        assert container["usecases"]["ver"] == 3

    def test_wns6_return_format_single_bumped_entry(self, fs):
        result = _write_node("project.1-foundation.prd", [], [])
        assert result == {
            "ok": True,
            "bumped": [{"node": "project.1-foundation.prd", "ver": 1}],
        }


# ── Project Field-Group (WN-F) ─────────────────────────────────────────────────

class TestProjectFieldGroup:
    def test_wnf1_null_container_initialized_as_dict(self, fs):
        _write_node("project.1-foundation.prd", ["detail"], [])
        val = read_file(fs / "project.json")["project"]["1-foundation"]["prd"]
        assert isinstance(val, dict)

    def test_wnf2_null_field_initialized_with_ver_1(self, fs):
        _write_node("project.1-foundation.prd", ["detail"], [])
        node = read_file(fs / "project.json")["project"]["1-foundation"]["prd"]
        assert node["detail"]["ver"] == 1

    def test_wnf3_only_named_fields_bumped(self, fs):
        _write_node("project.1-foundation.prd", ["a", "b"], [])
        _write_node("project.1-foundation.prd", ["a"], [])       # only a
        node = read_file(fs / "project.json")["project"]["1-foundation"]["prd"]
        assert node["a"]["ver"] == 2

    def test_wnf4_unnamed_field_ver_unchanged(self, fs):
        _write_node("project.1-foundation.prd", ["a", "b"], [])
        _write_node("project.1-foundation.prd", ["a"], [])
        node = read_file(fs / "project.json")["project"]["1-foundation"]["prd"]
        assert node["b"]["ver"] == 1   # b was not named on second call

    def test_wnf5_return_bumped_contains_per_field_entry(self, fs):
        result = _write_node("project.1-foundation.prd", ["x", "y"], [])
        assert result["ok"] is True
        nodes = {b["node"] for b in result["bumped"]}
        assert nodes == {
            "project.1-foundation.prd.x",
            "project.1-foundation.prd.y",
        }

    def test_wnf6_all_fields_receive_same_depends_on_snapshot(self, fs):
        _write_node("project.1-foundation.prd", [], [])           # prd ver=1
        _write_node(
            "project.1-foundation.arch-spec",
            ["detail", "summary"],
            ["project.1-foundation.prd"],
        )
        node = read_file(fs / "project.json")["project"]["1-foundation"]["arch-spec"]
        assert node["detail"]["depends_on"] == node["summary"]["depends_on"]


    def test_wnf7_parent_ver_unchanged_during_field_group(self, fs):
        # Setup: single-node write gives prd a top-level ver
        _write_node("project.1-foundation.prd", [], [])           # prd.ver = 1
        _write_node("project.1-foundation.prd", ["goals"], [])    # goals.ver = 1
        # Field-group must not touch prd.ver
        _write_node("project.1-foundation.prd", ["goals"], [])    # goals.ver = 2
        node = read_file(fs / "project.json")["project"]["1-foundation"]["prd"]
        assert node["ver"] == 1                 # induk tidak berubah
        assert node["goals"]["ver"] == 2

    def test_wnf8_field_ver_increments_by_exactly_1(self, fs):
        # Drive goals to ver 10 then verify each bump is +1
        for _ in range(10):
            _write_node("project.1-foundation.prd", ["goals"], [])
        node = read_file(fs / "project.json")["project"]["1-foundation"]["prd"]
        assert node["goals"]["ver"] == 10
        _write_node("project.1-foundation.prd", ["goals"], [])
        node = read_file(fs / "project.json")["project"]["1-foundation"]["prd"]
        assert node["goals"]["ver"] == 11      # naik tepat 1


# ── Module Node (WN-M) ─────────────────────────────────────────────────────────

class TestModuleNode:
    def test_wnm1_first_write_sets_ver_to_1(self, fs):
        _write_node("module-001.screen-001.3-tech-spec", [], [])
        mod = read_file(fs / "module-001.json")
        assert mod["module-001"]["screen-001"]["3-tech-spec"]["ver"] == 1

    def test_wnm2_second_write_increments_ver(self, fs):
        _write_node("module-001.screen-001.3-tech-spec", [], [])
        _write_node("module-001.screen-001.3-tech-spec", [], [])
        mod = read_file(fs / "module-001.json")
        assert mod["module-001"]["screen-001"]["3-tech-spec"]["ver"] == 2

    def test_wnm3_self_dep_resolved_using_actual_ver(self, fs):
        _write_node("module-001.screen-001.3-tech-spec", [], [])   # ver=1
        _write_node(
            "module-001.screen-001.4-implement",
            [],
            ["self.3-tech-spec"],
        )
        mod = read_file(fs / "module-001.json")
        impl = mod["module-001"]["screen-001"]["4-implement"]
        assert impl["depends_on"]["self.3-tech-spec"] == 1

    def test_wnm4_4_implement_with_files_stores_them(self, fs):
        files = {
            "backend":   ["api.py"],
            "frontend":  ["page.tsx"],
            "migration": [],
            "test":      ["test_api.py"],
        }
        _write_node("module-001.screen-001.4-implement", [], [], files=files)
        mod  = read_file(fs / "module-001.json")
        node = mod["module-001"]["screen-001"]["4-implement"]
        assert node["files"] == files

    def test_wnm5_4_implement_without_files_uses_default(self, fs):
        _write_node("module-001.screen-001.4-implement", [], [])
        mod  = read_file(fs / "module-001.json")
        node = mod["module-001"]["screen-001"]["4-implement"]
        assert node["files"] == {
            "backend": [], "frontend": [], "migration": [], "test": []
        }

    def test_wnm6_non_implement_phase_has_no_files_field(self, fs):
        _write_node("module-001.screen-001.3-tech-spec", [], [])
        mod  = read_file(fs / "module-001.json")
        node = mod["module-001"]["screen-001"]["3-tech-spec"]
        assert "files" not in node

    def test_wnm7_return_format(self, fs):
        result = _write_node("module-001.screen-001.3-tech-spec", [], [])
        assert result == {
            "ok":    True,
            "bumped": [{"node": "module-001.screen-001.3-tech-spec", "ver": 1}],
        }


# ── depends_on Snapshot (SN) ───────────────────────────────────────────────────

class TestDependsOnSnapshot:
    def test_sn1_stored_ver_is_current_not_caller_value(self, fs):
        _write_node("project.1-foundation.prd", [], [])           # prd ver=1
        _write_node(
            "project.1-foundation.arch-spec",
            [],
            ["project.1-foundation.prd"],
        )
        node = read_file(fs / "project.json")["project"]["1-foundation"]["arch-spec"]
        assert node["depends_on"]["project.1-foundation.prd"] == 1

    def test_sn2_unknown_dep_key_returns_error(self, fs):
        # "project.9-ghost.none" does not exist in project.json structure → error
        result = _write_node(
            "project.1-foundation.prd",
            [],
            ["project.9-ghost.none"],
        )
        assert "error" in result

    def test_sn3_empty_depends_on_leaves_node_clean(self, fs):
        _write_node("project.1-foundation.prd", [], [])
        node = read_file(fs / "project.json")["project"]["1-foundation"]["prd"]
        assert node["depends_on"] == {}
        assert node["stale"]      is False
        assert node["stale_keys"] == []

    def test_sn4_stale_always_false_after_snapshot(self, fs):
        _write_node("project.1-foundation.prd", [], [])
        # Force stale=True manually
        proj = read_file(fs / "project.json")
        proj["project"]["1-foundation"]["prd"]["stale"]      = True
        proj["project"]["1-foundation"]["prd"]["stale_keys"] = ["x"]
        write_file(fs / "project.json", proj)
        # Re-write; snapshot should reset stale
        _write_node("project.1-foundation.prd", [], [])
        node = read_file(fs / "project.json")["project"]["1-foundation"]["prd"]
        assert node["stale"]      is False
        assert node["stale_keys"] == []


# ── SD — Silent Drop fix ───────────────────────────────────────────────────────
#
# _snapshot_depends_on must distinguish two cases that currently both return None:
#
#   NOT FOUND   — dep key does not exist anywhere in the structure (typo / wrong key)
#                 → _write_node must return {"error": ...}; node must NOT be written
#
#   NOT STARTED — dep key exists in the structure but the node has not been written
#                 yet (value is null); this is intentional and must remain silent
#                 → _write_node returns {"ok": ...}; dep is absent from snapshot
#                 (it will be picked up when both nodes are written)
#
# Tests SD1–SD5 verify the new error path.
# Tests SD6–SD10 verify the preserved silent-skip path.

class TestSnapshotUnknownDep:

    # ── SD1–SD5: unknown key → error ─────────────────────────────────────────

    def test_sd1_unknown_project_phase_returns_error(self, fs):
        # "project.9-ghost.none" — phase "9-ghost" does not exist in project.json
        result = _write_node("project.1-foundation.prd", [], ["project.9-ghost.none"])
        assert "error" in result

    def test_sd2_unknown_project_artifact_returns_error(self, fs):
        # "project.1-foundation.typo" — artifact "typo" not registered under 1-foundation
        result = _write_node("project.1-foundation.prd", [], ["project.1-foundation.typo"])
        assert "error" in result

    def test_sd3_unknown_self_phase_returns_error(self, fs):
        # "self.9-nonexistent" — phase not in screen_phases at all
        _write_node("module-001.screen-001.3-tech-spec", [], [])   # create screen
        result = _write_node("module-001.screen-001.4-implement", [], ["self.9-nonexistent"])
        assert "error" in result

    def test_sd4_unknown_module_file_returns_error(self, fs):
        # module-999.json does not exist → not found
        result = _write_node(
            "module-001.screen-001.4-implement",
            [],
            ["module.module-999.screen-001.3-tech-spec"],
        )
        assert "error" in result

    def test_sd5_error_message_contains_unknown_key(self, fs):
        result = _write_node("project.1-foundation.prd", [], ["project.9-ghost.none"])
        assert "project.9-ghost.none" in result.get("error", "")

    def test_sd6_unknown_key_does_not_write_node(self, fs):
        _write_node("project.1-foundation.prd", [], ["project.9-ghost.none"])
        node = read_file(fs / "project.json")["project"]["1-foundation"]["prd"]
        assert node is None   # node was never written

    # ── SD7–SD10: not-started key → silently skipped ─────────────────────────

    def test_sd7_not_started_project_dep_skipped_silently(self, fs):
        # prd is null in project.json (exists but not yet written)
        result = _write_node("project.1-foundation.arch-spec", [], ["project.1-foundation.prd"])
        assert "ok" in result

    def test_sd8_not_started_dep_absent_from_snapshot(self, fs):
        # prd is null → should not appear in arch-spec's depends_on
        _write_node("project.1-foundation.arch-spec", [], ["project.1-foundation.prd"])
        node = read_file(fs / "project.json")["project"]["1-foundation"]["arch-spec"]
        assert "project.1-foundation.prd" not in node["depends_on"]

    def test_sd9_not_started_self_dep_skipped_silently(self, fs):
        # 2-business-spec is null after screen init — exists in phases but not written
        _write_node("module-001.screen-001.3-tech-spec", [], [])   # creates screen
        result = _write_node("module-001.screen-001.4-implement", [], ["self.2-business-spec"])
        assert "ok" in result
        mod  = read_file(fs / "module-001.json")
        node = mod["module-001"]["screen-001"]["4-implement"]
        assert "self.2-business-spec" not in node["depends_on"]

    def test_sd10_mix_not_started_and_written_only_written_snapshotted(self, fs):
        # prd written (ver=1), arch-spec not started (null)
        _write_node("project.1-foundation.prd", [], [])
        _write_node(
            "project.1-foundation.uiux-spec",
            [],
            ["project.1-foundation.prd", "project.1-foundation.arch-spec"],
        )
        node = read_file(fs / "project.json")["project"]["1-foundation"]["uiux-spec"]
        assert node["depends_on"] == {"project.1-foundation.prd": 1}


# ── SNV — Single-node validate-before-mutate contract ─────────────────────────
#
# In _write_project_node single-node path, dep validation must happen BEFORE
# ver is bumped. This mirrors the field-group path (which already uses a
# sentinel for pre-validation). The externally observable contract:
#
#   1. A failed write (unknown dep) must not modify any disk state.
#   2. A failed write must not corrupt an existing node's ver on disk.
#   3. A successful write following a failed write must increment ver by exactly
#      1 from the last successful write — not +2 (i.e. no phantom bump).
#   4. These properties hold symmetrically for the field-group path (already
#      tested here as a baseline reference).
#
# Note: these are contract tests, not red-first. The behaviour is already
# correct at the disk level; the tests lock it down and make it explicit.

class TestSingleNodeValidateOrder:

    def test_snv1_failed_write_does_not_modify_existing_ver(self, fs):
        # Establish node at ver=1
        _write_node("project.1-foundation.prd", [], [])
        # Attempt write with unknown dep → must fail
        result = _write_node("project.1-foundation.prd", [], ["project.9-ghost.none"])
        assert "error" in result
        # Ver on disk must still be 1
        node = read_file(fs / "project.json")["project"]["1-foundation"]["prd"]
        assert node["ver"] == 1

    def test_snv2_failed_write_does_not_touch_project_json(self, fs):
        # Establish a written node so the file has a known mtime
        _write_node("project.1-foundation.prd", [], [])
        import time; time.sleep(0.05)
        mtime_before = (fs / "project.json").stat().st_mtime
        # Attempt write with unknown dep
        _write_node("project.1-foundation.prd", [], ["project.9-ghost.none"])
        assert (fs / "project.json").stat().st_mtime == mtime_before

    def test_snv3_successful_write_after_failed_write_increments_by_1(self, fs):
        # Write prd → ver=1
        _write_node("project.1-foundation.prd", [], [])
        # Fail with unknown dep (ver must not be phantom-bumped)
        _write_node("project.1-foundation.prd", [], ["project.9-ghost.none"])
        # Successful write → must be ver=2, not ver=3
        _write_node("project.1-foundation.prd", [], [])
        node = read_file(fs / "project.json")["project"]["1-foundation"]["prd"]
        assert node["ver"] == 2

    def test_snv4_field_group_failed_write_does_not_modify_existing_ver(self, fs):
        # Baseline: same contract holds for field-group path
        _write_node("project.1-foundation.prd", ["goals"], [])   # goals ver=1
        result = _write_node("project.1-foundation.prd", ["goals"], ["project.9-ghost.none"])
        assert "error" in result
        node = read_file(fs / "project.json")["project"]["1-foundation"]["prd"]
        assert node["goals"]["ver"] == 1


# ── _resolve_dep_ver (RV) ──────────────────────────────────────────────────────

def _leaf(ver):
    return {"ver": ver, "updated_at": None, "depends_on": {}, "stale": False, "stale_keys": []}


class TestResolveDepVer:
    def _project(self, prd_ver=3):
        return {
            "project": {
                "1-foundation": {
                    "prd":       _leaf(prd_ver),
                    "arch-spec": None,
                }
            }
        }

    def _phases(self, tech_ver=2):
        return {
            "2-business-spec": None,
            "3-tech-spec":     _leaf(tech_ver),
            "4-implement":     None,
        }

    # self.*
    def test_rv1_self_existing_phase_returns_ver(self):
        phases = self._phases(tech_ver=5)
        assert _resolve_dep_ver("self.3-tech-spec", {}, phases) == 5

    def test_rv2_self_null_phase_returns_none(self):
        phases = self._phases()
        phases["3-tech-spec"] = None
        assert _resolve_dep_ver("self.3-tech-spec", {}, phases) is None

    def test_rv3_self_missing_phase_returns_none(self):
        assert _resolve_dep_ver("self.nonexistent", {}, {}) is None

    # module.*
    def test_rv4_module_valid_key_returns_ver(self, fs):
        _write_node("module-001.screen-001.3-tech-spec", [], [])
        assert _resolve_dep_ver("module.module-001.screen-001.3-tech-spec", {}, {}) == 1

    def test_rv5_module_wrong_part_count_returns_none(self):
        assert _resolve_dep_ver("module.module-001.screen-001", {}, {}) is None

    def test_rv6_module_file_not_exists_returns_none(self, fs):
        assert _resolve_dep_ver("module.module-999.screen-001.3-tech-spec", {}, {}) is None

    def test_rv7_module_screen_not_found_returns_none(self, fs):
        _write_node("module-001.screen-001.3-tech-spec", [], [])
        assert _resolve_dep_ver("module.module-001.screen-999.3-tech-spec", {}, {}) is None

    def test_rv8_module_phase_null_returns_none(self, fs):
        _write_node("module-001.screen-001.3-tech-spec", [], [])
        # 2-business-spec initialised as null, not yet written
        assert _resolve_dep_ver("module.module-001.screen-001.2-business-spec", {}, {}) is None

    # project.*
    def test_rv9_project_valid_key_returns_ver(self):
        project = self._project(prd_ver=7)
        assert _resolve_dep_ver("project.1-foundation.prd", project, {}) == 7

    def test_rv10_project_missing_intermediate_key_returns_none(self):
        project = self._project()
        assert _resolve_dep_ver("project.9-nonexistent.prd", project, {}) is None

    def test_rv11_project_leaf_dict_without_ver_returns_none(self):
        project = {"project": {"1-foundation": {"prd": {"label": "foo"}}}}
        assert _resolve_dep_ver("project.1-foundation.prd", project, {}) is None


# ── _compute_stale (CS) ────────────────────────────────────────────────────────

class TestComputeStale:
    def _project(self, prd_ver):
        return {"project": {"1-foundation": {"prd": _leaf(prd_ver)}}}

    def test_cs1_empty_depends_on_returns_clean(self):
        assert _compute_stale({}, {}, {}) == (False, [])

    def test_cs2_all_deps_matching_returns_clean(self):
        project = self._project(prd_ver=2)
        deps    = {"project.1-foundation.prd": 2}
        assert _compute_stale(deps, project, {}) == (False, [])

    def test_cs3_one_dep_changed_returns_stale(self):
        project = self._project(prd_ver=3)
        deps    = {"project.1-foundation.prd": 2}   # recorded=2, current=3
        stale, keys = _compute_stale(deps, project, {})
        assert stale is True
        assert "project.1-foundation.prd" in keys

    def test_cs4_multiple_deps_changed_all_appear_in_stale_keys(self):
        project = {
            "project": {
                "1-foundation": {
                    "prd":       _leaf(3),
                    "arch-spec": _leaf(5),
                }
            }
        }
        deps = {
            "project.1-foundation.prd":       2,   # changed
            "project.1-foundation.arch-spec": 4,   # changed
        }
        stale, keys = _compute_stale(deps, project, {})
        assert stale is True
        assert set(keys) == {"project.1-foundation.prd", "project.1-foundation.arch-spec"}

    def test_cs5_unresolvable_dep_skipped(self):
        deps = {"project.9-fake.ghost": 1}
        stale, keys = _compute_stale(deps, {}, {})
        assert stale is False
        assert keys == []

    def test_cs6_all_unresolvable_returns_clean(self):
        deps = {"project.a.b": 1, "project.c.d": 2}
        assert _compute_stale(deps, {}, {}) == (False, [])


# ── sync_stale_status (GS) ─────────────────────────────────────────────────────

class TestSyncStaleStatus:
    def test_gs1_null_project_node_in_not_started(self, fs):
        result = _sync_stale_status()
        assert "project.1-foundation.prd" in result["not_started"]

    def test_gs2_null_module_phase_in_not_started(self, fs):
        _write_node("module-001.screen-001.3-tech-spec", [], [])
        result = _sync_stale_status()
        assert "module-001.screen-001.2-business-spec" in result["not_started"]

    def test_gs3_node_without_depends_on_is_clean(self, fs):
        _write_node("project.1-foundation.prd", [], [])
        result = _sync_stale_status()
        assert "project.1-foundation.prd" in result["clean"]

    def test_gs4_node_with_changed_dep_is_stale(self, fs):
        _write_node("project.1-foundation.prd", [], [])          # prd ver=1
        _write_node(
            "project.1-foundation.arch-spec",
            [],
            ["project.1-foundation.prd"],
        )
        _write_node("project.1-foundation.prd", [], [])          # prd ver=2 → arch-spec stale
        result = _sync_stale_status()
        stale_paths = {s["path"] for s in result["stale"]}
        assert "project.1-foundation.arch-spec" in stale_paths

    def test_gs5_node_with_matching_dep_is_clean(self, fs):
        _write_node("project.1-foundation.prd", [], [])          # prd ver=1
        _write_node(
            "project.1-foundation.arch-spec",
            [],
            ["project.1-foundation.prd"],
        )
        # do NOT bump prd again → arch-spec stays clean
        result = _sync_stale_status()
        assert "project.1-foundation.arch-spec" in result["clean"]

    def test_gs6_module_nodes_discovered_via_modules_json(self, fs):
        _write_node("module-001.screen-001.3-tech-spec", [], [])
        result = _sync_stale_status()
        all_paths = (
            [s["path"] for s in result["stale"]]
            + result["clean"]
            + result["not_started"]
        )
        assert any("module-001" in p for p in all_paths)

    def test_gs7_missing_module_file_skipped_gracefully(self, fs):
        write_file(fs / "modules.json", {"modules": ["module-ghost"]})
        result = _sync_stale_status()   # must not raise
        assert "stale" in result

    def test_gs8_no_write_when_stale_state_unchanged(self, fs):
        _write_node("project.1-foundation.prd", [], [])
        _sync_stale_status()                                    # normalize
        mtime_1 = (fs / "project.json").stat().st_mtime
        time.sleep(0.05)
        _sync_stale_status()                                    # no change
        mtime_2 = (fs / "project.json").stat().st_mtime
        assert mtime_1 == mtime_2

    def test_gs9_stale_true_without_depends_on_reset_to_false(self, fs):
        _write_node("project.1-foundation.prd", [], [])
        # Force stale=True manually
        proj = read_file(fs / "project.json")
        proj["project"]["1-foundation"]["prd"]["stale"]      = True
        proj["project"]["1-foundation"]["prd"]["stale_keys"] = ["x"]
        write_file(fs / "project.json", proj)
        _sync_stale_status()
        node = read_file(fs / "project.json")["project"]["1-foundation"]["prd"]
        assert node["stale"]      is False
        assert node["stale_keys"] == []

    def test_gs10_idempotent_no_write_on_second_call(self, fs):
        _write_node("project.1-foundation.prd", [], [])
        _write_node(
            "project.1-foundation.arch-spec",
            [],
            ["project.1-foundation.prd"],
        )
        _sync_stale_status()                                    # first compute
        mtime_proj = (fs / "project.json").stat().st_mtime
        time.sleep(0.05)
        _sync_stale_status()                                    # no change → no write
        assert (fs / "project.json").stat().st_mtime == mtime_proj

    def test_gs11_modules_json_pre_seeded_empty_list_returned(self, fs):
        # modules.json is pre-seeded — sync_stale_status works on fresh empty state
        result = _sync_stale_status()
        assert result["stale"] == []
        assert result["not_started"]  # project nodes are all null

    def test_gs12_return_has_ok_true(self, fs):
        result = _sync_stale_status()
        assert result.get("ok") is True


# ── GN — get_stale_nodes ──────────────────────────────────────────────────────

class TestGetStaleNodes:
    def test_gn1_clean_node_not_in_result(self, fs):
        _write_node("project.1-foundation.prd", [], [])
        result = _get_stale_nodes()
        paths = [r["path"] for r in result]
        assert "project.1-foundation.prd" not in paths

    def test_gn2_stale_node_appears_with_path_and_stale_keys(self, fs):
        _write_node("project.1-foundation.prd", [], [])
        _write_node("project.1-foundation.arch-spec", [], ["project.1-foundation.prd"])
        # bump prd so arch-spec becomes stale
        _write_node("project.1-foundation.prd", [], [])
        _sync_stale_status()
        result = _get_stale_nodes()
        paths = [r["path"] for r in result]
        assert "project.1-foundation.arch-spec" in paths
        entry = next(r for r in result if r["path"] == "project.1-foundation.arch-spec")
        assert "project.1-foundation.prd" in entry["stale_keys"]

    def test_gn3_null_node_not_in_result(self, fs):
        # All nodes are null (not started) — result must be empty
        result = _get_stale_nodes()
        assert result == []

    def test_gn4_no_stale_nodes_returns_empty_list(self, fs):
        _write_node("project.1-foundation.prd", [], [])
        _sync_stale_status()
        result = _get_stale_nodes()
        assert result == []

    def test_gn5_module_node_stale_appears_in_result(self, fs):
        _write_node("project.1-foundation.prd", [], [])
        _write_node("module-001.screen-001.2-business-spec", [], ["project.1-foundation.prd"])
        # bump prd → module node becomes stale
        _write_node("project.1-foundation.prd", [], [])
        _sync_stale_status()
        result = _get_stale_nodes()
        paths = [r["path"] for r in result]
        assert "module-001.screen-001.2-business-spec" in paths

    def test_gn6_multiple_stale_nodes_all_appear(self, fs):
        _write_node("project.1-foundation.prd", [], [])
        _write_node("project.1-foundation.arch-spec", [], ["project.1-foundation.prd"])
        _write_node("project.1-foundation.uiux-spec",   [], ["project.1-foundation.prd"])
        _write_node("project.1-foundation.prd", [], [])
        _sync_stale_status()
        result = _get_stale_nodes()
        paths = [r["path"] for r in result]
        assert "project.1-foundation.arch-spec" in paths
        assert "project.1-foundation.uiux-spec"   in paths

    def test_gn7_read_only_file_not_modified(self, fs):
        _write_node("project.1-foundation.prd", [], [])
        _sync_stale_status()
        mtime_before = (fs / "project.json").stat().st_mtime
        import time; time.sleep(0.05)
        _get_stale_nodes()
        assert (fs / "project.json").stat().st_mtime == mtime_before


    def test_gn8_stale_keys_match_stored_value(self, fs):
        _write_node("project.1-foundation.prd",       [], [])
        _write_node("project.1-foundation.arch-spec", [], [])
        _write_node("project.1-foundation.uiux-spec", [],
                    ["project.1-foundation.prd", "project.1-foundation.arch-spec"])
        # bump both deps
        _write_node("project.1-foundation.prd",       [], [])
        _write_node("project.1-foundation.arch-spec", [], [])
        _sync_stale_status()
        result = _get_stale_nodes()
        entry = next(r for r in result if r["path"] == "project.1-foundation.uiux-spec")
        assert set(entry["stale_keys"]) == {
            "project.1-foundation.prd",
            "project.1-foundation.arch-spec",
        }


# ── VNK — _validate_node_key ──────────────────────────────────────────────────

class TestValidateNodeKey:
    def test_vnk1_empty_string_returns_error(self):
        result = _validate_node_key("")
        assert "error" in result

    def test_vnk2_one_part_returns_error(self):
        result = _validate_node_key("project")
        assert "error" in result

    def test_vnk3_two_parts_returns_error(self):
        result = _validate_node_key("project.1-foundation")
        assert "error" in result

    def test_vnk4_valid_project_key_returns_none(self):
        assert _validate_node_key("project.1-foundation.prd") is None