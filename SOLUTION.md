# MindPulse — Solution

What this plugin actually does today, written for a human deciding whether it's ready to use — not a feature wishlist. See `BLUEPRINT.md` for architecture and `PROGRESS.md` for the work log.

## The problem this solves

Build and run personality/IQ-style quizzes on WordPress: score a respondent's answers against admin-defined bands, show them a result profile, capture the lead even if they abandon partway through, optionally charge for the full report, and let B2B partners embed a quiz on their own site while their leads stay attributed to them.

## What works right now

- **Building a quiz**: MindPulse → Forms → Add New. Add questions, give each answer option a point value, define score bands (min/max → title/description/image/CTA), toggle lead-capture-before-questions and premium (paid) results, save.
- **Taking a quiz**: drop `[mindpulse_quiz id="X"]` on any page, or add the "MindPulse Quiz" widget in Elementor and pick the quiz. The visitor steps through questions one at a time; if lead capture is on, they give name/email first.
- **Getting a result**: on the last question, the plugin scores the answers, matches a band, and shows that band's title/description/image/CTA. If the quiz is premium and unpaid, the visitor sees a paywall with a Stripe Checkout link instead of the full description.
- **Abandon recovery**: the moment a visitor's email is captured (either up front, or the first time they'd be asked for it), a lead row is saved. If they never finish, MindPulse → Abandon Emails' cron sends up to N recovery emails after a configurable delay, with a link back to the quiz.
- **Payments**: Stripe Checkout Session created via direct REST calls (no Stripe SDK). Webhook confirms payment and unlocks the submission. Configure keys under Settings.
- **B2B embeds**: MindPulse → B2B Embed → Create Partner gives an API key and a `<script>` snippet partners paste on their own site; it iframes the quiz and tags resulting submissions with that partner.
- **Reviewing results**: MindPulse → Users lists every submission with score/profile/payment status; MindPulse → Payments lists transactions.

## What "done" does not mean here

This has been built and reviewed carefully, but **it has not yet been activated on a real WordPress site.** Nobody has confirmed:

- Activation runs cleanly (table creation, CPT registration, cron scheduling) on an actual WP install.
- The admin screens render without PHP notices under a live WP + Elementor environment.
- A quiz can actually be completed end-to-end through the browser, on both the shortcode and the Elementor widget.
- Stripe checkout and webhook confirmation work against real (test-mode) Stripe keys.
- The abandon-email cron actually fires and sends on WP-Cron's real timing.

Treat this as "code-complete, field-untested" rather than "shipped." The first real task for anyone picking this up — human or the Ralph loop — should be installing it on a staging site and working through the verification checklist in `PROGRESS.md`.

## Known limitations (by design, not bugs)

- Brain Games is a stub: one title + one embed URL per game, nothing scored. It's a placeholder for a distinct product surface, not a mini-game engine.
- Only Stripe is wired up as a payment gateway. Adding another means implementing `MP_Payment_Gateway` — the REST/admin layers don't need to change.
- No CSV export, no automated tests, no CI/release pipeline yet.

## Where the code lives

Repo: https://github.com/yasinzia01-ops/MindPulse (public). Plugin root is the repo root — clone/zip it directly into `wp-content/plugins/mindpulse-quiz`, or upload a zip via Plugins → Add New. If you build the zip yourself on Windows, do not use PowerShell's `Compress-Archive` — it writes backslash paths that WordPress's extractor can mis-handle. Use a zip tool that writes forward-slash paths (see git history for the Node-based workaround used previously).
