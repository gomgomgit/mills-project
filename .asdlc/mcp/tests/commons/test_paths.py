"""
Unit tests for mcp.lib.commons.paths

Coverage: 55 acceptance criteria across 7 groups
  RO  — ASDLC_ROOT
  BD  — Base Dirs (dep_graph_dir, template_dir)
  DG  — Dep-graph Files (project_json, modules_json, module_json, module_template)
  AP  — Project Artifact Paths, 3-part flat key (artifact_path, artifact_template, artifact_schema)
  IP  — Project Item Artifact Paths, 4-part key (artifact_path, artifact_template, artifact_schema)
  MP  — Module Artifact Paths (module_artifact_path, module_artifact_template, module_artifact_schema)
  GD  — Guard Conditions (_project_parts, _project_item_parts, _module_parts error handling)

Run:
    cd Agentic-SDLC-v102/.asdlc
    python3 -m pytest mcp/tests/test_paths.py -v
"""
import sys
from pathlib import Path

# Make .asdlc/ the package root so relative imports inside mcp work
sys.path.insert(0, str(Path(__file__).parents[3]))

import mcp.lib.commons.paths as _paths_mod
from mcp.lib.commons.paths import (
    ASDLC_ROOT,
    dep_graph_dir,
    template_dir,
    project_json,
    modules_json,
    module_json,
    module_template,
    artifact_path,
    artifact_template,
    artifact_schema,
    module_artifact_path,
    module_artifact_template,
    module_artifact_schema,
)


# ── helper ─────────────────────────────────────────────────────────────────────

def p(path) -> str:
    """Normalise separators for cross-platform comparison."""
    return str(path).replace("\\", "/")


# ── RO — ASDLC_ROOT ────────────────────────────────────────────────────────────

class TestAsdlcRoot:
    def test_ro1_is_path_object(self):
        assert isinstance(ASDLC_ROOT, Path)

    def test_ro2_ends_with_asdlc(self):
        assert ASDLC_ROOT.name == ".asdlc"

    def test_ro3_is_four_levels_above_paths_py(self):
        expected = Path(_paths_mod.__file__).parents[3]
        assert ASDLC_ROOT == expected


# ── BD — Base Dirs ─────────────────────────────────────────────────────────────

class TestBaseDirs:
    def test_bd1_dep_graph_dir_ends_with_correct_suffix(self):
        assert p(dep_graph_dir()).endswith("generated/internal/dep-graph")

    def test_bd2_dep_graph_dir_is_under_asdlc_root(self):
        assert dep_graph_dir().is_relative_to(ASDLC_ROOT)

    def test_bd3_template_dir_ends_with_correct_suffix(self):
        assert p(template_dir()).endswith("template/internal/dep-graph")

    def test_bd4_template_dir_is_under_asdlc_root(self):
        assert template_dir().is_relative_to(ASDLC_ROOT)


# ── DG — Dep-graph Files ───────────────────────────────────────────────────────

class TestDepGraphFiles:
    def test_dg1_project_json_equals_dep_graph_dir_project_json(self):
        assert project_json() == dep_graph_dir() / "project.json"

    def test_dg2_modules_json_equals_dep_graph_dir_modules_json(self):
        assert modules_json() == dep_graph_dir() / "modules.json"

    def test_dg3_module_json_inserts_module_id(self):
        assert module_json("module-001") == dep_graph_dir() / "module-001.json"

    def test_dg4_module_json_preserves_double_hyphen(self):
        result = module_json("module-001--nama")
        assert "module-001--nama.json" in p(result)

    def test_dg4b_module_json_no_transformation(self):
        mid = "module-abc-XYZ"
        assert module_json(mid) == dep_graph_dir() / f"{mid}.json"

    def test_dg5_module_template_equals_template_dir_module_json(self):
        assert module_template() == template_dir() / "module.json"


# ── AP — Project Artifact Paths ────────────────────────────────────────────────

PRJ_KEY = "project.1-foundation.prd"


class TestProjectArtifactPaths:
    def test_ap1_artifact_path_correct_suffix(self):
        assert p(artifact_path(PRJ_KEY)).endswith("generated/1-foundation/prd.json")

    def test_ap2_artifact_template_correct_suffix(self):
        assert p(artifact_template(PRJ_KEY)).endswith("template/1-foundation/prd.json")

    def test_ap3_artifact_schema_correct_suffix(self):
        assert p(artifact_schema(PRJ_KEY)).endswith("template/1-foundation/prd.schema.json")

    def test_ap4_phase_with_numbers_and_hyphens_preserved(self):
        key = "project.3-tech-spec.erd"
        assert "3-tech-spec" in p(artifact_path(key))
        assert "3-tech-spec" in p(artifact_template(key))
        assert "3-tech-spec" in p(artifact_schema(key))

    def test_ap5_artifact_name_with_hyphens_preserved(self):
        key = "project.1-foundation.arch-spec"
        assert "arch-spec.json" in p(artifact_path(key))
        assert "arch-spec.json" in p(artifact_template(key))
        assert "arch-spec.schema.json" in p(artifact_schema(key))

    def test_ap6_all_three_return_path_objects(self):
        assert isinstance(artifact_path(PRJ_KEY),     Path)
        assert isinstance(artifact_template(PRJ_KEY), Path)
        assert isinstance(artifact_schema(PRJ_KEY),   Path)

    def test_ap7_all_three_under_asdlc_root(self):
        assert artifact_path(PRJ_KEY).is_relative_to(ASDLC_ROOT)
        assert artifact_template(PRJ_KEY).is_relative_to(ASDLC_ROOT)
        assert artifact_schema(PRJ_KEY).is_relative_to(ASDLC_ROOT)

    def test_ap8_artifact_path_under_generated_template_and_schema_under_template(self):
        gen  = ASDLC_ROOT / "generated"
        tmpl = ASDLC_ROOT / "template"
        assert artifact_path(PRJ_KEY).is_relative_to(gen)
        assert artifact_template(PRJ_KEY).is_relative_to(tmpl)
        assert artifact_schema(PRJ_KEY).is_relative_to(tmpl)

    def test_ap9_parts0_not_a_path_segment(self):
        # "project" (parts[0]) must not appear as a directory in the path
        rel = artifact_path(PRJ_KEY).relative_to(ASDLC_ROOT)
        assert rel.parts[0] != "project"


# ── IP — Project Item Artifact Paths (4-part key) ─────────────────────────────

ITEM_KEY       = "project.2-business-spec.usecases.usecase-001--my-usecase"
ITEM_KEY_OTHER = "project.2-business-spec.usecases.usecase-002--other-usecase"


class TestProjectItemArtifactPaths:
    def test_ip1_artifact_path_correct_suffix(self):
        assert p(artifact_path(ITEM_KEY)).endswith(
            "generated/2-business-spec/usecases/usecase-001--my-usecase.json"
        )

    def test_ip2_artifact_template_correct_suffix(self):
        # template is shared: named after the subfolder, not the item
        assert p(artifact_template(ITEM_KEY)).endswith(
            "template/2-business-spec/usecases.json"
        )

    def test_ip3_artifact_schema_correct_suffix(self):
        assert p(artifact_schema(ITEM_KEY)).endswith(
            "template/2-business-spec/usecases.schema.json"
        )

    def test_ip4_template_and_schema_shared_across_items(self):
        # different item IDs → same template and schema
        assert artifact_template(ITEM_KEY)     == artifact_template(ITEM_KEY_OTHER)
        assert artifact_schema(ITEM_KEY)       == artifact_schema(ITEM_KEY_OTHER)

    def test_ip5_different_item_ids_produce_different_content_paths(self):
        assert artifact_path(ITEM_KEY) != artifact_path(ITEM_KEY_OTHER)

    def test_ip6_all_three_return_path_objects(self):
        assert isinstance(artifact_path(ITEM_KEY),     Path)
        assert isinstance(artifact_template(ITEM_KEY), Path)
        assert isinstance(artifact_schema(ITEM_KEY),   Path)

    def test_ip7_all_three_under_asdlc_root(self):
        assert artifact_path(ITEM_KEY).is_relative_to(ASDLC_ROOT)
        assert artifact_template(ITEM_KEY).is_relative_to(ASDLC_ROOT)
        assert artifact_schema(ITEM_KEY).is_relative_to(ASDLC_ROOT)

    def test_ip8_content_under_generated_others_under_template(self):
        gen  = ASDLC_ROOT / "generated"
        tmpl = ASDLC_ROOT / "template"
        assert artifact_path(ITEM_KEY).is_relative_to(gen)
        assert artifact_template(ITEM_KEY).is_relative_to(tmpl)
        assert artifact_schema(ITEM_KEY).is_relative_to(tmpl)

    def test_ip9_content_file_in_subfolder_named_after_subfolder(self):
        path = artifact_path(ITEM_KEY)
        # parent of the .json file must be named "usecases"
        assert path.parent.name == "usecases"

    def test_ip10_is_item_key_true_for_four_part_project_key(self):
        assert _is_item_key("project.2-business-spec.usecases.usecase-001") is True

    def test_ip11_is_item_key_false_for_three_part_project_key(self):
        assert _is_item_key("project.1-foundation.prd") is False

    def test_ip12_is_item_key_false_for_non_project_key(self):
        assert _is_item_key("module-001.screen-001.2-business-spec") is False

    def test_ip13_project_item_parts_extracts_correctly(self):
        result = _project_item_parts("project.2-business-spec.usecases.usecase-001--name")
        assert result == {
            "phase":     "2-business-spec",
            "subfolder": "usecases",
            "item_id":   "usecase-001--name",
        }

    def test_ip14_project_item_parts_raises_for_three_parts(self):
        with pytest.raises(ValueError):
            _project_item_parts("project.1-foundation.prd")

    def test_ip15_3part_key_still_routes_to_flat_path(self):
        # backward compat: existing 3-part keys unaffected by 4-part support
        key = "project.1-foundation.prd"
        assert p(artifact_path(key)).endswith("generated/1-foundation/prd.json")
        assert p(artifact_template(key)).endswith("template/1-foundation/prd.json")
        assert p(artifact_schema(key)).endswith("template/1-foundation/prd.schema.json")


# ── MP — Module Artifact Paths ─────────────────────────────────────────────────

MOD_KEY      = "module-001.screen-001--login.2-business-spec"
MOD_KEY_PLAIN = "module-001.screen-001.2-business-spec"


class TestModuleArtifactPaths:
    def test_mp1_module_artifact_path_correct_suffix(self):
        assert p(module_artifact_path(MOD_KEY)).endswith(
            "generated/2-business-spec/screens/screen-001--login.json"
        )

    def test_mp2_module_artifact_template_correct_suffix(self):
        assert p(module_artifact_template(MOD_KEY_PLAIN)).endswith(
            "template/2-business-spec/screen.json"
        )

    def test_mp3_module_artifact_schema_correct_suffix(self):
        assert p(module_artifact_schema(MOD_KEY_PLAIN)).endswith(
            "template/2-business-spec/screen.schema.json"
        )

    def test_mp4_module_id_ignored_same_screen_phase_same_path(self):
        key1 = "module-001.screen-001.2-business-spec"
        key2 = "module-002.screen-001.2-business-spec"
        assert module_artifact_path(key1)     == module_artifact_path(key2)
        assert module_artifact_template(key1) == module_artifact_template(key2)
        assert module_artifact_schema(key1)   == module_artifact_schema(key2)

    def test_mp5_double_hyphen_screen_id_preserved(self):
        key = "module-001.screen-001--nama.3-tech-spec"
        assert "screen-001--nama.json" in p(module_artifact_path(key))

    def test_mp6_template_and_schema_in_same_directory(self):
        assert module_artifact_template(MOD_KEY_PLAIN).parent == \
               module_artifact_schema(MOD_KEY_PLAIN).parent

    def test_mp7_all_three_return_path_objects(self):
        assert isinstance(module_artifact_path(MOD_KEY),      Path)
        assert isinstance(module_artifact_template(MOD_KEY),  Path)
        assert isinstance(module_artifact_schema(MOD_KEY),    Path)

    def test_mp8_content_under_generated_others_under_template(self):
        gen  = ASDLC_ROOT / "generated"
        tmpl = ASDLC_ROOT / "template"
        assert module_artifact_path(MOD_KEY).is_relative_to(gen)
        assert module_artifact_template(MOD_KEY).is_relative_to(tmpl)
        assert module_artifact_schema(MOD_KEY).is_relative_to(tmpl)

    def test_mp9_content_file_in_screens_subdirectory(self):
        path = module_artifact_path(MOD_KEY_PLAIN)
        # parent of the .json file must be named "screens"
        assert path.parent.name == "screens"


# ── GD — Guard Conditions ──────────────────────────────────────────────────────

import pytest
from mcp.lib.commons.paths import (
    _project_parts, _project_item_parts, _is_item_key, _module_parts,
)


class TestGuardConditions:
    def test_gd1_project_parts_two_parts_raises(self):
        with pytest.raises(ValueError):
            _project_parts("project.only-two")

    def test_gd2_project_parts_one_part_raises(self):
        with pytest.raises(ValueError):
            _project_parts("project")

    def test_gd3_project_parts_three_parts_ok(self):
        result = _project_parts("project.1-foundation.prd")
        assert result == {"phase": "1-foundation", "artifact": "prd"}

    def test_gd4_project_parts_more_than_three_ok(self):
        # >=3 is valid; extra parts ignored
        result = _project_parts("project.1-foundation.prd.extra")
        assert result["phase"] == "1-foundation"
        assert result["artifact"] == "prd"

    def test_gd5_module_parts_exactly_three_ok(self):
        result = _module_parts("module-001.screen-001.2-business-spec")
        assert result == {"screen_id": "screen-001", "phase": "2-business-spec"}

    def test_gd6_module_parts_two_parts_raises(self):
        with pytest.raises(ValueError):
            _module_parts("module-001.screen-001")

    def test_gd7_module_parts_four_parts_raises(self):
        with pytest.raises(ValueError):
            _module_parts("module-001.screen-001.phase.extra")

    def test_gd8_project_parts_error_message_includes_key(self):
        try:
            _project_parts("bad.key")
        except ValueError as e:
            assert "bad.key" in str(e)
        else:
            pytest.fail("Expected ValueError")

    def test_gd9_module_parts_error_message_includes_key(self):
        try:
            _module_parts("bad.key.too.many")
        except ValueError as e:
            assert "bad.key.too.many" in str(e)
        else:
            pytest.fail("Expected ValueError")
