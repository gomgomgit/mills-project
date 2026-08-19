---
description: View or change process-config values (Autonomy level, Mock Generation level, Test Generation level) stored in config.json
allowed-tools:
  - Read
  - Write
---

You are running the `asdlc-config` command.

This command reads and writes `.asdlc/generated/internal/config.json` — the framework's
process-config file (`autonomy_level`, see `CLAUDE.md` §8; `mock_generation_level`, see
`CLAUDE.md` §9; `test_generation_level`, see `CLAUDE.md` §10). It is not an artifact: no MCP tool, no dep-graph node, no schema file. This
command *is* the validation layer for that file — it only ever writes a value it has
confirmed is valid.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Known Config Keys

This is the framework's config-key registry. When a new process-config value is introduced
elsewhere in the framework, add it here — this is the one place that needs to change.

| Key | Valid values | Default | Meaning |
|---|---|---|---|
| `autonomy_level` | `careful` \| `autopilot` | `careful` | See `CLAUDE.md` §8 for what each level means. |
| `mock_generation_level` | `full` \| `none` | `none` | See `CLAUDE.md` §9 for what each level means. |
| `test_generation_level` | `full` \| `none` | `full` | See `CLAUDE.md` §10 for what each level means. |

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Step 1 — Read Current Config

Attempt `Read` on `.asdlc/generated/internal/config.json`.
- Not found → treat as `{}`. Every known key is at its default.
- Found but not valid JSON (e.g. hand-edited and broken) → STOP. Display: "config.json exists
  but isn't valid JSON — fix or delete it manually, then run `/asdlc-config` again." Do not
  attempt to guess-repair it or overwrite it silently.
- Found and valid JSON → save as `current_config`. For any known key missing from the object,
  treat it as being at its default.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Step 2 — Select Key

Display current values for every known key, one line per key from the Known Config Keys table:

> **Current config:**
> - `[key]` = `[current value]`
> - ... (one line per known key)

**If there is exactly one known key** → set `target_key` to that key directly, skip straight
to Step 3, no selection question needed.

**If there is more than one known key** (true today) → ask, one numbered line per known key:

> Which setting do you want to change?
> 1. `[key]` = `[current value]`
> 2. ... (one numbered line per known key)
>
> Type a number, or "cancel".

- `cancel` → STOP. Display: "No changes made."
- A number → save the corresponding key as `target_key`, proceed to Step 3.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Step 3 — Get New Value

Look up `target_key`'s valid-values list from the Known Config Keys table above. Ask:

> New value for `[target_key]`? [valid values for `target_key`, joined with " / "], or "cancel"

- `cancel` → STOP. Display: "No changes made."
- Answer not in `target_key`'s valid-values list → **do not write anything**. Display: "Not a
  valid value for `[target_key]` — must be one of: [target_key's valid values, comma-joined]."
  Ask again (repeat this step; do not silently accept or guess-correct a typo).
- Valid answer → save as `new_value`, proceed to Step 4.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Step 4 — Write (Only If Changed)

If `new_value` equals the current value of `target_key` → skip the write. Display: "Already
`[new_value]` — no change." Stop here.

Otherwise:
1. Take `current_config` (or `{}` if the file didn't exist), set `current_config[target_key] = new_value`.
2. `Write` `.asdlc/generated/internal/config.json` with `current_config`, pretty-printed
   (2-space indent), preserving every other key untouched.

Display:

> `[target_key]`: `[old value]` → `[new value]`
>
> Takes effect on the next command you run — nothing already in progress is affected.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Rules

- Never write `config.json` with a value that isn't in that key's valid-values list — re-ask
  instead, every time, no exceptions.
- Never drop other keys from `config.json` when writing — read the full object first, change
  only the one key, write the full object back.
- Never call any `mcp__asdlc__*` tool — this command does not touch artifacts or the
  dep-graph, by design.
- If the current value already equals the requested new value, do not perform a no-op write.
