# MindPulse — Solution

What this plugin actually does today, written for a human deciding whether it's ready to use — not a feature wishlist. See `BLUEPRINT.md` for architecture and `PROGRESS.md` for the work log.

## The problem this solves

Build and run personality/IQ-style quizzes on WordPress: score a respondent's answers against admin-defined bands, show them a result profile, capture the lead even if they abandon partway through, optionally charge for the full report, and let B2B partners embed a quiz on their own site while their leads stay attributed to them.

## What works right now

- **Building a quiz**: MindPulse → Forms → Add New, a 9-tab editor: Form Details, Test Introduction, Test Sections, Processing Page, Preview Page, Email Capture, Checkout Page, Report Settings, Custom CSS. Group questions into sections, give each option either a point value or a "Correct answer" flag (pick per-quiz under Form Details → Scoring Mode), define score bands (min/max → title/description/image/CTA), and optionally turn on any of the structured funnel pages below.
- **Taking a quiz**: drop `[mindpulse_quiz id="X"]` on any page, or add the "MindPulse Quiz" widget in Elementor and pick the quiz. If Test Introduction is on, the visitor sees an intro screen first; then questions one at a time (grouped by section); then, if Processing Page is on, a brief animated "scoring" screen.
- **Getting a result**: the plugin scores the answers, matches a band, and shows a report (hero title/subtitle, band title/description/image/CTA, and — if turned on in Report Settings — an IQ-style number/percentile display and a certificate), with "Download PDF" / "Download Image" buttons underneath so the visitor can save it. If the quiz is premium and unpaid, the visitor sees the Preview page (headline, benefits, testimonials, an optional rotating "someone just unlocked..." notice) instead, then — if Email Capture is on — a structured lead form, then the Checkout page with the real Stripe Checkout link.
- **Abandon recovery**: the moment a visitor's email is captured (either up front, or the first time they'd be asked for it), a lead row is saved along with their answers so far. If they never finish, MindPulse → Abandon Emails' cron sends up to N recovery emails after a configurable delay, with a resume link that restores their name, email, in-progress answers, and step — they pick up where they left off, not from question 1.
- **Payments**: Stripe Checkout Session created via direct REST calls (no Stripe SDK). Webhook confirms payment and unlocks the submission. Configure keys under Settings.
- **B2B embeds**: MindPulse → B2B Embed → Create Partner gives an API key and a `<script>` snippet partners paste on their own site; it iframes the quiz and tags resulting submissions with that partner.
- **Reviewing results**: MindPulse → Users lists every submission with score/profile/payment status, with a one-click CSV export; MindPulse → Payments lists transactions.
- **Releases**: pushing a `v*` git tag triggers a GitHub Actions workflow that builds the installable zip correctly (forward-slash paths — no PowerShell `Compress-Archive` pitfall) and attaches it to a GitHub Release.

## What's been verified

Activated on a real (local Docker) WordPress 7.1 + Elementor 4.2 install, not just read for correctness. That process found and fixed two real bugs — a `plugins_loaded`-timing fatal that broke every admin page on activation, and a paywall bypass where the full premium result was readable from the network response even when unpaid (see `PROGRESS.md` for details). After the fixes:

- Activation runs cleanly: all 4 tables created via `dbDelta`, both CPTs registered, no PHP warnings/notices anywhere, deactivate/reactivate cycle clean.
- A quiz can be completed end-to-end through a real browser (lead capture → questions → scored result), verified with Playwright.
- The premium paywall correctly withholds the result until paid, and correctly reveals it after — verified in-browser both ways.
- Stripe webhook signature verification correctly accepts a validly-signed payload and rejects a forged one.
- Lead-capture/resume tokens, B2B partner creation + attribution, and CSV export all verified against a live REST API + database.

## What's still unverified

- **Real Stripe test-mode keys.** Checkout *session creation* against Stripe's live API wasn't exercised — only the "not configured" error path and the webhook-confirmation side were. Run one real test-mode checkout before sending a premium quiz live.
- **Real WP-Cron timing.** The abandon-email cron's logic and SQL were verified by invoking it directly; nobody has confirmed WP-Cron's real 15-minute schedule fires it on a live host with working outbound mail.
- The Elementor widget was verified as registered and rendering, but only the shortcode path was driven through a full browser click-through — worth a quick manual check on the widget too.

## Known limitations (by design, not bugs)

- Brain Games is a stub: one title + one embed URL per game, nothing scored. It's a placeholder for a distinct product surface, not a mini-game engine.
- Only Stripe is wired up as a payment gateway. Adding another means implementing `MP_Payment_Gateway` — the REST/admin layers don't need to change.
- No automated tests yet.
- **The Report page's IQ-style number/percentile and the Preview page's rotating "just unlocked..." notice are styled to look more scientific/live than they are.** The number is a statistical transform of this quiz's own raw score (not a validated psychometric result), and the notice is illustrative example copy the admin types in themselves (not real visitor activity). Built this way at your explicit request — keep the Report Settings → Disclaimer field filled in.
- Report Settings has no per-dimension chart breakdown (a 6-category radar/bar chart, like some reference IQ-test funnels show) — this quiz's scoring produces one aggregate score, not independently measured sub-scores, so a breakdown would have to be fabricated. Left out on purpose; revisit if the scoring engine grows real per-category sub-scores.

## Where the code lives

Repo: https://github.com/yasinzia01-ops/MindPulse (public). Plugin root is the repo root — clone/zip it directly into `wp-content/plugins/mindpulse-quiz`, or upload a zip via Plugins → Add New. If you build the zip yourself on Windows, do not use PowerShell's `Compress-Archive` — it writes backslash paths that WordPress's extractor can mis-handle. Use a zip tool that writes forward-slash paths (see git history for the Node-based workaround used previously).
