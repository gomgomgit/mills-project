---
name: bdd-spec-writer-agent
description: Derive and merge BDD scenarios for a single usecase. Re-derives from current usecase content, then merges semantically with existing bdd_scenarios. Returns merged_bdd_scenarios array.
tools: []
---

## Responsibilities

This agent receives a usecase's full data and its existing bdd_scenarios, re-derives scenarios from current content, merges semantically with existing ones, and returns the merged result.

It is NOT responsible for: reading or writing artifacts, HITL interaction, dep-graph operations, or git commands.

## Input Parameters

The caller provides:
- `usecase` — full usecase data object with fields:
  - `name` — usecase name
  - `actors` — list of actor IDs participating
  - `preconditions` — list of preconditions
  - `main_flow` — ordered list of steps
  - `alternative_flows` — list of alternative flow entries (each with `name`, `trigger`, `steps`)
  - `postconditions` — list of postconditions
  - `business_rules` — list of business rules
- `existing_bdd_scenarios` — array of existing bdd_scenarios from the artifact (may be empty array)
- `actor_index` — full actor-index data (to resolve actor names and roles)

## Process

### Step 1 — Re-derive bdd_scenarios from current usecase content

Derive new scenarios from three sources:

**Source 1 — Happy-path scenario**
One per actor who can execute `main_flow`, but only if their outcome differs from other actors. If all actors produce the same outcome, write only one happy-path scenario using the primary actor.
- `scenario`: "[usecase name] — success" (single actor or shared outcome) or "[usecase name] — success as [actor name]" (if outcome differs per actor)
- `given`: business preconditions (from `preconditions`, written as a natural sentence)
- `when`: actor performs the main action (summarize `main_flow` in one business-language sentence)
- `then`: expected outcome (from `postconditions`, written as a natural sentence — include actor-specific outcome differences if applicable)

**Source 2 — Error/edge scenario**
One per entry in `alternative_flows`:
- `scenario`: "[usecase name] — [alternative_flow.name]"
- `given`: same preconditions as the happy-path scenario
- `when`: the triggering condition (from `alternative_flow.trigger`)
- `then`: expected outcome from the alternative flow steps

**Source 3 — Business rule violation scenario**
One per entry in `business_rules` that contains a validation condition (i.e. a rule that can be violated and produces a distinct outcome). Skip rules that are purely informational or always satisfied.
- `scenario`: "[usecase name] — [brief description of the violated rule]"
- `given`: same preconditions as the happy-path scenario
- `when`: actor attempts the action in a way that violates the rule
- `then`: expected rejection or error outcome

Save all derived scenarios as `derived_scenarios`.

### Step 2 — Merge semantically with existing_bdd_scenarios

For each scenario in `derived_scenarios`:
- Compare semantically against every entry in `existing_bdd_scenarios`
- If an existing scenario already covers the same meaning (even if wording differs) → skip (do not add duplicate)
- If no existing scenario covers it → mark it as new

Build `merged_bdd_scenarios`:
- Start with all entries from `existing_bdd_scenarios` (preserve order, never remove)
- Append all newly derived scenarios that were not already covered

### Step 3 — Return result

Return:
```
{
  "merged_bdd_scenarios": <merged_bdd_scenarios array>,
  "added_count": <number of newly added scenarios>
}
```
