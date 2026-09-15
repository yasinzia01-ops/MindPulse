# Ralph Loop

An unattended loop for driving Claude Code against this repo, one focused iteration at a time. Named after the "Ralph Wiggum" technique: run the same simple prompt against the agent repeatedly, in a fresh context each time, letting `PROGRESS.md` and `BLUEPRINT.md` carry state between runs instead of conversation memory.

## Why this shape

A single long-running agent session drifts: context fills up, earlier decisions get forgotten or re-litigated, and the agent loses track of what's actually done vs. assumed. The loop sidesteps that by making every iteration cold-start from the same three files:

- `BLUEPRINT.md` — what the system is and how it's structured (rarely changes)
- `PROGRESS.md` — what's done, what's next, and any gotchas discovered (updated every iteration)
- `SOLUTION.md` — the current state of "is this actually working," written for a human, not the loop

Each iteration should pick exactly one item off `PROGRESS.md`'s "Not yet done" list, do it, verify it, and update `PROGRESS.md` before exiting — never try to clear the whole list in one pass.

## Running it

From the repo root, with the GitHub CLI and Claude Code CLI both authenticated:

```bash
./ralph.sh
```

or invoke a single iteration by hand:

```bash
claude -p "$(cat RALPH_PROMPT.md)" --dangerously-skip-permissions
```

`ralph.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

MAX_ITERATIONS="${1:-20}"

for i in $(seq 1 "$MAX_ITERATIONS"); do
	echo "=== Ralph loop iteration $i/$MAX_ITERATIONS ==="

	claude -p "$(cat RALPH_PROMPT.md)" \
		--dangerously-skip-permissions \
		--max-turns 40

	if git diff --quiet && git diff --cached --quiet; then
		echo "No changes made this iteration — stopping."
		break
	fi

	git add -A
	git commit -m "Ralph loop iteration $i: $(head -1 PROGRESS.md)"
	git push

	sleep 5
done
```

`RALPH_PROMPT.md` (the fixed prompt fed every iteration):

```
Read BLUEPRINT.md and PROGRESS.md in full before doing anything else.

Pick the single highest-priority unchecked item from PROGRESS.md's
"Not yet done / next up" list. If none remain, pick the most
impactful improvement you can justify from BLUEPRINT.md's "Known
gaps" section instead.

Implement it completely — don't leave partial work. Follow the
conventions in BLUEPRINT.md (class/prefix naming, sanitize-in/
escape-out, nonces on admin_post handlers, autoloader map). Do not
introduce a build step, Composer, or npm dependency.

When done:
1. Update PROGRESS.md: move the item from "Not yet done" to "Done",
   and add any new gaps or follow-ups you discovered.
2. If your change affects the architecture described in
   BLUEPRINT.md, update BLUEPRINT.md too.
3. If this changes what a human should expect when they activate
   the plugin, update SOLUTION.md.

Do exactly one item. Do not start a second item even if you have
turns left.
```

## Guardrails

- **Never run this against a repo with uncommitted work you care about** — the loop commits and pushes automatically at the end of every iteration.
- `--dangerously-skip-permissions` is required for unattended operation; only run the loop in a disposable/CI environment or a repo you're fine with being modified without per-action approval.
- Cap `--max-turns` so a stuck iteration fails fast instead of burning the whole budget on one item.
- The loop stops itself once an iteration produces no diff — treat that as "PROGRESS.md's backlog is empty," not as a crash.
- This plugin has no automated test suite yet (tracked in `PROGRESS.md`). Until one exists, every iteration's "verify it" step is manual reasoning, not a test run — be conservative about claiming something works.
