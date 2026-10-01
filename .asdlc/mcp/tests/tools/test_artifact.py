"""
Unit tests for mcp.lib.tools.artifact

Coverage: 119 acceptance criteria across 10 groups
  RT  — Routing (_content_path / _schema_path / _template_path)
  DF  — _diff_fields
  LA  — _list_artifacts
  RA  — _read_artifact
  WA  — _write_artifact
  RS  — _read_artifact_scheme
  IT  — Integration / Roundtrip
  GC  — Guard Conditions (_validate_artifact_key)
  VS  — _validate_structure (direct unit tests)
  VW  — _write_artifact with template validation
  EP  — _parse_edit_path / _render_path
  AE  — _apply_edit
  PA  — _patch_artifact

Run:
    cd Agentic-SDLC-v101/.asdlc
    python3 -m pytest mcp/tests/tools/test_artifact.py -v
"""
import sys
from pathlib import Path

import pytest

sys.path.insert(0, str(Path(__file__).parents[3]))

import mcp.lib.tools.artifact as af
from mcp.lib.tools.artifact import (
    _content_path,
    _schema_path,
    _template_path,
    _diff_fields,
    _validate_structure,
    _list_artifacts,
    _read_artifact,
    _write_artifact,
    _read_artifact_scheme,
    _validate_artifact_key,
    _parse_edit_path,
    _render_path,
    _apply_edit,
    _patch_artifact,
)
from mcp.lib.commons.json_ops import read_file, write_file


# ── canonical project template ────────────────────────────────────────────────

PROJECT_TEMPLATE = {
    "project": {
        "1-foundation": {
            "prd":       None,
            "arch-spec": None,
            "uiux-spec":   None,
        },
        "2-business-spec": {
            "actor": None,
        },
        "3-tech-spec": {
            "erd":              None,
            "api-list":         None,
            "shared-catalog":   None,
            "integration-spec": None,
        },
    }
}

SAMPLE_SCHEMA = {
    "_tracked": ["goals", "problem_statement", "assumptions"],
    "ver":  "schema version string",
    "meta": "metadata description",
}


# ── path helpers ──────────────────────────────────────────────────────────────

def _ap(root, key):
    parts = key.split(".")
    return root / "generated" / parts[1] / f"{parts[2]}.json"

def _as(root, key):
    parts = key.split(".")
    return root / "template" / parts[1] / f"{parts[2]}.schema.json"

def _at(root, key):
    parts = key.split(".")
    return root / "template" / parts[1] / f"{parts[2]}.json"

def _map(root, key):
    parts = key.split(".")
    screen_id, phase = parts[1], parts[2]
    return root / "generated" / phase / "screens" / f"{screen_id}.json"

def _mas(root, key):
    phase = key.split(".")[2]
    return root / "template" / phase / "screen.schema.json"

def _mat(root, key):
    phase = key.split(".")[2]
    return root / "template" / phase / "screen.json"


# ── seed helpers ──────────────────────────────────────────────────────────────

def _write_schema(root, key, schema):
    path = _as(root, key)
    path.parent.mkdir(parents=True, exist_ok=True)
    write_file(path, schema)

def _write_template(root, key, template):
    path = _at(root, key)
    path.parent.mkdir(parents=True, exist_ok=True)
    write_file(path, template)

def _write_module_schema(root, phase, schema):
    path = root / "template" / phase / "screen.schema.json"
    path.parent.mkdir(parents=True, exist_ok=True)
    write_file(path, schema)

def _seed_module(root, module_id):
    dep_dir = root / "generated/internal/dep-graph"
    modules_data = read_file(dep_dir / "modules.json")
    if module_id not in modules_data["modules"]:
        modules_data["modules"].append(module_id)
    write_file(dep_dir / "modules.json", modules_data)
    write_file(dep_dir / f"{module_id}.json", {
        module_id: {
            "screen-001": {
                "2-business-spec": None,
                "3-tech-spec":     None,
            }
        }
    })


# ── shared fixture ────────────────────────────────────────────────────────────

@pytest.fixture
def fs(tmp_path, monkeypatch):
    dep_dir = tmp_path / "generated/internal/dep-graph"
    dep_dir.mkdir(parents=True)

    write_file(dep_dir / "project.json", PROJECT_TEMPLATE)
    write_file(dep_dir / "modules.json", {"modules": []})

    monkeypatch.setattr(af, "project_json",              lambda: dep_dir / "project.json")
    monkeypatch.setattr(af, "modules_json",              lambda: dep_dir / "modules.json")
    monkeypatch.setattr(af, "module_json",               lambda mid: dep_dir / f"{mid}.json")
    monkeypatch.setattr(af, "artifact_path",             lambda key: _ap(tmp_path, key))
    monkeypatch.setattr(af, "artifact_template",         lambda key: _at(tmp_path, key))
    monkeypatch.setattr(af, "artifact_schema",           lambda key: _as(tmp_path, key))
    monkeypatch.setattr(af, "module_artifact_path",      lambda key: _map(tmp_path, key))
    monkeypatch.setattr(af, "module_artifact_template",  lambda key: _mat(tmp_path, key))
    monkeypatch.setattr(af, "module_artifact_schema",    lambda key: _mas(tmp_path, key))

    return tmp_path


# ── RT — Routing ──────────────────────────────────────────────────────────────

class TestRouting:
    def test_rt1_project_key_content_path(self, fs):
        path = _content_path("project.1-foundation.prd")
        assert "generated/1-foundation/prd.json" in str(path).replace("\\", "/")

    def test_rt2_module_key_content_path(self, fs):
        path = _content_path("module-001.screen-001--login.2-business-spec")
        assert "generated/2-business-spec/screens/screen-001--login.json" in str(path).replace("\\", "/")

    def test_rt3_project_schema_path(self, fs):
        path = _schema_path("project.1-foundation.prd")
        assert "template/1-foundation/prd.schema.json" in str(path).replace("\\", "/")

    def test_rt4_module_schema_path(self, fs):
        path = _schema_path("module-001.screen-001--login.2-business-spec")
        assert "template/2-business-spec/screen.schema.json" in str(path).replace("\\", "/")

    def test_rt5_double_hyphen_screen_id_preserved(self, fs):
        path = _content_path("module-001.screen-001--nama.2-business-spec")
        assert "screen-001--nama.json" in str(path)

    def test_rt6_module_id_ignored_in_content_path(self, fs):
        path1 = _content_path("module-001.screen-001.2-business-spec")
        path2 = _content_path("module-002.screen-001.2-business-spec")
        assert path1 == path2

    def test_rt7_content_path_returns_path_object(self, fs):
        assert isinstance(_content_path("project.1-foundation.prd"), Path)

    def test_rt7b_schema_path_returns_path_object(self, fs):
        assert isinstance(_schema_path("project.1-foundation.prd"), Path)


# ── DF — _diff_fields ─────────────────────────────────────────────────────────

class TestDiffFields:
    def test_df1_changed_tracked_field_detected(self):
        old = {"goals": "old"}
        new = {"goals": "new"}
        assert _diff_fields(old, new, SAMPLE_SCHEMA) == ["goals"]

    def test_df2_unchanged_tracked_field_not_in_result(self):
        same = {"goals": "x", "problem_statement": "y", "assumptions": "z"}
        assert _diff_fields(same, same.copy(), SAMPLE_SCHEMA) == []

    def test_df3_changed_untracked_field_ignored(self):
        old = {"ver": 1,  "goals": "same"}
        new = {"ver": 99, "goals": "same"}
        assert _diff_fields(old, new, SAMPLE_SCHEMA) == []

    def test_df4_schema_without_tracked_returns_empty(self):
        assert _diff_fields({"goals": "x"}, {"goals": "y"}, {}) == []

    def test_df5_empty_tracked_list_returns_empty(self):
        schema = {"_tracked": []}
        assert _diff_fields({"goals": "x"}, {"goals": "y"}, schema) == []

    def test_df6_field_added_in_new_detected(self):
        result = _diff_fields({}, {"goals": "new"}, SAMPLE_SCHEMA)
        assert "goals" in result

    def test_df7_field_removed_in_new_detected(self):
        result = _diff_fields({"goals": "old"}, {}, SAMPLE_SCHEMA)
        assert "goals" in result

    def test_df8_identical_nested_list_not_changed(self):
        val = [{"item": "x"}, {"item": "y"}]
        old = {"goals": val}
        new = {"goals": [{"item": "x"}, {"item": "y"}]}
        assert _diff_fields(old, new, SAMPLE_SCHEMA) == []

    def test_df9_list_reorder_detected_as_changed(self):
        old = {"goals": ["a", "b"]}
        new = {"goals": ["b", "a"]}
        assert "goals" in _diff_fields(old, new, SAMPLE_SCHEMA)

    def test_df10_dict_key_reorder_not_changed(self):
        old = {"assumptions": {"a": 1, "b": 2}}
        new = {"assumptions": {"b": 2, "a": 1}}
        assert _diff_fields(old, new, SAMPLE_SCHEMA) == []

    def test_df11_none_vs_value_detected(self):
        old = {"goals": None}
        new = {"goals": "something"}
        assert "goals" in _diff_fields(old, new, SAMPLE_SCHEMA)

    def test_df12_ver_changed_not_in_result(self):
        old = {"ver": 1, "goals": "same"}
        new = {"ver": 2, "goals": "same"}
        assert "ver" not in _diff_fields(old, new, SAMPLE_SCHEMA)

    def test_df13_meta_changed_not_in_result(self):
        old = {"meta": {"title": "A"}, "goals": "same"}
        new = {"meta": {"title": "B"}, "goals": "same"}
        assert "meta" not in _diff_fields(old, new, SAMPLE_SCHEMA)

    def test_df14_both_empty_dicts_return_empty(self):
        assert _diff_fields({}, {}, SAMPLE_SCHEMA) == []

    def test_df15_result_order_follows_tracked_order(self):
        schema = {"_tracked": ["a", "b", "c"]}
        result = _diff_fields({}, {"a": 1, "b": 2, "c": 3}, schema)
        assert result == ["a", "b", "c"]


# ── LA — _list_artifacts ──────────────────────────────────────────────────────

class TestListArtifacts:
    def test_la1_project_artifact_count_matches_project_json(self, fs):
        result = _list_artifacts()
        project_items = [x for x in result if x["type"] == "project"]
        assert len(project_items) == 8

    def test_la2_all_project_keys_present(self, fs):
        keys = {x["key"] for x in _list_artifacts()}
        assert "project.1-foundation.prd" in keys
        assert "project.3-tech-spec.erd" in keys
        assert "project.2-business-spec.actor" in keys

    def test_la3_not_started_when_no_content_file(self, fs):
        item = next(x for x in _list_artifacts() if x["key"] == "project.1-foundation.prd")
        assert item["status"] == "not_started"

    def test_la4_written_after_content_file_exists(self, fs):
        _write_artifact("project.1-foundation.prd", {"ver": 1})
        item = next(x for x in _list_artifacts() if x["key"] == "project.1-foundation.prd")
        assert item["status"] == "written"

    def test_la5_project_type_is_correct(self, fs):
        item = next(x for x in _list_artifacts() if x["key"] == "project.1-foundation.prd")
        assert item["type"] == "project"

    def test_la6_module_type_is_correct(self, fs):
        _seed_module(fs, "module-001")
        mod_items = [x for x in _list_artifacts() if x["type"] == "module"]
        assert len(mod_items) > 0
        assert all(x["type"] == "module" for x in mod_items)

    def test_la7_module_registered_but_file_missing_skipped(self, fs):
        dep_dir = fs / "generated/internal/dep-graph"
        write_file(dep_dir / "modules.json", {"modules": ["module-ghost"]})
        result = _list_artifacts()
        assert not any("module-ghost" in x["key"] for x in result)

    def test_la8_empty_modules_no_module_artifacts(self, fs):
        result = _list_artifacts()
        assert not any(x["type"] == "module" for x in result)

    def test_la9_project_key_format(self, fs):
        project_items = [x for x in _list_artifacts() if x["type"] == "project"]
        for item in project_items:
            parts = item["key"].split(".")
            assert parts[0] == "project"
            assert len(parts) == 3

    def test_la10_module_key_format(self, fs):
        _seed_module(fs, "module-001")
        mod_items = [x for x in _list_artifacts() if x["type"] == "module"]
        for item in mod_items:
            parts = item["key"].split(".")
            assert parts[0] == "module-001"
            assert len(parts) == 3

    def test_la11_status_updates_after_write(self, fs):
        before = next(x for x in _list_artifacts() if x["key"] == "project.1-foundation.prd")
        assert before["status"] == "not_started"
        _write_artifact("project.1-foundation.prd", {"ver": 1})
        after = next(x for x in _list_artifacts() if x["key"] == "project.1-foundation.prd")
        assert after["status"] == "written"

    def test_la12_result_is_list_with_required_keys(self, fs):
        result = _list_artifacts()
        assert isinstance(result, list)
        for item in result:
            assert {"key", "type", "status"} <= item.keys()

    def test_la13_two_modules_both_appear(self, fs):
        _seed_module(fs, "module-001")
        _seed_module(fs, "module-002")
        result = _list_artifacts()
        mod_ids = {x["key"].split(".")[0] for x in result if x["type"] == "module"}
        assert "module-001" in mod_ids
        assert "module-002" in mod_ids


# ── RA — _read_artifact ───────────────────────────────────────────────────────
# Return contract: {"data": dict} | {"data": None} | {"error": str}

class TestReadArtifact:
    def test_ra1_existing_file_returns_data_dict(self, fs):
        _write_artifact("project.1-foundation.prd", {"ver": 1})
        result = _read_artifact("project.1-foundation.prd")
        assert isinstance(result["data"], dict)

    def test_ra2_nonexistent_project_returns_data_none(self, fs):
        result = _read_artifact("project.1-foundation.prd")
        assert result == {"data": None}

    def test_ra3_project_reads_from_correct_path(self, fs):
        path = _ap(fs, "project.1-foundation.prd")
        path.parent.mkdir(parents=True, exist_ok=True)
        write_file(path, {"direct": True})
        assert _read_artifact("project.1-foundation.prd") == {"data": {"direct": True}}

    def test_ra4_module_reads_from_screens_path(self, fs):
        key  = "module-001.screen-001--login.2-business-spec"
        path = _map(fs, key)
        path.parent.mkdir(parents=True, exist_ok=True)
        write_file(path, {"screen": "login"})
        assert _read_artifact(key) == {"data": {"screen": "login"}}

    def test_ra5_roundtrip_data_identical(self, fs):
        data = {"ver": 1, "goals": ["ship it"]}
        _write_artifact("project.1-foundation.prd", data)
        assert _read_artifact("project.1-foundation.prd") == {"data": data}

    def test_ra6_no_data_transformation(self, fs):
        data = {"ver": 1, "extra_field": "preserved", "nested": {"a": 1}}
        _write_artifact("project.1-foundation.prd", data)
        assert _read_artifact("project.1-foundation.prd") == {"data": data}

    def test_ra7_missing_parent_dir_returns_data_none(self, fs):
        result = _read_artifact("project.9-nonexistent.ghost")
        assert result == {"data": None}

    def test_ra8_return_always_has_data_key(self, fs):
        # Both found and not-found must have "data" key
        r_missing = _read_artifact("project.1-foundation.prd")
        assert "data" in r_missing
        _write_artifact("project.1-foundation.prd", {"ver": 1})
        r_found = _read_artifact("project.1-foundation.prd")
        assert "data" in r_found


# ── WA — _write_artifact ──────────────────────────────────────────────────────

class TestWriteArtifact:
    def test_wa1_project_file_written_to_correct_path(self, fs):
        _write_artifact("project.1-foundation.prd", {"ver": 1})
        assert _ap(fs, "project.1-foundation.prd").exists()

    def test_wa2_parent_dirs_created_automatically(self, fs):
        _write_artifact("project.2-business-spec.actor", {"ver": 1})
        assert _ap(fs, "project.2-business-spec.actor").exists()

    def test_wa3_changed_fields_detected_with_schema(self, fs):
        _write_schema(fs, "project.1-foundation.prd", SAMPLE_SCHEMA)
        _write_artifact("project.1-foundation.prd", {"goals": "old"})
        result = _write_artifact("project.1-foundation.prd", {"goals": "new"})
        assert "goals" in result["changed_fields"]

    def test_wa4_no_change_returns_empty_changed_fields(self, fs):
        _write_schema(fs, "project.1-foundation.prd", SAMPLE_SCHEMA)
        _write_artifact("project.1-foundation.prd", {"goals": "same"})
        result = _write_artifact("project.1-foundation.prd", {"goals": "same"})
        assert result["changed_fields"] == []

    def test_wa5_no_schema_returns_empty_changed_fields(self, fs):
        result = _write_artifact("project.1-foundation.prd", {"goals": "anything"})
        assert result["changed_fields"] == []

    def test_wa6_first_write_all_tracked_fields_appear_as_changed(self, fs):
        _write_schema(fs, "project.1-foundation.prd", SAMPLE_SCHEMA)
        result = _write_artifact("project.1-foundation.prd", {
            "goals":             "do stuff",
            "problem_statement": "the problem",
            "assumptions":       "some assumptions",
        })
        assert set(result["changed_fields"]) == {"goals", "problem_statement", "assumptions"}

    def test_wa7_untracked_field_not_in_changed_fields(self, fs):
        _write_schema(fs, "project.1-foundation.prd", SAMPLE_SCHEMA)
        _write_artifact("project.1-foundation.prd", {"goals": "same", "ver": 1})
        result = _write_artifact("project.1-foundation.prd", {"goals": "same", "ver": 2})
        assert "ver" not in result["changed_fields"]

    def test_wa8_identical_overwrite_empty_changed_fields(self, fs):
        _write_schema(fs, "project.1-foundation.prd", SAMPLE_SCHEMA)
        data = {"goals": "same", "problem_statement": "same"}
        _write_artifact("project.1-foundation.prd", data)
        result = _write_artifact("project.1-foundation.prd", data)
        assert result["changed_fields"] == []

    def test_wa9_module_artifact_written_to_screens_path(self, fs):
        key = "module-001.screen-001--login.2-business-spec"
        _write_artifact(key, {"data": True})
        assert _map(fs, key).exists()

    def test_wa10_overwrite_replaces_file_completely(self, fs):
        _write_artifact("project.1-foundation.prd", {"old_field": "old"})
        _write_artifact("project.1-foundation.prd", {"new_field": "new"})
        result = _read_artifact("project.1-foundation.prd")
        assert "old_field" not in result["data"]
        assert result["data"]["new_field"] == "new"

    def test_wa11_ver_and_meta_not_in_changed_fields(self, fs):
        _write_schema(fs, "project.1-foundation.prd", SAMPLE_SCHEMA)
        _write_artifact("project.1-foundation.prd", {
            "goals": "same", "ver": 1, "meta": {"title": "A"}
        })
        result = _write_artifact("project.1-foundation.prd", {
            "goals": "same", "ver": 2, "meta": {"title": "B"}
        })
        assert "ver"  not in result["changed_fields"]
        assert "meta" not in result["changed_fields"]

    def test_wa12_return_format_on_success(self, fs):
        result = _write_artifact("project.1-foundation.prd", {"ver": 1})
        assert result["ok"]  is True
        assert result["key"] == "project.1-foundation.prd"
        assert "path"           in result
        assert "changed_fields" in result

    def test_wa13_changed_fields_are_strings(self, fs):
        _write_schema(fs, "project.1-foundation.prd", SAMPLE_SCHEMA)
        result = _write_artifact("project.1-foundation.prd", {"goals": "new"})
        assert all(isinstance(f, str) for f in result["changed_fields"])

    def test_wa14_write_then_read_roundtrip(self, fs):
        data = {"ver": 1, "goals": "ship it", "meta": {"title": "PRD"}}
        _write_artifact("project.1-foundation.prd", data)
        assert _read_artifact("project.1-foundation.prd") == {"data": data}


# ── RS — _read_artifact_scheme ────────────────────────────────────────────────
# Return contract: {"data": dict} | {"data": None} | {"error": str}

class TestReadArtifactScheme:
    def test_rs1_project_key_with_schema_returns_data_dict(self, fs):
        _write_schema(fs, "project.1-foundation.prd", SAMPLE_SCHEMA)
        result = _read_artifact_scheme("project.1-foundation.prd")
        assert isinstance(result["data"], dict)

    def test_rs2_project_key_no_schema_returns_data_none(self, fs):
        assert _read_artifact_scheme("project.1-foundation.prd") == {"data": None}

    def test_rs3_module_key_no_schema_returns_data_none(self, fs):
        assert _read_artifact_scheme("module-001.screen-001.2-business-spec") == {"data": None}

    def test_rs4_schema_contains_tracked_key(self, fs):
        _write_schema(fs, "project.1-foundation.prd", SAMPLE_SCHEMA)
        result = _read_artifact_scheme("project.1-foundation.prd")
        assert "_tracked" in result["data"]
        assert "goals" in result["data"]["_tracked"]

    def test_rs5_returns_full_schema_not_only_tracked(self, fs):
        schema = {"_tracked": ["goals"], "goals": "your goals", "ver": "schema ver"}
        _write_schema(fs, "project.1-foundation.prd", schema)
        result = _read_artifact_scheme("project.1-foundation.prd")
        assert "goals" in result["data"]
        assert "ver"   in result["data"]

    def test_rs6_data_is_dict_not_string(self, fs):
        _write_schema(fs, "project.1-foundation.prd", SAMPLE_SCHEMA)
        result = _read_artifact_scheme("project.1-foundation.prd")
        assert isinstance(result["data"], dict)
        assert not isinstance(result["data"], str)

    def test_rs7_module_key_reads_screen_schema_file(self, fs):
        schema = {"_tracked": ["screen_name"]}
        _write_module_schema(fs, "2-business-spec", schema)
        result = _read_artifact_scheme("module-001.screen-001.2-business-spec")
        assert result == {"data": schema}

    def test_rs8_return_always_has_data_key(self, fs):
        r_missing = _read_artifact_scheme("project.1-foundation.prd")
        assert "data" in r_missing
        _write_schema(fs, "project.1-foundation.prd", SAMPLE_SCHEMA)
        r_found = _read_artifact_scheme("project.1-foundation.prd")
        assert "data" in r_found


# ── IT — Integration / Roundtrip ─────────────────────────────────────────────

class TestIntegration:
    def test_it1_write_then_read_returns_same_data(self, fs):
        data = {"ver": 1, "goals": ["ship it", "fast"], "meta": {"title": "PRD"}}
        _write_artifact("project.1-foundation.prd", data)
        assert _read_artifact("project.1-foundation.prd") == {"data": data}

    def test_it2_first_write_all_tracked_fields_changed(self, fs):
        schema = {"_tracked": ["goals", "problem_statement"]}
        _write_schema(fs, "project.1-foundation.prd", schema)
        result = _write_artifact("project.1-foundation.prd", {
            "goals": "something", "problem_statement": "something"
        })
        assert set(result["changed_fields"]) == {"goals", "problem_statement"}

    def test_it3_second_write_same_data_no_changed_fields(self, fs):
        schema = {"_tracked": ["goals"]}
        _write_schema(fs, "project.1-foundation.prd", schema)
        data = {"goals": "same"}
        _write_artifact("project.1-foundation.prd", data)
        result = _write_artifact("project.1-foundation.prd", data)
        assert result["changed_fields"] == []

    def test_it4_change_one_field_only_that_field_changed(self, fs):
        schema = {"_tracked": ["goals", "problem_statement"]}
        _write_schema(fs, "project.1-foundation.prd", schema)
        _write_artifact("project.1-foundation.prd", {
            "goals": "original", "problem_statement": "original"
        })
        result = _write_artifact("project.1-foundation.prd", {
            "goals": "changed", "problem_statement": "original"
        })
        assert result["changed_fields"] == ["goals"]

    def test_it5_list_status_changes_after_write(self, fs):
        before = next(x for x in _list_artifacts() if x["key"] == "project.1-foundation.prd")
        assert before["status"] == "not_started"
        _write_artifact("project.1-foundation.prd", {"ver": 1})
        after = next(x for x in _list_artifacts() if x["key"] == "project.1-foundation.prd")
        assert after["status"] == "written"

    def test_it6_module_artifact_written_shows_as_written_in_list(self, fs):
        key = "module-001.screen-001.2-business-spec"
        _seed_module(fs, "module-001")
        _write_artifact(key, {"data": True})
        result = _list_artifacts()
        item = next((x for x in result if x["key"] == key), None)
        assert item is not None
        assert item["status"] == "written"


# ── GC — Guard Conditions ─────────────────────────────────────────────────────

class TestGuardConditions:
    def test_gc1_valid_project_key_returns_none(self):
        assert _validate_artifact_key("project.1-foundation.prd") is None

    def test_gc2_valid_module_key_returns_none(self):
        assert _validate_artifact_key("module-001.screen-001.2-business-spec") is None

    def test_gc3_empty_string_returns_error(self):
        result = _validate_artifact_key("")
        assert "error" in result

    def test_gc4_two_parts_returns_error(self):
        result = _validate_artifact_key("project.prd")
        assert "error" in result

    def test_gc5_one_part_returns_error(self):
        result = _validate_artifact_key("prd")
        assert "error" in result

    def test_gc6_error_message_includes_key(self):
        result = _validate_artifact_key("bad.key")
        assert "bad.key" in result["error"]

    def test_gc7_read_artifact_invalid_key_returns_error(self, fs):
        result = _read_artifact("project.prd")
        assert "error" in result

    def test_gc8_read_artifact_empty_key_returns_error(self, fs):
        result = _read_artifact("")
        assert "error" in result

    def test_gc9_write_artifact_invalid_key_returns_error(self, fs):
        result = _write_artifact("bad.key", {"ver": 1})
        assert "error" in result

    def test_gc10_write_artifact_invalid_key_does_not_write(self, fs):
        _write_artifact("bad.key", {"ver": 1})
        assert not any(f.name == "key.json" for f in fs.rglob("*.json"))

    def test_gc11_write_artifact_ok_still_true_for_valid_key(self, fs):
        result = _write_artifact("project.1-foundation.prd", {"ver": 1})
        assert result.get("ok") is True

    def test_gc12_read_artifact_scheme_invalid_key_returns_error(self, fs):
        result = _read_artifact_scheme("project.prd")
        assert "error" in result

    def test_gc13_read_artifact_scheme_empty_key_returns_error(self, fs):
        result = _read_artifact_scheme("")
        assert "error" in result

    def test_gc14_module_key_four_parts_returns_error(self, fs):
        result = _write_artifact("module-001.screen-001.2-business-spec.extra", {"ver": 1})
        assert "error" in result

    def test_gc15_module_key_four_parts_read_returns_error(self, fs):
        result = _read_artifact("module-001.screen-001.2-business-spec.extra")
        assert "error" in result


# ── VS — _validate_structure ──────────────────────────────────────────────────
# Direct unit tests — no filesystem needed.

FLAT_TEMPLATE = {
    "ver":      1,
    "title":    "",
    "active":   True,
}

NESTED_TEMPLATE = {
    "ver":  1,
    "meta": {
        "title":      "",
        "updated_at": "",
    },
    "goals": [
        {"item": "", "reason": ""}
    ],
    "tags":  [""],
    "notes": None,
}

DEEP_TEMPLATE = {
    "level_1": {
        "level_2": {
            "level_3": {
                "key_a": "",
                "key_b": "",
            }
        }
    }
}


class TestValidateStructure:
    # ── flat dict ──────────────────────────────────────────────────────────────

    def test_vs1_valid_flat_dict_no_errors(self):
        data = {"ver": 1, "title": "App", "active": True}
        assert _validate_structure(data, FLAT_TEMPLATE) == []

    def test_vs2_unknown_top_level_key(self):
        data = {"ver": 1, "title": "App", "active": True, "extra": "x"}
        errors = _validate_structure(data, FLAT_TEMPLATE)
        assert any("unknown key" in e and "extra" in e for e in errors)

    def test_vs3_missing_required_key(self):
        data = {"ver": 1, "title": "App"}   # missing "active"
        errors = _validate_structure(data, FLAT_TEMPLATE)
        assert any("missing key" in e and "active" in e for e in errors)

    def test_vs4_str_field_wrong_type(self):
        data = {"ver": 1, "title": 99, "active": True}
        errors = _validate_structure(data, FLAT_TEMPLATE)
        assert any("title" in e and "expected str" in e for e in errors)

    def test_vs5_int_field_wrong_type(self):
        data = {"ver": "1", "title": "App", "active": True}
        errors = _validate_structure(data, FLAT_TEMPLATE)
        assert any("ver" in e and "expected number" in e for e in errors)

    def test_vs6_bool_field_wrong_type(self):
        data = {"ver": 1, "title": "App", "active": "yes"}
        errors = _validate_structure(data, FLAT_TEMPLATE)
        assert any("active" in e and "expected bool" in e for e in errors)

    def test_vs7_bool_true_not_treated_as_int(self):
        # True must pass a bool field, not an int field
        tmpl = {"flag": True, "count": 1}
        assert _validate_structure({"flag": True, "count": 1}, tmpl) == []

    def test_vs8_true_fails_int_field(self):
        # bool True should fail an int/number field
        tmpl  = {"count": 1}
        errors = _validate_structure({"count": True}, tmpl)
        assert any("count" in e for e in errors)

    def test_vs9_null_template_value_skips_constraint(self):
        data = {"ver": 1, "title": "App", "active": True, "notes": "anything"}
        tmpl = {**FLAT_TEMPLATE, "notes": None}
        assert _validate_structure(data, tmpl) == []

    # ── nested dict ───────────────────────────────────────────────────────────

    def test_vs10_valid_nested_dict_no_errors(self):
        data = {
            "ver":  1,
            "meta": {"title": "App", "updated_at": "2026-01-01"},
            "goals": [{"item": "x", "reason": "y"}],
            "tags":  ["alpha"],
            "notes": None,
        }
        assert _validate_structure(data, NESTED_TEMPLATE) == []

    def test_vs11_nested_dict_wrong_type(self):
        data = {
            "ver":   1,
            "meta":  "not a dict",
            "goals": [],
            "tags":  [],
            "notes": None,
        }
        errors = _validate_structure(data, NESTED_TEMPLATE)
        assert any("meta" in e and "expected dict" in e for e in errors)

    def test_vs12_nested_dict_missing_inner_key(self):
        data = {
            "ver":   1,
            "meta":  {"title": "App"},    # missing "updated_at"
            "goals": [],
            "tags":  [],
            "notes": None,
        }
        errors = _validate_structure(data, NESTED_TEMPLATE)
        assert any("meta.updated_at" in e and "missing" in e for e in errors)

    def test_vs13_nested_dict_unknown_inner_key(self):
        data = {
            "ver":   1,
            "meta":  {"title": "App", "updated_at": "", "extra": "x"},
            "goals": [],
            "tags":  [],
            "notes": None,
        }
        errors = _validate_structure(data, NESTED_TEMPLATE)
        assert any("meta.extra" in e and "unknown" in e for e in errors)

    def test_vs14_nested_dict_inner_type_mismatch(self):
        data = {
            "ver":   1,
            "meta":  {"title": 99, "updated_at": ""},
            "goals": [],
            "tags":  [],
            "notes": None,
        }
        errors = _validate_structure(data, NESTED_TEMPLATE)
        assert any("meta.title" in e and "expected str" in e for e in errors)

    # ── list of dicts ─────────────────────────────────────────────────────────

    def test_vs15_list_field_wrong_type(self):
        data = {
            "ver":   1,
            "meta":  {"title": "", "updated_at": ""},
            "goals": "not a list",
            "tags":  [],
            "notes": None,
        }
        errors = _validate_structure(data, NESTED_TEMPLATE)
        assert any("goals" in e and "expected list" in e for e in errors)

    def test_vs16_list_of_dicts_valid(self):
        data = {
            "ver":   1,
            "meta":  {"title": "", "updated_at": ""},
            "goals": [{"item": "ship", "reason": "value"}],
            "tags":  [],
            "notes": None,
        }
        assert _validate_structure(data, NESTED_TEMPLATE) == []

    def test_vs17_list_of_dicts_item_missing_key(self):
        data = {
            "ver":   1,
            "meta":  {"title": "", "updated_at": ""},
            "goals": [{"item": "ship"}],    # missing "reason"
            "tags":  [],
            "notes": None,
        }
        errors = _validate_structure(data, NESTED_TEMPLATE)
        assert any("goals[0].reason" in e and "missing" in e for e in errors)

    def test_vs18_list_of_dicts_item_unknown_key(self):
        data = {
            "ver":   1,
            "meta":  {"title": "", "updated_at": ""},
            "goals": [{"item": "ship", "reason": "value", "foo": "x"}],
            "tags":  [],
            "notes": None,
        }
        errors = _validate_structure(data, NESTED_TEMPLATE)
        assert any("goals[0].foo" in e and "unknown" in e for e in errors)

    def test_vs19_list_of_dicts_item_not_a_dict(self):
        data = {
            "ver":   1,
            "meta":  {"title": "", "updated_at": ""},
            "goals": ["not a dict"],
            "tags":  [],
            "notes": None,
        }
        errors = _validate_structure(data, NESTED_TEMPLATE)
        assert any("goals[0]" in e and "expected dict" in e for e in errors)

    def test_vs20_list_of_dicts_second_item_error_reports_index_1(self):
        data = {
            "ver":   1,
            "meta":  {"title": "", "updated_at": ""},
            "goals": [
                {"item": "ok",  "reason": "ok"},
                {"item": "bad"},              # index 1, missing "reason"
            ],
            "tags":  [],
            "notes": None,
        }
        errors = _validate_structure(data, NESTED_TEMPLATE)
        assert any("goals[1].reason" in e for e in errors)

    # ── list of strings ───────────────────────────────────────────────────────

    def test_vs21_list_of_strings_valid(self):
        data = {
            "ver":   1,
            "meta":  {"title": "", "updated_at": ""},
            "goals": [],
            "tags":  ["alpha", "beta"],
            "notes": None,
        }
        assert _validate_structure(data, NESTED_TEMPLATE) == []

    def test_vs22_list_of_strings_item_wrong_type(self):
        data = {
            "ver":   1,
            "meta":  {"title": "", "updated_at": ""},
            "goals": [],
            "tags":  ["ok", 99],
            "notes": None,
        }
        errors = _validate_structure(data, NESTED_TEMPLATE)
        assert any("tags[1]" in e and "expected str" in e for e in errors)

    # ── empty list template ───────────────────────────────────────────────────

    def test_vs23_empty_list_template_accepts_any_items(self):
        tmpl = {"items": []}
        assert _validate_structure({"items": [1, "x", {}]}, tmpl) == []

    def test_vs24_empty_list_template_rejects_non_list(self):
        tmpl   = {"items": []}
        errors = _validate_structure({"items": "not a list"}, tmpl)
        assert any("items" in e and "expected list" in e for e in errors)

    # ── deep nesting ──────────────────────────────────────────────────────────

    def test_vs25_valid_deep_nesting_no_errors(self):
        data = {"level_1": {"level_2": {"level_3": {"key_a": "x", "key_b": "y"}}}}
        assert _validate_structure(data, DEEP_TEMPLATE) == []

    def test_vs26_deep_nesting_missing_leaf_key(self):
        data   = {"level_1": {"level_2": {"level_3": {"key_a": "x"}}}}
        errors = _validate_structure(data, DEEP_TEMPLATE)
        assert any("level_1.level_2.level_3.key_b" in e and "missing" in e for e in errors)

    def test_vs27_deep_nesting_unknown_leaf_key(self):
        data   = {"level_1": {"level_2": {"level_3": {"key_a": "x", "key_b": "y", "key_c": "z"}}}}
        errors = _validate_structure(data, DEEP_TEMPLATE)
        assert any("level_1.level_2.level_3.key_c" in e and "unknown" in e for e in errors)

    def test_vs28_deep_nesting_type_mismatch_at_level_2(self):
        data   = {"level_1": {"level_2": "not a dict"}}
        errors = _validate_structure(data, DEEP_TEMPLATE)
        assert any("level_1.level_2" in e and "expected dict" in e for e in errors)

    # ── multiple errors ───────────────────────────────────────────────────────

    def test_vs29_multiple_errors_all_reported(self):
        data   = {"ver": "wrong", "extra": "x"}  # missing title+active, unknown extra, wrong ver type
        errors = _validate_structure(data, FLAT_TEMPLATE)
        assert len(errors) >= 3

    def test_vs30_empty_data_reports_all_missing(self):
        errors = _validate_structure({}, FLAT_TEMPLATE)
        keys   = {"ver", "title", "active"}
        assert all(any(k in e for e in errors) for k in keys)


# ── VW — _write_artifact with template validation ─────────────────────────────

PRD_TEMPLATE = {
    "ver":   1,
    "meta":  {"title": "", "updated_at": ""},
    "goals": [{"item": "", "reason": ""}],
    "tags":  [""],
}


class TestWriteArtifactValidation:
    def test_vw1_no_template_write_succeeds(self, fs):
        """Graceful degradation: no template → no validation → write proceeds."""
        result = _write_artifact("project.1-foundation.prd", {"anything": True})
        assert result.get("ok") is True

    def test_vw2_valid_data_matches_template_write_succeeds(self, fs):
        _write_template(fs, "project.1-foundation.prd", PRD_TEMPLATE)
        data = {
            "ver":   1,
            "meta":  {"title": "App", "updated_at": "2026-01-01"},
            "goals": [{"item": "ship", "reason": "value"}],
            "tags":  ["alpha"],
        }
        result = _write_artifact("project.1-foundation.prd", data)
        assert result.get("ok") is True

    def test_vw3_unknown_key_returns_error(self, fs):
        _write_template(fs, "project.1-foundation.prd", PRD_TEMPLATE)
        data = {
            "ver":   1,
            "meta":  {"title": "", "updated_at": ""},
            "goals": [],
            "tags":  [],
            "extra": "x",
        }
        result = _write_artifact("project.1-foundation.prd", data)
        assert "error" in result
        assert "extra" in result["error"]

    def test_vw4_unknown_key_does_not_write_file(self, fs):
        _write_template(fs, "project.1-foundation.prd", PRD_TEMPLATE)
        _write_artifact("project.1-foundation.prd", {"ver": 1, "bad": "x"})
        assert not _at(fs, "project.1-foundation.prd").with_suffix("").with_suffix(".json")\
            .exists() if False else not _ap(fs, "project.1-foundation.prd").exists()

    def test_vw5_missing_key_returns_error(self, fs):
        _write_template(fs, "project.1-foundation.prd", PRD_TEMPLATE)
        data = {"ver": 1, "meta": {"title": "", "updated_at": ""}, "goals": []}
        # missing "tags"
        result = _write_artifact("project.1-foundation.prd", data)
        assert "error" in result
        assert "tags" in result["error"]

    def test_vw6_type_mismatch_returns_error(self, fs):
        _write_template(fs, "project.1-foundation.prd", PRD_TEMPLATE)
        data = {
            "ver":   "one",   # should be number
            "meta":  {"title": "", "updated_at": ""},
            "goals": [],
            "tags":  [],
        }
        result = _write_artifact("project.1-foundation.prd", data)
        assert "error" in result
        assert "ver" in result["error"]

    def test_vw7_nested_type_mismatch_returns_error(self, fs):
        _write_template(fs, "project.1-foundation.prd", PRD_TEMPLATE)
        data = {
            "ver":   1,
            "meta":  {"title": 99, "updated_at": ""},   # title should be str
            "goals": [],
            "tags":  [],
        }
        result = _write_artifact("project.1-foundation.prd", data)
        assert "error" in result
        assert "meta.title" in result["error"]

    def test_vw8_list_item_missing_key_returns_error(self, fs):
        _write_template(fs, "project.1-foundation.prd", PRD_TEMPLATE)
        data = {
            "ver":   1,
            "meta":  {"title": "", "updated_at": ""},
            "goals": [{"item": "ship"}],   # missing "reason"
            "tags":  [],
        }
        result = _write_artifact("project.1-foundation.prd", data)
        assert "error" in result
        assert "goals[0].reason" in result["error"]

    def test_vw9_error_contains_artifact_key(self, fs):
        _write_template(fs, "project.1-foundation.prd", PRD_TEMPLATE)
        result = _write_artifact("project.1-foundation.prd", {"ver": 1})
        assert "project.1-foundation.prd" in result["error"]

    def test_vw10_validation_error_has_no_ok_field(self, fs):
        _write_template(fs, "project.1-foundation.prd", PRD_TEMPLATE)
        result = _write_artifact("project.1-foundation.prd", {"bad": True})
        assert "ok" not in result


# ── EP — _parse_edit_path / _render_path ──────────────────────────────────────

class TestParseEditPath:
    def test_ep1_single_key(self):
        assert _parse_edit_path("ver") == ["ver"]

    def test_ep2_dotted_keys(self):
        assert _parse_edit_path("meta.updated_at") == ["meta", "updated_at"]

    def test_ep3_index_then_key(self):
        assert _parse_edit_path("endpoints[34].screen_id") == ["endpoints", 34, "screen_id"]

    def test_ep4_leading_list_index(self):
        assert _parse_edit_path("items[0]") == ["items", 0]

    def test_ep5_consecutive_indices(self):
        assert _parse_edit_path("grid[1][2]") == ["grid", 1, 2]

    def test_ep6_deep_mixed(self):
        assert _parse_edit_path("a.b[3].c[0].d") == ["a", "b", 3, "c", 0, "d"]

    def test_ep7_index_zero_is_not_falsy_dropped(self):
        # 0 is falsy — a naive truthiness check would drop it.
        assert _parse_edit_path("a[0].b") == ["a", 0, "b"]

    def test_ep8_large_index_parsed_as_int(self):
        segs = _parse_edit_path("endpoints[163]")
        assert segs == ["endpoints", 163]
        assert isinstance(segs[1], int)

    def test_ep9_empty_string_rejected(self):
        assert "error" in _parse_edit_path("")

    def test_ep10_whitespace_only_rejected(self):
        assert "error" in _parse_edit_path("   ")

    def test_ep11_non_string_rejected(self):
        assert "error" in _parse_edit_path(42)

    def test_ep12_empty_segment_rejected(self):
        assert "error" in _parse_edit_path("a..b")

    def test_ep13_trailing_dot_rejected(self):
        assert "error" in _parse_edit_path("a.")

    def test_ep14_garbage_after_index_rejected(self):
        assert "error" in _parse_edit_path("a[0]x")

    def test_ep15_render_round_trips(self):
        for raw in ("ver", "meta.updated_at", "endpoints[34].screen_id", "grid[1][2]", "a.b[3].c[0].d"):
            assert _render_path(_parse_edit_path(raw)) == raw


# ── AE — _apply_edit ──────────────────────────────────────────────────────────

class TestApplyEdit:
    def test_ae1_sets_top_level_key(self):
        doc = {"ver": 1}
        assert _apply_edit(doc, ["ver"], 2, "ver") is None
        assert doc["ver"] == 2

    def test_ae2_sets_nested_key(self):
        doc = {"meta": {"updated_at": "old"}}
        _apply_edit(doc, ["meta", "updated_at"], "new", "meta.updated_at")
        assert doc["meta"]["updated_at"] == "new"

    def test_ae3_sets_value_inside_list_item(self):
        doc = {"endpoints": [{"screen_id": "a"}, {"screen_id": "b"}]}
        _apply_edit(doc, ["endpoints", 1, "screen_id"], "z", "endpoints[1].screen_id")
        assert doc["endpoints"][1]["screen_id"] == "z"
        assert doc["endpoints"][0]["screen_id"] == "a"

    def test_ae4_replaces_whole_list_element(self):
        doc = {"items": ["a", "b"]}
        _apply_edit(doc, ["items", 0], "z", "items[0]")
        assert doc["items"] == ["z", "b"]

    def test_ae5_missing_final_key_rejected(self):
        doc = {"ver": 1}
        err = _apply_edit(doc, ["nope"], 1, "nope")
        assert err and "does not exist" in err["error"]

    def test_ae6_missing_intermediate_key_rejected(self):
        doc = {"meta": {}}
        err = _apply_edit(doc, ["meta", "a", "b"], 1, "meta.a.b")
        assert err and "does not exist" in err["error"]

    def test_ae7_index_out_of_range_rejected(self):
        doc = {"items": ["a"]}
        err = _apply_edit(doc, ["items", 5], "z", "items[5]")
        assert err and "out of range" in err["error"]

    def test_ae8_intermediate_index_out_of_range_rejected(self):
        doc = {"items": [{"a": 1}]}
        err = _apply_edit(doc, ["items", 5, "a"], 2, "items[5].a")
        assert err and "out of range" in err["error"]

    def test_ae9_index_into_dict_rejected(self):
        doc = {"meta": {"a": 1}}
        err = _apply_edit(doc, ["meta", 0], 1, "meta[0]")
        assert err and "not a list" in err["error"]

    def test_ae10_key_into_list_rejected(self):
        doc = {"items": ["a"]}
        err = _apply_edit(doc, ["items", "a"], 1, "items.a")
        assert err and "not an object" in err["error"]

    def test_ae11_traversing_into_scalar_rejected(self):
        doc = {"ver": 1}
        err = _apply_edit(doc, ["ver", "x"], 1, "ver.x")
        assert err and "not an object" in err["error"]

    def test_ae12_error_names_the_failing_path(self):
        doc = {"a": {"b": {}}}
        err = _apply_edit(doc, ["a", "b", "c"], 1, "a.b.c")
        assert "a.b.c" in err["error"] and "a.b.c" != err["error"]

    def test_ae13_failed_edit_leaves_doc_untouched(self):
        doc = {"a": {"b": 1}}
        _apply_edit(doc, ["a", "nope"], 2, "a.nope")
        assert doc == {"a": {"b": 1}}

    def test_ae14_can_set_value_to_none(self):
        doc = {"a": 1}
        assert _apply_edit(doc, ["a"], None, "a") is None
        assert doc["a"] is None

    def test_ae15_can_set_nested_structure_as_value(self):
        doc = {"a": 1}
        _apply_edit(doc, ["a"], {"b": [1, 2]}, "a")
        assert doc["a"] == {"b": [1, 2]}


# ── PA — _patch_artifact ──────────────────────────────────────────────────────

class TestPatchArtifact:
    def test_pa1_patches_single_field(self, fs):
        _write_artifact("project.1-foundation.prd", {"ver": 1, "goals": "old"})
        result = _patch_artifact("project.1-foundation.prd", [{"path": "goals", "value": "new"}])
        assert result["ok"] is True
        assert read_file(_ap(fs, "project.1-foundation.prd"))["goals"] == "new"

    def test_pa2_leaves_every_other_field_byte_identical(self, fs):
        original = {
            "ver": 1,
            "meta": {"title": "T", "updated_at": "2026-01-01"},
            "endpoints": [{"path": "/a", "screen_id": "s1"}, {"path": "/b", "screen_id": "s2"}],
            "notes": ["one", "two", "three"],
        }
        _write_artifact("project.1-foundation.prd", original)
        _patch_artifact("project.1-foundation.prd", [
            {"path": "endpoints[1].screen_id", "value": "CHANGED"},
        ])
        after = read_file(_ap(fs, "project.1-foundation.prd"))
        assert after["endpoints"][1]["screen_id"] == "CHANGED"
        # This is the whole reason the tool exists: nothing else moved.
        assert after["ver"] == original["ver"]
        assert after["meta"] == original["meta"]
        assert after["notes"] == original["notes"]
        assert after["endpoints"][0] == {"path": "/a", "screen_id": "s1"}

    def test_pa3_applies_several_edits_in_one_call(self, fs):
        _write_artifact("project.1-foundation.prd", {"ver": 1, "meta": {"updated_at": "old"}, "goals": "g"})
        result = _patch_artifact("project.1-foundation.prd", [
            {"path": "ver", "value": 2},
            {"path": "meta.updated_at", "value": "2026-10-01"},
            {"path": "goals", "value": "g2"},
        ])
        assert result["edits_applied"] == 3
        data = read_file(_ap(fs, "project.1-foundation.prd"))
        assert (data["ver"], data["meta"]["updated_at"], data["goals"]) == (2, "2026-10-01", "g2")

    def test_pa4_reports_changed_fields_from_schema(self, fs):
        _write_schema(fs, "project.1-foundation.prd", SAMPLE_SCHEMA)
        _write_artifact("project.1-foundation.prd", {"goals": "old", "problem_statement": "same"})
        result = _patch_artifact("project.1-foundation.prd", [{"path": "goals", "value": "new"}])
        assert result["changed_fields"] == ["goals"]

    def test_pa5_untracked_field_absent_from_changed_fields(self, fs):
        _write_schema(fs, "project.1-foundation.prd", SAMPLE_SCHEMA)
        _write_artifact("project.1-foundation.prd", {"ver": 1, "goals": "g"})
        result = _patch_artifact("project.1-foundation.prd", [{"path": "ver", "value": 2}])
        assert result["changed_fields"] == []

    def test_pa6_no_schema_yields_empty_changed_fields(self, fs):
        _write_artifact("project.1-foundation.prd", {"goals": "old"})
        result = _patch_artifact("project.1-foundation.prd", [{"path": "goals", "value": "new"}])
        assert result["changed_fields"] == []

    def test_pa7_patching_unwritten_artifact_rejected(self, fs):
        result = _patch_artifact("project.1-foundation.prd", [{"path": "ver", "value": 1}])
        assert "error" in result
        assert "artifact__write" in result["error"]

    def test_pa8_invalid_artifact_key_rejected(self, fs):
        assert "error" in _patch_artifact("project.prd", [{"path": "ver", "value": 1}])

    def test_pa9_empty_edits_rejected(self, fs):
        _write_artifact("project.1-foundation.prd", {"ver": 1})
        assert "error" in _patch_artifact("project.1-foundation.prd", [])

    def test_pa10_non_list_edits_rejected(self, fs):
        _write_artifact("project.1-foundation.prd", {"ver": 1})
        assert "error" in _patch_artifact("project.1-foundation.prd", {"path": "ver", "value": 2})

    def test_pa11_edit_missing_value_key_rejected(self, fs):
        _write_artifact("project.1-foundation.prd", {"ver": 1})
        result = _patch_artifact("project.1-foundation.prd", [{"path": "ver"}])
        assert "error" in result and "value" in result["error"]

    def test_pa12_edit_missing_path_key_rejected(self, fs):
        _write_artifact("project.1-foundation.prd", {"ver": 1})
        result = _patch_artifact("project.1-foundation.prd", [{"value": 2}])
        assert "error" in result and "path" in result["error"]

    def test_pa13_nonexistent_path_rejected_and_nothing_written(self, fs):
        _write_artifact("project.1-foundation.prd", {"ver": 1, "goals": "g"})
        result = _patch_artifact("project.1-foundation.prd", [{"path": "typo", "value": 1}])
        assert "error" in result
        assert read_file(_ap(fs, "project.1-foundation.prd")) == {"ver": 1, "goals": "g"}

    def test_pa14_later_bad_edit_rolls_back_earlier_good_ones(self, fs):
        _write_artifact("project.1-foundation.prd", {"ver": 1, "goals": "g"})
        result = _patch_artifact("project.1-foundation.prd", [
            {"path": "goals", "value": "CHANGED"},   # valid
            {"path": "nope",  "value": 1},           # invalid -> whole call must abort
        ])
        assert "error" in result and "edits[1]" in result["error"]
        # All-or-nothing: the first edit must NOT have landed on disk.
        assert read_file(_ap(fs, "project.1-foundation.prd"))["goals"] == "g"

    def test_pa15_error_identifies_which_edit_failed(self, fs):
        _write_artifact("project.1-foundation.prd", {"ver": 1})
        result = _patch_artifact("project.1-foundation.prd", [
            {"path": "ver", "value": 2},
            {"path": "ver", "value": 3},
            {"path": "bad", "value": 4},
        ])
        assert "edits[2]" in result["error"]

    def test_pa16_template_validation_still_enforced(self, fs):
        # Template allows only ver/goals; patching a value to a wrong type must be caught.
        _write_template(fs, "project.1-foundation.prd", {"ver": 1, "goals": "text"})
        _write_artifact("project.1-foundation.prd", {"ver": 1, "goals": "text"})
        result = _patch_artifact("project.1-foundation.prd", [{"path": "goals", "value": 123}])
        assert "error" in result and "Validation failed" in result["error"]
        assert read_file(_ap(fs, "project.1-foundation.prd"))["goals"] == "text"

    def test_pa17_template_valid_patch_accepted(self, fs):
        _write_template(fs, "project.1-foundation.prd", {"ver": 1, "goals": "text"})
        _write_artifact("project.1-foundation.prd", {"ver": 1, "goals": "text"})
        result = _patch_artifact("project.1-foundation.prd", [{"path": "goals", "value": "other"}])
        assert result["ok"] is True

    def test_pa18_patches_module_artifact(self, fs):
        _write_artifact("module-001.screen-001--login.2-business-spec", {"ver": 1, "name": "Login"})
        result = _patch_artifact("module-001.screen-001--login.2-business-spec",
                                 [{"path": "name", "value": "Masuk"}])
        assert result["ok"] is True
        assert read_file(_map(fs, "module-001.screen-001--login.2-business-spec"))["name"] == "Masuk"

    def test_pa19_result_path_matches_write_path(self, fs):
        _write_artifact("project.1-foundation.prd", {"ver": 1})
        result = _patch_artifact("project.1-foundation.prd", [{"path": "ver", "value": 2}])
        assert result["path"] == str(_ap(fs, "project.1-foundation.prd"))

    def test_pa20_deep_list_edit_preserves_sibling_entries(self, fs):
        endpoints = [{"i": n, "screen_id": f"s{n}"} for n in range(164)]
        _write_artifact("project.1-foundation.prd", {"ver": 1, "endpoints": endpoints})
        _patch_artifact("project.1-foundation.prd", [
            {"path": "endpoints[33].screen_id", "value": "MOVED"},
            {"path": "endpoints[37].screen_id", "value": "MOVED"},
        ])
        after = read_file(_ap(fs, "project.1-foundation.prd"))["endpoints"]
        assert len(after) == 164
        assert after[33]["screen_id"] == "MOVED" and after[37]["screen_id"] == "MOVED"
        untouched = [e for n, e in enumerate(after) if n not in (33, 37)]
        assert untouched == [e for n, e in enumerate(endpoints) if n not in (33, 37)]

    def test_pa21_patch_then_read_round_trips(self, fs):
        _write_artifact("project.1-foundation.prd", {"ver": 1, "meta": {"updated_at": "old"}})
        _patch_artifact("project.1-foundation.prd", [{"path": "meta.updated_at", "value": "2026-10-01"}])
        assert _read_artifact("project.1-foundation.prd")["data"]["meta"]["updated_at"] == "2026-10-01"

    def test_pa22_setting_value_to_none_is_allowed(self, fs):
        _write_artifact("project.1-foundation.prd", {"ver": 1, "goals": "g"})
        result = _patch_artifact("project.1-foundation.prd", [{"path": "goals", "value": None}])
        assert result["ok"] is True
        assert read_file(_ap(fs, "project.1-foundation.prd"))["goals"] is None

    def test_pa23_identical_value_patch_reports_no_changed_fields(self, fs):
        _write_schema(fs, "project.1-foundation.prd", SAMPLE_SCHEMA)
        _write_artifact("project.1-foundation.prd", {"goals": "same"})
        result = _patch_artifact("project.1-foundation.prd", [{"path": "goals", "value": "same"}])
        assert result["ok"] is True and result["changed_fields"] == []


class TestAppendAndExtend:
    """op append/extend — growing a list without restating it.

    Why this exists: `set` can change any value but cannot make a list longer, so
    adding one endpoint to api-index (168 entries) forced a full artifact__write by
    hand. These tests pin both the growth and the guards, because an append that
    silently lands somewhere other than the intended list is worse than a refusal.
    """

    def test_ap1_append_grows_list_by_one_at_the_end(self, fs):
        _write_artifact("project.1-foundation.prd", {"notes": ["one", "two"]})
        result = _patch_artifact("project.1-foundation.prd", [
            {"path": "notes", "op": "append", "value": "three"},
        ])
        assert result["ok"] is True
        assert read_file(_ap(fs, "project.1-foundation.prd"))["notes"] == ["one", "two", "three"]

    def test_ap2_append_leaves_existing_elements_byte_identical(self, fs):
        original = {
            "ver": 1,
            "meta": {"title": "T"},
            "endpoints": [{"path": "/a", "screen_id": "s1"}, {"path": "/b", "screen_id": "s2"}],
        }
        _write_artifact("project.1-foundation.prd", original)
        _patch_artifact("project.1-foundation.prd", [
            {"path": "endpoints", "op": "append", "value": {"path": "/c", "screen_id": "s3"}},
        ])
        after = read_file(_ap(fs, "project.1-foundation.prd"))
        # The entire point: the 2 entries the caller never restated are untouched.
        assert after["endpoints"][0] == {"path": "/a", "screen_id": "s1"}
        assert after["endpoints"][1] == {"path": "/b", "screen_id": "s2"}
        assert after["endpoints"][2] == {"path": "/c", "screen_id": "s3"}
        assert after["ver"] == 1 and after["meta"] == {"title": "T"}

    def test_ap3_append_accepts_a_dict_element(self, fs):
        _write_artifact("project.1-foundation.prd", {"items": []})
        _patch_artifact("project.1-foundation.prd", [
            {"path": "items", "op": "append", "value": {"k": [1, 2], "n": None}},
        ])
        assert read_file(_ap(fs, "project.1-foundation.prd"))["items"] == [{"k": [1, 2], "n": None}]

    def test_ap4_append_reaches_a_list_nested_under_a_list_element(self, fs):
        _write_artifact("project.1-foundation.prd", {
            "contracts": [{"logic": ["a"]}, {"logic": ["b", "c"]}],
        })
        _patch_artifact("project.1-foundation.prd", [
            {"path": "contracts[1].logic", "op": "append", "value": "d"},
        ])
        after = read_file(_ap(fs, "project.1-foundation.prd"))
        assert after["contracts"][1]["logic"] == ["b", "c", "d"]
        assert after["contracts"][0]["logic"] == ["a"]

    def test_ap5_append_to_a_dict_is_refused_naming_the_type(self, fs):
        _write_artifact("project.1-foundation.prd", {"meta": {"title": "T"}})
        result = _patch_artifact("project.1-foundation.prd", [
            {"path": "meta", "op": "append", "value": "x"},
        ])
        assert "error" in result
        assert "needs a list" in result["error"] and "dict" in result["error"]
        # Nothing written: the dict must not have gained a "0" key.
        assert read_file(_ap(fs, "project.1-foundation.prd"))["meta"] == {"title": "T"}

    def test_ap6_append_to_a_scalar_is_refused(self, fs):
        _write_artifact("project.1-foundation.prd", {"goals": "g", "ver": 1})
        for path, typename in (("goals", "str"), ("ver", "int")):
            result = _patch_artifact("project.1-foundation.prd", [
                {"path": path, "op": "append", "value": "x"},
            ])
            assert "error" in result and typename in result["error"]

    def test_ap7_append_to_a_missing_key_is_refused_and_points_at_write(self, fs):
        _write_artifact("project.1-foundation.prd", {"goals": "g"})
        result = _patch_artifact("project.1-foundation.prd", [
            {"path": "notes", "op": "append", "value": "x"},
        ])
        assert "error" in result
        assert "does not exist" in result["error"] and "artifact__write" in result["error"]

    def test_ap8_append_with_out_of_range_parent_index_is_refused(self, fs):
        _write_artifact("project.1-foundation.prd", {"contracts": [{"logic": ["a"]}]})
        result = _patch_artifact("project.1-foundation.prd", [
            {"path": "contracts[5].logic", "op": "append", "value": "x"},
        ])
        assert "error" in result and "out of range" in result["error"]

    def test_ap9_extend_appends_every_element(self, fs):
        _write_artifact("project.1-foundation.prd", {"notes": ["one"]})
        result = _patch_artifact("project.1-foundation.prd", [
            {"path": "notes", "op": "extend", "value": ["two", "three", "four"]},
        ])
        assert result["ok"] is True
        assert read_file(_ap(fs, "project.1-foundation.prd"))["notes"] == [
            "one", "two", "three", "four",
        ]

    def test_ap10_extend_with_a_non_list_value_is_refused_and_suggests_append(self, fs):
        _write_artifact("project.1-foundation.prd", {"notes": ["one"]})
        result = _patch_artifact("project.1-foundation.prd", [
            {"path": "notes", "op": "extend", "value": "two"},
        ])
        assert "error" in result
        assert "needs value to be a list" in result["error"] and "append" in result["error"]
        assert read_file(_ap(fs, "project.1-foundation.prd"))["notes"] == ["one"]

    def test_ap11_extend_with_an_empty_list_is_refused_as_a_no_op(self, fs):
        _write_artifact("project.1-foundation.prd", {"notes": ["one"]})
        result = _patch_artifact("project.1-foundation.prd", [
            {"path": "notes", "op": "extend", "value": []},
        ])
        assert "error" in result and "changes nothing" in result["error"]

    def test_ap12_unknown_op_is_refused_listing_the_valid_ones(self, fs):
        _write_artifact("project.1-foundation.prd", {"notes": ["one"]})
        result = _patch_artifact("project.1-foundation.prd", [
            {"path": "notes", "op": "push", "value": "two"},
        ])
        assert "error" in result
        assert "unknown op 'push'" in result["error"]
        for op in ("set", "append", "extend"):
            assert op in result["error"]

    def test_ap13_omitting_op_still_means_set(self, fs):
        """Back-compat: every call written before append existed must behave the same."""
        _write_artifact("project.1-foundation.prd", {"notes": ["one", "two"]})
        _patch_artifact("project.1-foundation.prd", [
            {"path": "notes", "value": ["replaced"]},
        ])
        assert read_file(_ap(fs, "project.1-foundation.prd"))["notes"] == ["replaced"]

    def test_ap14_explicit_set_op_replaces_as_before(self, fs):
        _write_artifact("project.1-foundation.prd", {"notes": ["one"], "goals": "g"})
        _patch_artifact("project.1-foundation.prd", [
            {"path": "goals", "op": "set", "value": "g2"},
        ])
        assert read_file(_ap(fs, "project.1-foundation.prd"))["goals"] == "g2"

    def test_ap15_mixed_ops_in_one_call_all_apply(self, fs):
        _write_artifact("project.1-foundation.prd", {
            "ver": 1, "notes": ["one"], "endpoints": [{"path": "/a"}],
        })
        result = _patch_artifact("project.1-foundation.prd", [
            {"path": "ver", "value": 2},
            {"path": "notes", "op": "append", "value": "two"},
            {"path": "endpoints", "op": "extend", "value": [{"path": "/b"}, {"path": "/c"}]},
        ])
        assert result["edits_applied"] == 3
        after = read_file(_ap(fs, "project.1-foundation.prd"))
        assert after["ver"] == 2
        assert after["notes"] == ["one", "two"]
        assert [e["path"] for e in after["endpoints"]] == ["/a", "/b", "/c"]

    def test_ap16_a_failing_append_writes_nothing_at_all(self, fs):
        _write_artifact("project.1-foundation.prd", {"ver": 1, "notes": ["one"], "meta": {}})
        result = _patch_artifact("project.1-foundation.prd", [
            {"path": "ver", "value": 2},
            {"path": "notes", "op": "append", "value": "two"},
            {"path": "meta", "op": "append", "value": "boom"},
        ])
        assert "error" in result
        after = read_file(_ap(fs, "project.1-foundation.prd"))
        # Not even the two edits that succeeded in memory reached disk.
        assert after == {"ver": 1, "notes": ["one"], "meta": {}}

    def test_ap17_append_reports_changed_fields_from_schema(self, fs):
        _write_schema(fs, "project.1-foundation.prd", SAMPLE_SCHEMA)
        _write_artifact("project.1-foundation.prd", {"assumptions": ["a"], "goals": "same"})
        result = _patch_artifact("project.1-foundation.prd", [
            {"path": "assumptions", "op": "append", "value": "b"},
        ])
        assert result["ok"] is True and result["changed_fields"] == ["assumptions"]

    def test_ap18_append_still_runs_template_validation(self, fs):
        _write_template(fs, "project.1-foundation.prd", {"ver": 1, "notes": ["string"]})
        _write_artifact("project.1-foundation.prd", {"ver": 1, "notes": ["one"]})
        result = _patch_artifact("project.1-foundation.prd", [
            {"path": "notes", "op": "append", "value": {"not": "a string"}},
        ])
        assert "error" in result and "Validation failed" in result["error"]
        assert read_file(_ap(fs, "project.1-foundation.prd"))["notes"] == ["one"]

    def test_ap19_two_appends_to_the_same_list_land_in_order(self, fs):
        _write_artifact("project.1-foundation.prd", {"notes": ["one"]})
        _patch_artifact("project.1-foundation.prd", [
            {"path": "notes", "op": "append", "value": "two"},
            {"path": "notes", "op": "append", "value": "three"},
        ])
        assert read_file(_ap(fs, "project.1-foundation.prd"))["notes"] == ["one", "two", "three"]

    def test_ap20_append_onto_an_empty_list_works(self, fs):
        _write_artifact("project.1-foundation.prd", {"notes": []})
        _patch_artifact("project.1-foundation.prd", [
            {"path": "notes", "op": "append", "value": "first"},
        ])
        assert read_file(_ap(fs, "project.1-foundation.prd"))["notes"] == ["first"]

    def test_ap21_append_a_list_value_nests_it_rather_than_flattening(self, fs):
        """append is ONE element even when that element is itself a list — extend flattens."""
        _write_artifact("project.1-foundation.prd", {"notes": ["one"]})
        _patch_artifact("project.1-foundation.prd", [
            {"path": "notes", "op": "append", "value": ["two", "three"]},
        ])
        assert read_file(_ap(fs, "project.1-foundation.prd"))["notes"] == ["one", ["two", "three"]]
