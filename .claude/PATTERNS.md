# PATTERNS.md

Runtime reference for command execution — the two mechanisms every command with a gate needs
at the moment it runs. This is the shipped counterpart to the repo's `PATTERN.md` (design-time
architecture discussion, not deployed with a project); those two sections used to live there
and were moved here because commands need to `Read` this content when they actually execute,
and `PATTERN.md` itself never ships with a deployed project. Command files reference this file
as `` `.claude/PATTERNS.md` ``.

---

## Interview Question Style

When a command asks the user a question during an **interview** or **draft-refine** step, present it
as **labeled options** — `A)`, `B)`, `C)` … — whenever the answer is enumerable or is a
confirm/adjust decision, so the user can reply with a single letter instead of typing a sentence.

- List the **recommended** option first, marked `✓`, with a short reason.
- Always end with `Other — [describe]` (or, for a confirm, `Adjust — [describe what to change]`) so
  the user is never boxed in.
- Derive the concrete options from context at runtime — exactly like the Phase 1 spec commands
  already do (e.g. `[derive 2–3 contextual options from the PRD]`).
- A yes/no confirmation of a proposed draft or value becomes:
  `A) Accept as-is (recommended) ✓   B) Adjust — describe`.

Use **free-text** (no options) **only** for genuinely open inputs: describing the application,
naming it, listing arbitrary items (business rules, edge cases, use-case names), or describing a
specific correction the user has already decided to make. When in doubt and the answer is a small
closed set — offer options.

## HITL Gate vs Digest (Autonomy-Conditional)

Every command's gate branches on the Autonomy level read in Pre-Flight (`autonomy_level` in
`.asdlc/generated/internal/config.json`; meaning of each level defined in `CLAUDE.md` §8). This
section is the shared reference — command files point here rather than re-explaining the
mechanism.

**Level branch:**

- **`careful`** → display the full proposal, blocking `GO / REVISE [section] / STOP`. Existing
  behavior, unchanged.
- **`autopilot`**, for a gate this command's own decision table marks as "digest"
  at that level → write the artifact immediately, then display the Review Digest below
  (non-blocking), continue to the next step without waiting.
- Any gate a command's decision table marks "blocking" at the current level (e.g. the visual
  UIUX gate stays blocking even at `autopilot`) → same as `careful`.
- The five permanent exceptions (`CLAUDE.md` §8) are never digest, at any level.

**Review Digest format:**

```
**{Phase · Section} — written (autonomy: {level})**

Written
  {artifact_key}   v{ver}   ({one-line stat})

⚠ Derived by the agent — not stated explicitly by you
  · {field/value} ← {short reason}
  (omit this whole block if nothing beyond stated input was inferred)

Anything off? Say so — I'll fix it now.
```

The ⚠ block renders directly from the `derived_assumptions` list built during synthesis — see
§ Derived Assumptions Log below. It is no longer best-effort. Omit the block entirely only when
that list is genuinely empty for this write.

**Inline correction + versioning rule** — a same-`ver` rewrite is already precedented at the
visual gate in `fnd-3-uiux-spec.md` ("call `artifact__write` with the same `ver`"); this
generalizes it:

- User corrects something shown in a digest → apply the correction to the data, call
  `artifact__write` again.
- Nothing has been generated from this artifact yet since the last write (no downstream
  dep-graph node created from it) → rewrite with the **same `ver`** — no bump, no
  re-invocation of `dep-graph-sync-agent` needed.
- Something downstream already exists → **bump `ver`** and re-invoke `dep-graph-sync-agent` as
  normal, so downstream is correctly marked stale.
- When unsure which applies → bump. A same-ver rewrite that should have bumped hides a real
  staleness problem; the reverse only costs one unnecessary stale flag.
- If the corrected field had a `derived_assumptions` entry (it was flagged ⚠ because it wasn't
  stated by the user — now it has been), remove that entry from the in-memory list before
  re-appending to the Derived Assumptions Log.
  - **Same-ver** rewrite: `Read` the log file, remove that bullet from the section just
    appended (same version number), and `Write` it back — leaving it in place would have the
    same version number claim two contradictory histories, which is worse than the
    already-accepted "older version is stale" trade-off (see § Derived Assumptions Log).
  - **Bump**: the new `ver` gets its own append, same as any other write — follow the normal
    append mechanics for the bumped version with the corrected `derived_assumptions` (the
    corrected field's entry already removed). Do not skip this just because a section for the
    previous version was already appended earlier in the same run.
- Re-display the corrected digest, then continue.

## Derived Assumptions Log

The structured source behind the ⚠ block above. Two failure modes motivate this design:
retrospective self-review ("at the end, list what you guessed") is where an LLM is most likely
to miss something, since it requires recalling many decisions at once; and anything held only in
conversation memory is vulnerable to context compression over a long `fast-bootstrap` /
`fast-screen` run. This section addresses both — logging happens *at the moment* a field is
derived, and is *persisted to disk* immediately, not carried in working memory alone.

**1. Concurrent-logging instruction** — added once, near the top of a command's synthesis step,
before its field-by-field derivation logic:

> **Initialize `derived_assumptions = []`** — a fresh, empty list scoped strictly to this
> command's current execution. Even if this command is running as one step inside a sequencer
> (`fast-bootstrap`, `fast-screen`) and other commands' assumptions are still visible earlier in
> the conversation, do not include them — those belong to a different artifact and are already
> logged under their own file. This happens **once**, the first time this step runs — if a
> REVISE loop later returns here to correct one field, do not re-initialize and lose entries
> already logged for other, unrelated fields.
>
> As you populate each field above, apply this test: did the user state this value, or are you
> determining it yourself? If the latter, append `{field, value, reason}` to
> `derived_assumptions` **immediately** — before moving to the next field. Do not defer this to
> a review pass at the end.

**2. Audit pass** — one short step, after the draft is fully constructed, immediately before the
gate/digest branch:

> Re-read the finished draft once against the interview transcript. For each field in
> `derived_assumptions`, confirm it's genuinely not stated. For each field *not* in the list,
> spot-check that it truly came from the user. Add any missed entries now.

Runs at **every** Autonomy level, not just `autopilot` — the log is a durable project
record with value independent of whether its contents get displayed in a digest this run (and it
is the data source for a future phase-boundary checkpoint).

**3. File — one per artifact, not one shared file:**
`.asdlc/generated/internal/derived-assumptions/{artifact_key}.md`, e.g.
`derived-assumptions/project.1-foundation.arch-spec.md` or
`derived-assumptions/module-001.screen-001--login.2-business-spec.md` — the filename is the
artifact's own `artifact_key`, the same identifier used everywhere else in the system, so the
path is always derivable without a new naming scheme.

**Why per-artifact, not one shared file or one per module:** a shared file interleaves entries
from unrelated commands in run order, making "what was assumed for this one artifact" hard to
find. Module-level sharding (mirroring `dep-graph`'s `module-{id}.json`) was considered and
rejected — dep-graph shards by module because `dep_graph__sync_stale_status` needs to examine
many related nodes together in one read; this log has no such cross-artifact read need, so
per-artifact is strictly simpler with no downside. Since the artifact_key is already in the
filename, entries don't need to repeat it — just version and date:

```markdown
## v2 — 2026-07-22
- tech_stack.orm = Prisma ← inferred from stack, not stated
- tech_stack.migration = Prisma Migrate ← default for chosen ORM
```

**Append mechanics** — a plain filesystem file, not MCP-routed; requires `Read` + `Write` in the
command's `allowed-tools`. The `Write` tool requires reading an existing file before it can be
overwritten, so:
1. Attempt `Read` on `.asdlc/generated/internal/derived-assumptions/{artifact_key}.md`. Not
   found → treat existing content as empty string. Found → save as `existing_log`.
2. Build `new_section` from this write's `derived_assumptions`, headed `## v{ver} — {date}`. If
   `derived_assumptions` is empty, skip the append entirely — no section for a write with
   nothing derived.
3. `Write` the file with `existing_log + "\n" + new_section`.

For a command that writes multiple artifacts in one run (e.g. `bus-1-scope`'s four indexes,
`tech-1-core`'s entity-catalog + shared-decisions), each artifact gets its own file — append one
section per artifact actually written, using that artifact's own `derived_assumptions` subset.

These log files are also the data source for the two phase-boundary checkpoints —
`asdlc-fast-bootstrap`'s Section 9 (reads the last section of all 13 project-level artifacts'
logs) and `asdlc-fast-screen`'s Section 3b (reads the last section of one screen's 2
pre-implementation artifacts' logs — business + tech spec — for every screen, before Phase 4) — both permanent exceptions (`CLAUDE.md` §8). Neither checkpoint adds
any new accumulation mechanism; they only `Read` what synthesis steps already wrote here.

**Rendering rule (checkpoints and digests):** render every logged bullet **verbatim** — one line
per assumption, in the stored `field = value ← reason` form. Never summarize, collapse multiple
fields into one line, list a field name without its value, or paraphrase the value. To keep this
complete-yet-scannable, two field classes are kept concise **at the source** (at log-write, not
at display):
1. **UIUX-spec** fields (`design_system`, `screen_type_patterns`, `layout`, `accessibility`,
   `component_patterns`, `design_notes`) are logged as a **one-line summary** value — their full
   value is a large nested object already present in the artifact JSON.
2. **`test_fixture`** entries are **not logged at all** — placeholder test data whose errors are
   caught by the tests themselves.
Everything else is logged, and therefore rendered, with its full value.
