# AGENTS.md — Mandatory Workflow: Prerequisites → Plan → Implement

> This file applies to every AI agent working in this repository. Read it first.
> It overrides any default behavior that would have you start planning or
> implementing immediately.

## Golden rule

**Never plan while prerequisites are unknown. Never create, modify, or delete
any project file before a written plan is explicitly approved by the human
owner.** "Project file" means any file other than the plan itself.

## Why

- A plan built on guesses (undecided libraries, tooling, specs) is invalidated
  the moment those decisions arrive. Planning on unknowns is wasted work.
- Planning is iterative; implementation is a single pass. Both require known
  inputs and a validated plan.

## Workflow — three phases, hard gates between them

### Phase 0 — PREREQUISITES (no planning, no files)

Check these before writing anything, including the plan:

1. Read `specs.md` plus every `README.md` that exists along the path to the
   task folder (READMEs in not-yet-existing folders are naturally absent).
   Inner READMEs add local constraints; they all apply cumulatively.
2. **External dependencies decided**: any third-party library (and version),
   external service/API, build tooling, runtime, or protocol format coming
   from outside this repo must already be chosen by the human. "Some JSON
   library, TBD" is not decided.

If a prerequisite is missing: **ask, don't plan.** Do not write a plan, a
draft plan, or assumption-based variants. Instead ask **semi-open questions**:
one question per unknown, concrete numbered options, an escape hatch
"(N) Something else — please describe", and a recommendation if you have one.
Do not assume a default for an undecided dependency. Once the human answers,
record the decisions in the plan and proceed to Phase 1.

### Phase 1 — PLAN (only the plan file may be written)

1. Read every `README.md` in the folder hierarchy plus `specs.md`. Inner
   READMEs add local constraints; they all apply cumulatively. Re-read the
   relevant spec sections for the task.
2. Write the plan as `a/b/c/plan.md` (where the task happens) covering at least:
   - scope (in / deliberately out);
   - **Decisions**: the Phase 0 prerequisite decisions (dependencies, versions,
     tooling), so the plan is self-contained;
   - file/module layout and naming, each file's responsibility and a sketch of
     its public API (signatures, not bodies);
   - how the spec's invariants are honored (pure core, instruction protocol,
     affected test groups);
   - the test plan mapped to the spec's test groups (§11);
   - open questions for the human.
3. Submit for review: "Review this plan before I write any code."
4. Iterate on feedback until explicit approval. Keep revisions visible in the
   plan file.

Never jump to Phase 2 on your own initiative, however obvious or small the
task seems.

### Phase 2 — IMPLEMENT (only after explicit approval)

"Single pass" means one human prompt; the agent completes the whole
implementation without returning for authorization. It does **not** mean
writing everything in one shot — iterate test-first (write a test, make it
pass, repeat; refactor as needed; keep the suite green).

1. Implement exactly the approved plan. If the plan turns out wrong or
   incomplete, **stop and request a plan amendment** — do not improvise
   structural changes.
2. Create only the files and tests the plan describes.
3. Run the full test suite; report results honestly.
4. End with a summary of what was created, approved deviations (if any), and
   how to build/run/test.

## Hard rules (always in force)

- **No planning while a prerequisite is unknown**; ask instead.
- **No scaffolding before approval** (`dotnet new`, `cargo init`, manifests,
  placeholder files).
- **No speculative work**: implement only what the current task requests.
- **The plan is the only file you may write in Phase 1**, stored where the
  task happens (`a/b/c/plan.md`).
- If in doubt about scope, ask.
- About to plan with an unknown prerequisite? Stop, return to Phase 0.
- About to create a file with no approved plan? Stop, return to Phase 1.

## Approval semantics

Only an explicit statement counts as approval: "Plan approved", "Go ahead",
"Implement phase 2". Silence, absence of objection, or "looks good so far"
is **not** approval.

Likewise, a Phase 0 decision ("use library X version Y") is a prerequisite
decision, **not** plan approval. Plan approval is a separate, explicit
statement given after the plan is reviewed.

## Right to refuse — clarity before code

- **Before planning**: an undecided external dependency or a missing/
  contradicted spec is a **blocker**. Ask a semi-open question (numbered
  options + escape hatch + recommendation). Do not write any part of a plan.
- **Before implementing**: any unresolved ambiguity in the plan (open
  question, two-way reading, missing naming/layout/tooling/test decision) is a
  **blocker**. Ask before writing any code, even after approval. Amend the
  plan with the answer, then implement.
- Ambiguity discovered **during** implementation: stop immediately, ask,
  resume only after the answer is in the plan.
- Ask semi-open questions only: one per point, genuinely distinct options,
  never questions already answered in the READMEs or spec.

---

**Summary: prerequisites known (specs + decided dependencies) → read READMEs
+ spec → plan where the task happens → review → refine → explicit approval →
single-prompt, test-driven implementation. No planning on unknowns; no file
before approval.**

