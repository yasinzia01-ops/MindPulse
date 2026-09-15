#!/usr/bin/env bash
set -euo pipefail

# Ralph loop runner — see RALPH_LOOP.md for what this is and why.
# Usage: ./ralph.sh [max_iterations]

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
