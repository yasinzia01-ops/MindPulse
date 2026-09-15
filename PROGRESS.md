# MindPulse — Progress

Running log for the autonomous loop (`RALPH_LOOP.md`) and for humans picking this project back up. Newest entry on top. Each iteration of the loop should append one entry here before stopping, even if the entry is "no changes needed."

## Status: v1 scaffold complete, untested on a live site

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

## Not yet done / next up

- [ ] **Never activated on a real WordPress install.** No confirmation that `dbDelta` produces the tables cleanly, that activation doesn't fatal, or that the admin pages render without notices/warnings under a real WP + Elementor environment.
- [ ] No automated tests (no PHPUnit scaffold, no JS tests).
- [ ] Elementor widget's `get_quiz_options()` re-queries all quizzes on every editor render — fine at small scale, revisit if quiz count grows.
- [ ] Resume-token flow (`?mp_resume=TOKEN&mp_quiz=ID` built in `MP_Cron::send_recovery_email()`) is generated but the frontend JS never reads/consumes it — clicking a recovery email link currently just opens the quiz from question 1, not mid-quiz. Either wire it up or document it as "restarts the quiz."
- [ ] No CSV export on the Users list (mentioned as a nice-to-have during planning, not built).
- [ ] No GitHub Actions workflow to build/attach a release `.zip` on tag push.
- [ ] Brain Games is intentionally minimal (see BLUEPRINT.md) — revisit only if the user asks for more than a single embed URL per game.
- [ ] `MP_Gateway_Stripe::signature_is_valid()` webhook check only runs if a webhook secret is configured; if the user skips that Settings field, webhooks are accepted unverified. Consider refusing webhook calls outright when no secret is set, instead of silently trusting them.

## How to verify after an activation attempt

See "Verification" in the original plan (superseded by this file going forward): activate on staging, build a quiz with bands, complete it via shortcode and via Elementor widget, abandon one attempt and confirm a recovery email fires (shrink the delay for testing), mark a quiz premium and complete a Stripe test-mode checkout, create a partner and complete a quiz through the embed snippet.
