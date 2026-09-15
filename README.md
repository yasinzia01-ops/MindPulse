# MindPulse

A custom WordPress plugin for building IQ, personality, and aptitude quizzes with point-band scoring, result profiles, lead capture, abandon-cart email recovery, payment-gated results, and B2B embeds. Works as a shortcode or an Elementor widget.

## Features

- **Forms** — build quizzes with a custom question/answer builder. Each answer option carries a point value; total score maps to admin-defined score bands, each tied to a result profile (title, description, image, CTA).
- **Users** — list of every completed submission (name, email, quiz, score, result profile, payment status) with bulk delete.
- **Payments** — gate the full result profile behind Stripe Checkout for premium quizzes; transactions list with status.
- **Abandon Emails** — captures a lead's email as soon as they enter it, before they finish the quiz. A WP-Cron job sends a configurable recovery email sequence to anyone who never converts.
- **Brain Games** — lightweight embeddable mini-games, separate from the scored quiz engine.
- **Settings** — sender email/name, Stripe API keys.
- **B2B Embed** — generate a partner API key and a copy-paste `<script>` snippet that iframes a quiz on a partner's site; their submissions are tagged with that partner.
- **Frontend** — `[mindpulse_quiz id="123"]` shortcode and a native "MindPulse Quiz" Elementor widget, both driven by the same JS quiz runner.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- Elementor (optional — only needed for the Elementor widget; the shortcode works without it)

## Installation

1. Download or clone this repository into `wp-content/plugins/mindpulse-quiz`, or zip the folder and upload it via **Plugins → Add New → Upload Plugin**.
2. Activate **MindPulse** from the Plugins screen. Activation creates its own database tables (`wp_mp_submissions`, `wp_mp_leads`, `wp_mp_payments`, `wp_mp_partners`).
3. Go to **MindPulse → Forms → Add New** to build your first quiz.

## Architecture

```
mindpulse-quiz/
  mindpulse-quiz.php               # plugin bootstrap, autoloader, activation/cron setup
  includes/
    class-mp-cpt.php               # mp_quiz / mp_game post types + quiz data (questions, bands, settings)
    class-mp-db.php                # custom table schema (submissions, leads, payments, partners)
    class-mp-scoring.php           # point total -> score band -> result profile
    class-mp-rest.php              # REST API: lead-capture, submit, payment webhook
    class-mp-cron.php              # abandon-email recovery sequence
    class-mp-payments.php          # gateway interface + Stripe Checkout implementation
    class-mp-embed.php             # B2B partner API keys + embed.js endpoint
    class-mp-mailer.php            # wp_mail wrapper with {placeholder} templating
  admin/
    class-mp-admin-menu.php        # registers the MindPulse admin menu + form handlers
    class-mp-users-list-table.php  # WP_List_Table for submissions
    class-mp-payments-list-table.php
    views/                         # Forms, Users, Payments, Abandon Emails, Brain Games, Settings, B2B Embed
  public/
    class-mp-shortcode.php         # [mindpulse_quiz] / [mindpulse_game] + B2B embed bare-page route
    class-mp-elementor-widget.php  # Elementor widget wrapper
    js/quiz-builder.js             # admin quiz builder UI
    js/quiz-runner.js              # frontend quiz runner (lead capture, step-through, scoring, result)
    css/
```

## Scoring model

Each question has multiple answer options; each option carries a point value. On submit, points from the selected options are summed into a total score, which is matched against the quiz's score bands (`min`–`max` ranges) to find the resulting profile. Configure bands under **Forms → [quiz] → Score Bands & Result Profiles**.

## Payments

Premium quizzes require `MindPulse → Settings → Stripe Secret Key`. Set your Stripe webhook to:

```
https://yoursite.com/wp-json/mindpulse/v1/payment/webhook
```

and paste the signing secret into **Settings → Webhook Signing Secret**.

## License

Proprietary — all rights reserved.
