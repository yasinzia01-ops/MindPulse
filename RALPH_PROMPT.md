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
