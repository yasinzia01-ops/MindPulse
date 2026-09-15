# MindPulse — Progress

Running log for the autonomous loop (`RALPH_LOOP.md`) and for humans picking this project back up. Newest entry on top. Each iteration of the loop should append one entry here before stopping, even if the entry is "no changes needed."

## Status: v1 verified end-to-end on a local WordPress + Elementor install

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

## Not yet done / next up

- [ ] **Real Stripe test-mode keys** — checkout *session creation* against Stripe's live API was not exercised (no test API key available in this pass); only the "no key configured" error path and the webhook confirmation side were verified. Do this before the first real premium quiz goes out.
- [ ] **Real WP-Cron timing** — `MP_Cron::process_abandoned_leads()` was invoked directly and its SQL/logic verified, but nobody has confirmed WP-Cron's real 15-minute schedule actually fires it on a live (non-Docker-sandbox) host with outbound mail working.
- [ ] No automated tests (no PHPUnit scaffold, no JS tests).
- [ ] Elementor widget's `get_quiz_options()` re-queries all quizzes on every editor render — fine at small scale, revisit if quiz count grows.
- [ ] Brain Games is intentionally minimal (see BLUEPRINT.md) — revisit only if the user asks for more than a single embed URL per game.
- [ ] The release workflow (`.github/workflows/release.yml`) has not actually been exercised (no tag pushed yet) — first tag push (`git tag v1.0.0 && git push --tags`) should be treated as a test of it, not an assumption it works.

## Done (this pass)

- [x] **Resume-token flow wired up end to end.** `wp_mp_leads` now has an `answers` column; `/lead-capture` accepts and stores in-progress answers on every step; a new `GET /wp-json/mindpulse/v1/resume?token=&quiz_id=` endpoint (`MP_REST::resume`) looks up an unconverted lead by its resume token; `quiz-runner.js` checks `?mp_resume=&mp_quiz=` on load and restores name/email/answers/step before rendering, instead of restarting from question 1.
- [x] **Stripe webhook hardened.** `MP_Gateway_Stripe::verify_webhook()` now refuses any webhook call outright when no signing secret is configured in Settings, instead of silently accepting unsigned payloads.
- [x] **CSV export added** to MindPulse → Users ("Export CSV" button, nonce-protected `admin-post` handler streaming all submissions).
- [x] **Release workflow added** (`.github/workflows/release.yml`) — builds the zip with `zip` on the Ubuntu runner (forward-slash paths, unlike PowerShell's `Compress-Archive`) and attaches it to a GitHub Release on any `v*` tag push.
- [x] `MP_DB_VERSION` bump + an upgrade check on `plugins_loaded` (`mp_init`) so schema changes like the new `answers` column apply without requiring deactivate/reactivate.

## How to verify after an activation attempt

See "Verification" in the original plan (superseded by this file going forward): activate on staging, build a quiz with bands, complete it via shortcode and via Elementor widget, abandon one attempt and confirm a recovery email fires (shrink the delay for testing), mark a quiz premium and complete a Stripe test-mode checkout, create a partner and complete a quiz through the embed snippet.
