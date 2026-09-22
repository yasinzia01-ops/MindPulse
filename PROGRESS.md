# MindPulse — Progress

Running log for the autonomous loop (`RALPH_LOOP.md`) and for humans picking this project back up. Newest entry on top. Each iteration of the loop should append one entry here before stopping, even if the entry is "no changes needed."

## Status: v1 verified end-to-end on a local WordPress + Elementor install; v2 full-funnel builder (intro/sections/processing/preview/email-capture/checkout/report/custom-CSS + correct-answer scoring) added and verified on the same harness

## Done (this pass — full funnel builder)

- [x] **Extended `_mp_quiz_data` schema** (`includes/class-mp-cpt.php`) with `scoring_mode`, `sections` (question grouping metadata), and structured `intro`/`processing`/`preview`/`email_capture`/`checkout`/`report`/`custom_css` blocks — all optional with safe defaults, so every existing quiz keeps working unchanged.
- [x] **Correct/Incorrect scoring mode** (`includes/class-mp-scoring.php`) alongside the original points mode — each option now carries both a `points` value and an `is_correct` flag; the quiz picks which one matters via `scoring_mode`.
- [x] **Tabbed admin editor** (`admin/views/forms-edit.php`, `public/js/quiz-builder.js`, `public/css/admin.css`) — 9 tabs (Form Details, Test Introduction, Test Sections, Processing Page, Preview Page, Email Capture, Checkout Page, Report Settings, Custom CSS), sections with nested question/option cards, and repeatable-list widgets (tips, benefits, testimonials, social-proof entries, classification labels) — all structured fields, no raw-HTML blobs. Still saves as one JSON blob via the existing `mp_save_quiz` handler — no backend changes needed there.
- [x] **New frontend stage machine** (`public/js/quiz-runner.js`): Intro → (lead capture) → Questions (grouped by section) → Processing (animated) → submit →, if premium and locked, Preview (with an optional rotating social-proof notice) → Email Capture (deferred, structured) → Checkout, else straight to Report (band + optional IQ-style number/percentile + certificate + disclaimer). Every stage is opt-in per-quiz; a quiz with none of them enabled runs exactly like the original flat flow (regression-tested).
- [x] **Fixed a real attribution gap found during testing**: when email is captured *after* the quiz already scored anonymously (the new deferred Email Capture stage), the submission row stayed unlinked to the lead. `MP_REST::lead_capture()` now accepts an optional `submission_id` and attaches the now-known name/email back onto that submission (only ever filling a blank `lead_email`, never overwriting an already-attributed one).
- [x] **Custom CSS** injected per-quiz via `MP_Shortcode::render_quiz()` (`public/class-mp-shortcode.php`), scoped by a `.mp-quiz-custom-{quiz_id}` class on the container for the admin to target themselves.
- [x] Verified live (same Docker WP+Elementor+Playwright harness as the v1 pass): built a full-featured premium quiz through the new 9-tab editor (correct-answer scoring, 2 sections, intro tips, processing animation, preview with rotating social proof + testimonials, deferred email capture, checkout, IQ-style report + certificate, custom CSS), saved and confirmed the JSON round-trips exactly, then clicked through the entire visitor funnel in a real browser end to end with zero PHP warnings/notices and zero JS console errors — including the return-from-Stripe path (marked paid via DB, reloaded via `?mp_submission=`, confirmed the report unlocks with the correct name on the certificate). Also re-ran a plain legacy quiz (no new features enabled) to confirm the regression path is unaffected.
- [x] Two bugs found and fixed *during* this build (not carried over from before): a stray early `return` in `quiz-builder.js`'s `addSection()` that silently dropped every section from the DOM, and the pre-existing "ask for email right after questions" fallback in `quiz-runner.js` firing even when the new deferred Email Capture stage was meant to handle it.

## Done

- [x] Plugin skeleton, autoloader, activation/deactivation, custom cron interval (`mindpulse-quiz.php`)
- [x] Custom tables: submissions, leads, payments, partners (`includes/class-mp-db.php`)
- [x] `mp_quiz` / `mp_game` post types + JSON quiz-data accessor (`includes/class-mp-cpt.php`)
- [x] Point-band scoring engine (`includes/class-mp-scoring.php`)
- [x] REST API: lead-capture, submit, payment webhook (`includes/class-mp-rest.php`)
- [x] Abandon-email cron + mailer (`includes/class-mp-cron.php`, `includes/class-mp-mailer.php`)
- [x] Stripe Checkout gateway behind `MP_Payment_Gateway` interface (`includes/class-mp-payments.php`)
- [x] B2B partner keys + embed.js + bare embed page (`includes/class-mp-embed.php`, `public/class-mp-shortcode.php`)
- [x] Admin menu + all 7 pages (Forms, Users, Payments, Abandon Emails, Brain Games, Settings, B2B Embed) with working form handlers (`admin/`)
- [x] Quiz builder UI (questions/options/points, score bands) — vanilla JS, no build step (`public/js/quiz-builder.js`)
- [x] Shortcode `[mindpulse_quiz]` + `[mindpulse_game]`, Elementor widget, shared frontend quiz runner (`public/`)
- [x] Fixed: per-page settings scoping so the Abandon Emails form can't silently zero out Stripe/from-name settings and vice versa
- [x] Fixed: zip packaging — `Compress-Archive` writes backslash paths that break WP's extractor; replaced with a forward-slash-correct zip writer
- [x] Pushed to GitHub: https://github.com/yasinzia01-ops/MindPulse (public)

## Done (verification pass — activated on a real WP install)

Spun up an isolated WordPress 7.1 + MySQL 8 stack in Docker (`docker-compose.test.yml`, gitignored, not part of the plugin), installed Elementor 4.2, and activated MindPulse against it. Found and fixed two real bugs in the process:

- [x] **Fixed activation-time fatal.** `mp_init()` called `MP_CPT::register()` directly on `plugins_loaded`, but `register_post_type()` needs the `$wp_rewrite` global, which WordPress only creates *after* `plugins_loaded` (right before `init`). Every admin page load fataled with "Call to a member function add_rewrite_tag() on null". Fixed by hooking CPT registration to `init` instead (`mindpulse-quiz.php`).
- [x] **Fixed a real paywall bypass.** `POST /submit` returned the full band (`description`/`image`/`cta`) in the JSON response even when the quiz was premium and unpaid — the frontend only *hid* it visually, so reading the network response (or calling the REST API directly) got the paid content for free. Added `MP_REST::lock_band()` to strip everything but `title` from the response whenever premium content isn't unlocked yet.
- [x] **Fixed the post-payment dead end.** Stripe's success/cancel URLs redirect back to the quiz page with `?mp_submission=ID&mp_paid=`, but `quiz-runner.js` never read those params — a visitor who actually paid landed back on question 1 with no way to see their unlocked result. Added `GET /wp-json/mindpulse/v1/submission/{id}` (`MP_REST::get_submission`) which re-scores the stored answers and returns the unlocked band once `payment_status` is `paid`, and wired `quiz-runner.js` to check for `mp_submission` on load and render that result instead of restarting.
- [x] Verified clean: `dbDelta` creates all 4 tables, both CPTs register, all 7 admin pages render with zero PHP warnings/notices, activate/deactivate/reactivate cycle is clean.
- [x] Verified in a real headless browser (Playwright): full free-quiz flow (lead capture → 2 questions → scored result) and the premium locked → paid → unlocked flow, both with zero JS console errors.
- [x] Verified via curl/WP-CLI: lead-capture + resume token round-trip, B2B partner creation + partner-tagged submission, CSV export, Stripe webhook signature verification (valid signature accepted, forged signature rejected, unconfigured secret refused), abandon-cron query logic (correctly selects an aged unconverted lead; only skips incrementing `recovery_emails_sent` because the sandbox has no MTA — not a plugin bug).

## Done (this pass — result download)

- [x] **"Download PDF" / "Download Image" buttons on the report stage** (`renderReport()` in `public/js/quiz-runner.js`). Renders the result panel to a canvas with html2canvas, then either saves it straight as a PNG or embeds it (JPEG-compressed, ~70KB instead of several MB for an uncompressed PNG embed) into an A4-proportioned PDF via jsPDF — both entirely client-side. Both libraries load as CDN-hosted script dependencies of `mp-quiz-runner` (`mindpulse-quiz.php`), so the B2B embed page picks them up automatically too (`wp_print_scripts()` resolves dependencies).
- [x] Only appears on the unlocked/free report — never on the locked preview, since there's nothing real to download yet.
- [x] Verified live: both downloads produce valid, correctly-sized files (`file` magic-byte check on the PDF, size sanity-check on the PNG) with zero PHP/JS errors.
- [ ] **Known limitation**: if a band image or logo is hosted somewhere without CORS headers, html2canvas can fail to capture it (or throw) — that's a limitation of the image's hosting, not fixable from this plugin. The download buttons show an alert and re-enable themselves if capture fails, rather than hanging.

## Done (this pass — asset cache-busting fix)

- [x] **Fixed: `MP_VERSION` never changed across any release** (`mindpulse-quiz.php` had it hardcoded to `'1.0.0'` since the very first commit). Every enqueued script/style URL (`quiz-runner.js?ver=...`, `frontend.css?ver=...`) therefore never changed either, so any caching layer between the origin and a visitor's browser (server-side page/object cache, a CDN, even the browser's own HTTP cache under a `Cache-Control: max-age` header) that had already cached that exact URL kept serving the pre-update file indefinitely — regardless of how many times the plugin itself was updated. This is what caused a real "the download buttons aren't showing" report on a live site running v1.2.0's code correctly on disk (confirmed byte-for-byte via diff) — purging caches is a workaround, but the actual fix is bumping `MP_VERSION` on every release so the URL itself changes and forces a fresh fetch. Bumped to `1.2.1` and verified locally that the served URL changes accordingly.
- [ ] **Process reminder**: bump `MP_VERSION` (and the `Version:` header) on every future release, not just when there's a cache complaint.

## Not yet done / next up

- [ ] **Real Stripe test-mode keys** — checkout *session creation* against Stripe's live API was not exercised (no test API key available in this pass); only the "no key configured" error path and the webhook confirmation side were verified. Do this before the first real premium quiz goes out.
- [ ] **Real WP-Cron timing** — `MP_Cron::process_abandoned_leads()` was invoked directly and its SQL/logic verified, but nobody has confirmed WP-Cron's real 15-minute schedule actually fires it on a live (non-Docker-sandbox) host with outbound mail working.
- [ ] No automated tests (no PHPUnit scaffold, no JS tests).
- [ ] Elementor widget's `get_quiz_options()` re-queries all quizzes on every editor render — fine at small scale, revisit if quiz count grows.
- [ ] Brain Games is intentionally minimal (see BLUEPRINT.md) — revisit only if the user asks for more than a single embed URL per game.
- [x] The release workflow was exercised for real: `v1.0.0` initially failed (403 — the workflow never declared `contents: write`, so the default `GITHUB_TOKEN` couldn't create the Release under this repo's token defaults); fixed and re-verified via `v1.0.1`, whose zip was downloaded and its contents checked (correct root folder, forward-slash paths). See https://github.com/yasinzia01-ops/MindPulse/releases/tag/v1.0.1.
- [ ] **Report page's IQ-style number/percentile and the rotating social-proof notice are deliberately styled to look more scientific/live than they are** (a statistical transform of this quiz's own raw score, and illustrative example copy the admin writes — not real visitor data or a validated psychometric result). Built at the user's explicit request after flagging the concern; the same honest disclaimer text field the reference material used is included and should stay filled in.
- [ ] Report Settings intentionally has no per-dimension chart breakdown (the sample's 6 independently-scored cognitive-dimension bars) — this quiz's scoring produces one aggregate score/band, not independently measured sub-scores, and fabricating a breakdown not backed by real sub-scores would go a step further than what was asked for. Revisit only if the scoring engine grows real per-category sub-scores.

## Done (this pass)

- [x] **Resume-token flow wired up end to end.** `wp_mp_leads` now has an `answers` column; `/lead-capture` accepts and stores in-progress answers on every step; a new `GET /wp-json/mindpulse/v1/resume?token=&quiz_id=` endpoint (`MP_REST::resume`) looks up an unconverted lead by its resume token; `quiz-runner.js` checks `?mp_resume=&mp_quiz=` on load and restores name/email/answers/step before rendering, instead of restarting from question 1.
- [x] **Stripe webhook hardened.** `MP_Gateway_Stripe::verify_webhook()` now refuses any webhook call outright when no signing secret is configured in Settings, instead of silently accepting unsigned payloads.
- [x] **CSV export added** to MindPulse → Users ("Export CSV" button, nonce-protected `admin-post` handler streaming all submissions).
- [x] **Release workflow added** (`.github/workflows/release.yml`) — builds the zip with `zip` on the Ubuntu runner (forward-slash paths, unlike PowerShell's `Compress-Archive`) and attaches it to a GitHub Release on any `v*` tag push.
- [x] `MP_DB_VERSION` bump + an upgrade check on `plugins_loaded` (`mp_init`) so schema changes like the new `answers` column apply without requiring deactivate/reactivate.

## How to verify after an activation attempt

See "Verification" in the original plan (superseded by this file going forward): activate on staging, build a quiz with bands, complete it via shortcode and via Elementor widget, abandon one attempt and confirm a recovery email fires (shrink the delay for testing), mark a quiz premium and complete a Stripe test-mode checkout, create a partner and complete a quiz through the embed snippet.
