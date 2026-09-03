=== Ravanix – Smart Psychological Assessment ===
Contributors: ravanix
Tags: psychological test, questionnaire, assessment, psychometric, scoring
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build, run, and score psychological questionnaires in WordPress with charted profiles and automatic result interpretation.

== Description ==

Ravanix is a free, dynamic engine for building, managing, and running
psychological questionnaires in WordPress. Site admins can design any kind
of test (personality, clinical, screening, satisfaction surveys, etc.) with
custom dimensions (subscales), multiple question types (5- and 7-point
Likert, yes/no, custom multiple-choice), reverse scoring, and custom
interpretation ranges. Once a user completes a test, they see their
psychological profile along with a bar chart and an interpretive
description for each dimension, and the admin can view every participant's
results in the admin panel.

= Key features (free, in this plugin) =

* A fully dynamic test builder: any number of tests, each with custom dimensions and questions
* Question types: 5- and 7-point Likert, yes/no, custom multiple-choice with any numeric value
* Reverse scoring and an importance weight for each question
* Definable interpretation ranges per dimension, with a custom description
* Branching / skip logic: show a question only if an earlier one was answered a specific way
* Informed consent: a site-wide or per-test notice the participant must expand and agree to before starting
* Save & Resume: answers always autosave in-browser; an optional button also lets a logged-in participant resume on another device
* Display a test via shortcode, or as its own custom-post-type page with an SEO-friendly slug, tags, and categories
* The WordPress classic editor for test, dimension, and consent-notice text
* Bulk paste-import of questions and answer options; pagination with a progress bar for long questionnaires
* Configurable participant fields (name, education, mobile, email, age, gender)
* A Material Design 3-inspired look on the front-end, with an adjustable brand color and automatic light/dark appearance
* Bar chart for results with Chart.js (bundled locally, no external CDN)
* An admin panel to review every participant's results
* Anti-spam protection (honeypot field, minimum completion time, rate limiting)
* Displays correctly in RTL (e.g. Persian/Arabic) or LTR, automatically following the site's language; fully translation-ready (i18n)
* WordPress Erase Personal Data integration, plus an opt-in "delete all data on uninstall" setting

= Ravanix Pro =

A separate, paid companion plugin, **Ravanix Pro**, is available from
[psykey.ir](https://psykey.ir) and adds: T/Z scores and percentile rank from
norm tables, composite (higher-order) factor scores, validity scales,
forced-choice/ipsative questions, PDF and CSV/Excel export, full JSON
import/export, execution limits and access codes, WooCommerce integration,
and a "My Results" dashboard with a timeline chart. Ravanix Pro requires
this plugin to be installed and active; it is not hosted on WordPress.org.
See the "Upgrade to Pro" page in the plugin's own menu for a full comparison.

= Important note about standardized instruments (NEO, Millon, Beck, MMPI, etc.) =

The items, official scoring keys, and norms of standardized, copyrighted
instruments (NEO-PI-R, MMPI, Millon CMI, the original Beck inventories,
etc.) belong to their publishers and are not included in this plugin. With
legitimate, licensed access to such an instrument, you can enter its items,
scoring, and interpretive keys yourself; the plugin only provides the
technical infrastructure.

== Installation ==

1. Upload the plugin folder to wp-content/plugins, or from the WordPress
   dashboard, go to Plugins > Add New > Upload Plugin and upload the zip file.
2. Activate the plugin.
3. A short sample questionnaire is created automatically so you can see how
   it works; create your own test from the "Ravanix" menu.

== Frequently Asked Questions ==

= Does the plugin include items from standardized tests like the NEO or the Beck inventory? =

No. These instruments are copyrighted. The plugin only provides the
technical infrastructure (scoring engine, display, and interpretation), and
you enter the items yourself from your own official, licensed source.

= Can a test be displayed on its own dedicated page with its own URL? =

Yes. In the plugin settings you can enable "Display as a custom post type"
so that, in addition to the shortcode, each test also gets its own URL.

= Can I build a right-to-left (e.g. Persian or Arabic) questionnaire? =

Yes. A test's display direction (RTL or LTR) automatically follows the
site's active language (WordPress's own is_rtl()) — for example, a test
displays RTL on a site running in Persian or Arabic, and LTR on a site
running in English. This is site-wide, not a per-test choice. The plugin's
own interface strings are in English and fully translation-ready (a .pot
file is included; community translations can be contributed via
translate.wordpress.org once the plugin is published).

= Can I require participants to agree to a consent notice before starting? =

Yes. Set a default notice in Ravanix Settings, or a custom one per test (or
turn it off) in that test's settings. Shown collapsed with an "I agree"
checkbox required before "Start Test", verified server-side too.

= Can a long questionnaire be saved and finished later? =

Yes. Answers always autosave in the browser. For long tests, you can also
turn on a visible "Save my progress" button per test, letting a logged-in
participant resume on a different device.

= Can a question be shown only if an earlier question was answered a certain way? =

Yes, via each question's "show this question only if" setting: pick an
earlier question and the required answer value. A hidden question isn't
required and is excluded from its dimension's score, like any unanswered one.

= What happens to my data if I delete the plugin? =

By default, nothing is deleted; your questionnaires, results, and settings
stay in the database and come back if you reinstall Ravanix later. To
permanently delete everything instead, enable "Delete data on uninstall" in
the Danger zone section of Ravanix Settings before removing the plugin —
this cannot be undone, and never deletes Media Library images.

= Can a logged-in participant ask for their data to be removed? =

Yes, via WordPress's own Tools -> Erase Personal Data for any request tied
to a registered user's email. Ravanix removes that user's identifying
details (name, contact info, individual answers) while keeping the
resulting dimension scores as anonymous data points, so site-wide
statistics aren't affected. This only covers logged-in users, since a guest
submission has no account/email for WordPress's privacy tools to look up.

= What's the difference between Ravanix and Ravanix Pro? =

Ravanix (this plugin) is fully functional on its own. Ravanix Pro is a
separate, paid add-on for professional/research use (norm-based scoring,
composite factors, validity scales, PDF/CSV export, data portability,
access control, selling access to tests). See "Upgrade to Pro" in the
plugin's menu for a full comparison.

== Screenshots ==

1. Test builder panel and dimension setup
2. Test form on the front end
3. Psychological profile display with a bar chart

== Privacy ==

For a guest participant: a `ravanix_guest_token` cookie (random ID, 3-year
lifetime) and the submitting IP are stored with each result, solely for
anti-spam rate-limiting and letting that guest resume an in-progress test.
Nothing is sent to any external server. A logged-in participant using
"Save my progress" (if enabled for that test) also has in-progress answers
stored server-side until the test is completed or restarted.

Participant info fields (name, email, etc.) are only collected if the
admin turns them on. See the FAQ for uninstall data deletion and
WordPress's Erase Personal Data tool for logged-in users.

== Changelog ==

Full history (every release before 1.2.0) is in changelog.txt.

= 1.2.1 =
* Fixed: an admin-chosen brand color that was too light could fail WCAG AA text contrast; it's now automatically darkened only as much as actually needed, verified by a real contrast calculation (your saved setting is never changed)
* Fixed: dark mode's default button text also failed contrast against its own background (~2.6:1, well under the 4.5:1 minimum); now chosen by measuring actual contrast

= 1.2.0 =
* New: the front-end pages (and Ravanix Pro's My Results dashboard) now use a Material Design 3-inspired look -- color roles, shape scale, type scale, and CSS-only state layers on buttons/answers
* New: a "Brand color" setting (Ravanix Settings -> Branding) re-tints the entire front-end palette from one chosen color, computed live in the browser (no extra library)
* New: automatic light/dark appearance, following the visitor's OS/browser preference

== Upgrade Notice ==

= 1.2.1 =
Recommended if you set a custom Brand color: fixes a real WCAG contrast failure that could make button text hard to read.

= 1.2.0 =
Visual refresh only (Material Design 3-inspired front-end + adjustable brand color); no database changes, safe to update.

== Technical Note ==

Chart.js is bundled locally (no external CDN). The source translation
file is at languages/ravanix.pot; use Poedit or Loco Translate to build
.po/.mo files for your language from it.
