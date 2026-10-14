# AGENTS.md — Mandatory Workflow: Plan First, Then Implement

> **This file applies to every AI agent working in this repository. Read it before
> doing anything else. It overrides any default behavior that would have you
> start implementing immediately.**

## Golden rule

**Never create, modify, or delete any project file before a written plan has
been reviewed and explicitly approved by the human owner.**

"Project file" means any file other than the plan itself. Showing code snippets
inside the plan document is fine; creating source files, test files, build
files, or scaffolding is not.

## Why

- Planning is an **iterative** process: the plan will go through multiple
  refinements with the human before it is considered complete.
- Implementation is expected to go in a **single pass** — one human prompt —
  once planning is complete. That is only achievable if the plan is precise
  and validated.
- Starting to implement before the plan is approved wastes work, locks in
  wrong decisions, and defeats the review step.

## The workflow — two phases, a hard gate between them

### Phase 1 — PLAN (no project files may be written)

1. **Read the context first**. Documentation is hierarchical: a `README.md`
   exists at several levels of the repo (repo root, each language folder, each
   environment folder). When the task happens in folder `a/b/c`, you **must**
   read every `README.md` in the folder hierarchy — `README.md` (repo root),
   `a/README.md`, `a/b/README.md`, `a/b/c/README.md` — plus `specs.md` at the
   repo root, before proposing anything. Inner READMEs add local constraints
   (build tooling, layout, how the core is referenced); they all apply
   cumulatively. Re-read the relevant sections of the spec (architecture,
   domain model, instruction protocol, test suite) for the task at hand.
2. **Produce a written plan** covering at least:
   - scope of the requested task (what is in, what is deliberately out);
   - file/module layout and naming;
   - for each file: its responsibility and a sketch of its public API /
     key types and functions (signatures, not full bodies);
   - how the spec's invariants are honored (pure core, instruction protocol,
     test groups affected);
   - the language-idiomatic choices made (build tooling, how environments will
     reference the core, data structures, memoization idiom);
   - the test plan mapped to the spec's test groups (§11);
   - open questions for the human.
3. **Submit the plan for review.** Ask explicitly: "Review this plan before I
   write any code."
4. **Iterate.** Apply feedback and resubmit. Repeat until the human says the
   plan is approved. Do not silently "improve" the plan during implementation.
5. **Never jump to Phase 2 on your own initiative**, even if the plan seems
   obvious, even if the task looks small, even if feedback is slow. Wait for
   an explicit approval such as "plan approved" / "go ahead".

### Phase 2 — IMPLEMENT (only after explicit approval)

"Single pass" means **one human prompt**: after approval, the agent completes
the whole implementation without coming back for further authorization. It
does **not** mean writing all code in one shot — the agent **should iterate on
unit tests** (write a test, make it pass, write the next test, make all tests
pass, refactor as needed). Test-driven iteration inside the approved plan is
the expected way of working.

1. Implement exactly the approved plan. If you discover that the plan is
   wrong or incomplete, **stop, report the issue, and request a plan
   amendment** — do not improvise structural changes.
2. Create only the files and tests the plan describes.
3. Iterate test-first: write a test, make it pass, write another test, make
   all tests pass. Keep the suite green at each step of the iteration.
4. Run the full test suite and report results honestly (all green, or the
   exact failures).
5. End with a summary of what was created, deviations (if any were approved
   along the way), and how to build/run/test.

## Hard rules (always in force)

- **No scaffolding, no "getting started" files** before approval. That
  includes: `dotnet new`, `cargo init`, package manifests, empty folders of
  source files, placeholder classes.
- **No speculative work**: do not implement parts of `specs.md` that were not
  requested in the current task (e.g. do not start v2 or environment shells
  when asked for `fsharp/classic/core` only).
- **The plan is stored where the task happens.** If the task is to implement
  `a/b/c`, the plan is `a/b/c/plan.md`. If `a/b/c` does not exist yet, create
  only the plan file there (this is the sole permitted file write in Phase 1).
  The plan is the only file you may write during Phase 1. Keep previous
  versions or clearly mark revisions so refinement history is visible.
- If in doubt about scope, ask. A clarifying question is always cheaper
  than an unapproved implementation.
- **If you (the agent) notice yourself about to create a file while no
  approved plan exists: stop and return to Phase 1.**

## What an approval looks like

Only a clear, explicit human statement counts as approval, for example:
"Plan approved", "Go ahead", "Implement phase 2". Absence of objection,
silence, or "looks good so far" during iteration is **not** approval.

## Right to refuse — clarity before code

**The agent must refuse to implement while the plan still contains unclear
points.** Do not treat approval as pressure to fill gaps by guessing: an
ambiguous plan cannot produce a correct single-pass implementation.

- Any unresolved ambiguity in the plan (an open question left unanswered, a
  requirement that can be read two ways, a missing decision on layout,
  naming, tooling, or test scope) is a **blocker**, not an implementation
  detail to resolve silently.
- In that case the agent **asks for clarification before writing any code**,
  even after the plan has been approved. The human answers, the plan is
  amended, and only then does implementation start.
- **Ask semi-open questions**, not open-ended ones. For each unclear point,
  propose **concrete numbered options** plus an escape hatch, e.g.:

  > This point is unclear: should the persistent map live in
  > (1) a dedicated `Map` module in the core, or
  > (2) the neighbor-counting module only?
  > (3) Something else — please describe.

  One question per point, options that are genuinely distinct, and a
  recommendation if the agent has one. Do not bundle several ambiguities
  into one vague question, and do not ask questions whose answers are
  already in the READMEs or the spec.
- If the ambiguity is discovered **during** implementation, stop immediately,
  ask the semi-open question, and resume only after the answer is
  incorporated into the plan.

---

**Summary: Read every README in the folder hierarchy + the spec → write the
plan where the task happens → human review → refine → repeat → explicit
approval → single-prompt, test-driven implementation. No file before approval.**
