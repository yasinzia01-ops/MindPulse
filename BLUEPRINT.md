# MindPulse — Blueprint

Architecture reference for the plugin. This is the source of truth an autonomous coding loop (see `RALPH_LOOP.md`) should read before making changes, and update if the architecture itself changes.

## Product

A WordPress plugin for building personality/IQ-style quizzes with point-band scoring and result profiles. Delivered as a shortcode and an Elementor widget. Admin surface: Forms, Users, Payments, Abandon Emails, Brain Games, Settings, B2B Embed.

## Scoring model

Two scoring modes, selected per-quiz via `scoring_mode` ('points' | 'correct'):
- **Points** (default/legacy): each answer option carries a point value; the sum of selected options is the total score.
- **Correct/Incorrect**: each option carries an `is_correct` flag instead; the total score is the count of correct answers. Used for IQ/aptitude-style quizzes.

Either way, the total score is matched against admin-defined `[min, max]` score bands (`MP_Scoring::match_band()`), each tied to a result profile (title, description, image, CTA). See `includes/class-mp-scoring.php`.

## Funnel pages (all optional, per-quiz)

Beyond the core question flow, a quiz can enable any combination of structured funnel pages, each with its own admin tab (`admin/views/forms-edit.php`) and JSON block in `_mp_quiz_data`: `intro`, `sections` (question grouping), `processing` (animated scoring screen), `preview` (paywall teaser, with an optional rotating social-proof notice — illustrative example copy the admin writes, not real visitor data), `email_capture` (structured lead form shown post-preview instead of the plain pre-questions one), `checkout` (copy shown right before the Stripe redirect), `report` (full result page, optionally with an IQ-style number/percentile transform of the raw score and a certificate block), and `custom_css`. None of this changes the request flow below — it only adds stages before/after `submit`/`payment/webhook`, all driven client-side by `public/js/quiz-runner.js`. A quiz with none of these enabled behaves exactly like the original flat flow.

When email is captured *after* `/submit` (the deferred email-capture path, `email_capture` stage), the frontend passes `submission_id` back to `/lead-capture` so `MP_REST::lead_capture()` can attach the now-known name/email onto that already-scored (previously anonymous) submission — otherwise it would stay unattributed in MindPulse → Users.

## Data storage

- CPT `mp_quiz` — one post per quiz. Questions/options/points/bands/settings/funnel-pages live as JSON in post meta `_mp_quiz_data` (`MP_CPT::get_quiz_data()` / `save_quiz_data()`). Questions are stored flat with a `section_id`; `sections` is separate `{id,name}` metadata used only for grouping/display.
- CPT `mp_game` — Brain Games entries (title + embed URL in `_mp_game_embed_url`).
- Table `wp_mp_submissions` — completed attempts: quiz_id, lead name/email, answers (JSON), total_score, band_key, profile_title, payment_status, partner_id, lead_id, created_at.
- Table `wp_mp_leads` — partial/abandoned attempts: quiz_id, lead name/email, last_step, answers (JSON, kept in sync on every step so a resume restores mid-quiz progress), partner_id, converted_submission_id, recovery_emails_sent, last_email_sent_at, resume_token.
- Table `wp_mp_payments` — gateway, transaction_id, submission_id, amount, currency, status.
- Table `wp_mp_partners` — B2B clients: name, api_key, allowed_quiz_ids, status.

Schema lives in `includes/class-mp-db.php::create_tables()` (dbDelta). Bumping `MP_DB_VERSION` in the main plugin file and re-running `create_tables()` on `plugins_loaded` is the pattern to use for future schema changes — don't hand-write ALTER statements elsewhere.

## Request flow

1. Frontend renders `.mp-quiz` container (shortcode `class-mp-shortcode.php` or Elementor `class-mp-elementor-widget.php`), both calling `MP_Shortcode::render_quiz()`.
2. `public/js/quiz-runner.js` steps through lead capture → questions → submit.
3. `POST /wp-json/mindpulse/v1/lead-capture` (`MP_REST::lead_capture`) upserts a `wp_mp_leads` row as soon as an email exists, including the answers collected so far — this is what powers abandon recovery.
4. `POST /wp-json/mindpulse/v1/submit` (`MP_REST::submit`) scores via `MP_Scoring::score()`, writes `wp_mp_submissions`, marks the lead converted, and — if the quiz is premium — calls `MP_Payments::create_checkout()`.
5. Stripe webhook `POST /wp-json/mindpulse/v1/payment/webhook` → `MP_Payments::handle_webhook()` marks the submission/payment paid. Refuses to process anything unless `mp_settings_stripe_webhook_secret` is set and the signature verifies.
6. `mp_abandon_email_cron` (every 15 min, `MP_Cron::process_abandoned_leads()`) emails unconverted leads past the configured delay, up to N times, linking to `?mp_resume=TOKEN&mp_quiz=ID`. `GET /wp-json/mindpulse/v1/resume` (`MP_REST::resume`) resolves that token back to the lead's name/email/answers/step; `quiz-runner.js` reads those URL params on load and restores the in-progress state instead of restarting from question 1.
7. B2B: `MP_Embed::embed_script()` served at `?mp_embed_js=1` iframes `?mp_embed_quiz=ID&mp_partner_key=KEY`, handled by `MP_Shortcode::maybe_render_embed_page()` on `template_redirect`; the partner key tags resulting rows with `partner_id`.

## Admin

`admin/class-mp-admin-menu.php` registers the 7 pages and all `admin_post_mp_*` form handlers. Views are plain PHP in `admin/views/`. List views (Users, Payments) use `WP_List_Table` subclasses in `admin/`.

Settings are per-page-scoped in `handle_save_settings()` (`$fields_by_page`) — a hidden `_mp_settings_page` field tells the handler which option group to touch, so submitting one settings form never resets checkboxes that live only on the other form. Any new settings page/form must follow this pattern rather than sharing one flat `$fields` array.

## Conventions

- Class prefix `MP_`, DB/option/hook prefix `mp_`.
- Classes autoload via the `spl_autoload_register` map in `mindpulse-quiz.php` — every new class must be added to that map.
- No Composer/npm build step. No external PHP dependencies (Stripe integration is hand-rolled over `wp_remote_post`, not the Stripe SDK).
- Sanitize on the way in (`sanitize_*`, `absint`), escape on the way out (`esc_html`, `esc_attr`, `esc_url`). Nonces on every state-changing admin form (`admin_post_*` + `check_admin_referer`) and the REST `wp_rest` nonce on the frontend fetches.

## Known gaps / deliberately out of scope for v1

- Brain Games is a stub (list + single embed URL), not a full mini-game engine.
- Only Stripe is implemented; `MP_Payment_Gateway` interface exists so a second gateway can be added without touching `MP_REST`/`MP_Admin_Menu`.
- No automated test suite yet.
