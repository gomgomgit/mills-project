"""
Unit tests for mcp.lib.commons.json_ops

Coverage: 40 acceptance criteria across 7 groups
  RF  — read_file
  WF  — write_file
  PK  — _parse_key
  TV  — _traverse
  SN  — _set_nested
  GV  — get_value
  SV  — set_value

Run:
    cd Agentic-SDLC-v101/.asdlc
    python3 -m pytest mcp/tests/commons/test_json_ops.py -v
"""
import json
import sys
from pathlib import Path

import pytest

sys.path.insert(0, str(Path(__file__).parents[3]))

from mcp.lib.commons.json_ops import (
    read_file,
    write_file,
    get_value,
    set_value,
    _parse_key,
    _traverse,
    _set_nested,
)


# ── RF — read_file ─────────────────────────────────────────────────────────────

class TestReadFile:
    def test_rf1_valid_json_returns_dict(self, tmp_path):
        f = tmp_path / "data.json"
        f.write_text('{"key": "value"}', encoding="utf-8")
        assert read_file(f) == {"key": "value"}

    def test_rf2_missing_file_raises_file_not_found(self, tmp_path):
        with pytest.raises(FileNotFoundError):
            read_file(tmp_path / "ghost.json")

    def test_rf3_invalid_json_raises_decode_error(self, tmp_path):
        f = tmp_path / "bad.json"
        f.write_text("{not valid json}", encoding="utf-8")
        with pytest.raises(json.JSONDecodeError):
            read_file(f)

    def test_rf4_non_ascii_preserved(self, tmp_path):
        f = tmp_path / "data.json"
        f.write_text('{"nama": "Björn"}', encoding="utf-8")
        assert read_file(f)["nama"] == "Björn"

    def test_rf5_nested_structure_preserved(self, tmp_path):
        data = {"a": {"b": [1, 2, {"c": True}]}}
        f = tmp_path / "data.json"
        f.write_text(json.dumps(data), encoding="utf-8")
        assert read_file(f) == data


# ── WF — write_file ────────────────────────────────────────────────────────────

class TestWriteFile:
    def test_wf1_file_created_at_path(self, tmp_path):
        f = tmp_path / "out.json"
        write_file(f, {"x": 1})
        assert f.exists()

    def test_wf2_parent_dirs_created_automatically(self, tmp_path):
        f = tmp_path / "a" / "b" / "c" / "out.json"
        write_file(f, {"x": 1})
        assert f.exists()

    def test_wf3_content_is_valid_json(self, tmp_path):
        f = tmp_path / "out.json"
        write_file(f, {"x": 1})
        parsed = json.loads(f.read_text(encoding="utf-8"))
        assert parsed == {"x": 1}

    def test_wf4_uses_two_space_indentation(self, tmp_path):
        f = tmp_path / "out.json"
        write_file(f, {"key": "val"})
        lines = f.read_text(encoding="utf-8").splitlines()
        # second line must start with exactly two spaces
        assert lines[1].startswith("  ") and not lines[1].startswith("   ")

    def test_wf5_non_ascii_not_escaped(self, tmp_path):
        f = tmp_path / "out.json"
        write_file(f, {"nama": "Björn"})
        raw = f.read_text(encoding="utf-8")
        assert "Björn" in raw
        assert "\\u" not in raw

    def test_wf6_overwrites_existing_file(self, tmp_path):
        f = tmp_path / "out.json"
        write_file(f, {"old": True})
        write_file(f, {"new": True})
        data = json.loads(f.read_text(encoding="utf-8"))
        assert "old" not in data
        assert data["new"] is True

    def test_wf7_roundtrip_write_then_read(self, tmp_path):
        f = tmp_path / "out.json"
        original = {"a": 1, "b": [1, 2], "c": {"d": "e"}}
        write_file(f, original)
        assert read_file(f) == original


# ── PK — _parse_key ────────────────────────────────────────────────────────────

class TestParseKey:
    def test_pk1_single_segment(self):
        assert _parse_key("platform") == ["platform"]

    def test_pk2_dot_notation(self):
        assert _parse_key("meta.title") == ["meta", "title"]

    def test_pk3_array_index_with_child(self):
        assert _parse_key("goals[0].item") == ["goals", 0, "item"]

    def test_pk4_array_index_is_int(self):
        result = _parse_key("goals[0].item")
        assert isinstance(result[1], int)

    def test_pk5_array_access_without_child(self):
        assert _parse_key("items[2]") == ["items", 2]

    def test_pk6_segment_with_hyphen(self):
        assert _parse_key("meta-title.sub") == ["meta-title", "sub"]

    def test_pk7_multi_level_key(self):
        assert _parse_key("a.b.c.d") == ["a", "b", "c", "d"]


# ── TV — _traverse ─────────────────────────────────────────────────────────────

class TestTraverse:
    def test_tv1_nested_dict(self):
        data = {"meta": {"title": "X"}}
        assert _traverse(data, "meta.title") == "X"

    def test_tv2_list_by_index(self):
        data = {"goals": [{"item": "A"}, {"item": "B"}]}
        assert _traverse(data, "goals[0].item") == "A"

    def test_tv3_combined_dict_list_dict(self):
        data = {"a": {"b": [{"c": "deep"}]}}
        assert _traverse(data, "a.b[0].c") == "deep"

    def test_tv4_missing_key_raises_key_error(self):
        with pytest.raises(KeyError):
            _traverse({"a": 1}, "b")

    def test_tv5_out_of_range_index_raises_index_error(self):
        with pytest.raises(IndexError):
            _traverse({"items": [1, 2]}, "items[5]")


# ── SN — _set_nested ───────────────────────────────────────────────────────────

class TestSetNested:
    def test_sn1_top_level_key(self):
        data = {}
        _set_nested(data, "title", "X")
        assert data == {"title": "X"}

    def test_sn2_nested_key(self):
        data = {"meta": {}}
        _set_nested(data, "meta.title", "X")
        assert data["meta"]["title"] == "X"

    def test_sn3_creates_intermediate_dicts(self):
        data = {}
        _set_nested(data, "a.b.c", "deep")
        assert data == {"a": {"b": {"c": "deep"}}}

    def test_sn4_navigate_into_list_element(self):
        data = {"items": [{"name": "old"}]}
        _set_nested(data, "items[0].name", "new")
        assert data["items"][0]["name"] == "new"

    def test_sn5_other_keys_not_disturbed(self):
        data = {"keep": True, "meta": {"title": "A"}}
        _set_nested(data, "meta.title", "B")
        assert data["keep"] is True
        assert data["meta"]["title"] == "B"


# ── GV — get_value ─────────────────────────────────────────────────────────────

class TestGetValue:
    @pytest.fixture
    def sample_file(self, tmp_path):
        f = tmp_path / "data.json"
        write_file(f, {
            "meta": {"title": "MyApp"},
            "goals": [{"item": "Reduce churn"}, {"item": "Ship fast"}],
            "platform": "web",
        })
        return f

    def test_gv1_simple_dot_notation(self, sample_file):
        assert get_value(sample_file, "meta.title") == "MyApp"

    def test_gv2_array_indexing(self, sample_file):
        assert get_value(sample_file, "goals[0].item") == "Reduce churn"

    def test_gv3_returns_full_list(self, sample_file):
        result = get_value(sample_file, "goals")
        assert isinstance(result, list)
        assert len(result) == 2

    def test_gv4_missing_key_raises_key_error(self, sample_file):
        with pytest.raises(KeyError):
            get_value(sample_file, "nonexistent")

    def test_gv5_missing_file_raises_file_not_found(self, tmp_path):
        with pytest.raises(FileNotFoundError):
            get_value(tmp_path / "ghost.json", "key")


# ── SV — set_value ─────────────────────────────────────────────────────────────

class TestSetValue:
    def test_sv1_update_existing_key(self, tmp_path):
        f = tmp_path / "data.json"
        write_file(f, {"meta": {"title": "Old"}})
        set_value(f, "meta.title", "New")
        assert read_file(f)["meta"]["title"] == "New"

    def test_sv2_creates_file_when_missing(self, tmp_path):
        f = tmp_path / "new.json"
        assert not f.exists()
        set_value(f, "title", "Hello")
        assert f.exists()
        assert read_file(f)["title"] == "Hello"

    def test_sv3_creates_intermediate_dicts(self, tmp_path):
        f = tmp_path / "data.json"
        write_file(f, {})
        set_value(f, "a.b.c", "deep")
        assert read_file(f) == {"a": {"b": {"c": "deep"}}}

    def test_sv4_array_indexing_navigation(self, tmp_path):
        f = tmp_path / "data.json"
        write_file(f, {"items": [{"name": "old"}]})
        set_value(f, "items[0].name", "new")
        assert read_file(f)["items"][0]["name"] == "new"

    def test_sv5_other_keys_not_disturbed(self, tmp_path):
        f = tmp_path / "data.json"
        write_file(f, {"keep": True, "change": "old"})
        set_value(f, "change", "new")
        data = read_file(f)
        assert data["keep"] is True
        assert data["change"] == "new"

    def test_sv6_roundtrip_get_after_set(self, tmp_path):
        f = tmp_path / "data.json"
        write_file(f, {"meta": {"title": ""}})
        set_value(f, "meta.title", "MyApp")
        assert get_value(f, "meta.title") == "MyApp"

    def test_pk8_empty_string_raises_value_error(self):
        with pytest.raises(ValueError):
            _parse_key("")

    def test_pk9_error_message_non_empty(self):
        try:
            _parse_key("")
        except ValueError as e:
            assert len(str(e)) > 0
        else:
            pytest.fail("Expected ValueError")
