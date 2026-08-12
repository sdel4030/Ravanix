=== Ravanix Lite – Smart Psychological Assessment ===
Contributors: psykeyir
Tags: psychological test, questionnaire, quiz builder, scoring, rtl
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build, run, and score psychological questionnaires in WordPress with charted profiles and automatic result interpretation.

== Description ==

Ravanix Lite is a free, dynamic engine for building, managing, and running
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
* Definable interpretation ranges for each dimension, with a custom description
* Display a test via shortcode on any page or post
* Optional display of questionnaires as a custom post type with an SEO-friendly slug, tags, categories, and a "related questionnaires" display
* The WordPress classic editor for the test and dimension descriptions
* Bulk paste-import of questions and answer options
* Question pagination for long questionnaires, with a progress bar and browser autosave
* Configurable participant info fields (name, education, mobile, email, age, gender)
* Bar chart for results with Chart.js (loaded locally, no external CDN required)
* An admin panel to review every participant's results
* Anti-spam protection (honeypot field, minimum completion time, rate limiting)
* Supports right-to-left (RTL) questionnaires (e.g. Persian/Arabic) alongside left-to-right ones, and is fully translation-ready (i18n-ready)

= Ravanix Pro =

A separate, paid companion plugin, **Ravanix Pro**, is available from
[psykey.ir](https://psykey.ir) and adds: standard T/Z scores and percentile
rank based on norm tables, composite (higher-order) factor scores, validity
scales, forced-choice/ipsative questions and overlapping multi-scale scoring,
PDF and CSV/Excel export, full JSON import/export of tests, execution
limits and access codes, WooCommerce integration for selling access to a
test, and a "My Results" dashboard with a timeline chart for repeated
attempts. Ravanix Pro requires this plugin (Ravanix Lite) to be installed
and active; it is not hosted on WordPress.org. An "Upgrade to Pro" page
with a full feature comparison is available from the plugin's own menu.

= Important note about standardized instruments (NEO, Millon, Beck, MMPI, etc.) =

The items, official scoring keys, and norms of standardized, copyrighted
instruments such as the NEO-PI-R, MMPI, Millon Clinical Multiaxial Inventory,
and the original Beck inventories belong to their publishers and are not
included in this plugin. As a professional with legitimate, licensed access
to these instruments, you can enter their items, dimensions, scoring, and
interpretive keys yourself through the admin panel; the plugin only provides
the technical infrastructure.

= External services =

The plugin's Settings page optionally displays two RSS feeds fetched from
psykey.ir (the developer's own site) using WordPress's built-in fetch_feed()
function: recent blog posts about Ravanix, and ready-made questionnaires
available from the developer's shop. No personal or site data is sent to
psykey.ir as part of this request beyond a standard, anonymous HTTP request
to a public RSS URL. See psykey.ir's own terms for that site:
https://psykey.ir

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

Yes. Each test has its own display-direction setting (left-to-right or
right-to-left), independent of the admin interface language, so you can mix
RTL and LTR questionnaires on the same site. The admin interface itself is
in English and is fully translation-ready (a .pot file is included, and
community translations can be contributed via translate.wordpress.org once
the plugin is published).

= What's the difference between Ravanix Lite and Ravanix Pro? =

Ravanix Lite (this plugin) is fully functional on its own for building and
running questionnaires with basic scoring. Ravanix Pro is a separate,
paid add-on for professional/research use (norm-based scoring, composite
factors, validity scales, PDF/CSV export, data portability, access control,
and selling access to tests). See the "Upgrade to Pro" page in the plugin's
menu for a full comparison.

== Screenshots ==

1. Test builder panel and dimension setup
2. Test form on the front end
3. Psychological profile display with a bar chart

== Changelog ==

= 1.0.2 =
* New: an optional "Show results ranked from highest to lowest" setting per test, for questionnaires where relative ranking between dimensions matters more than each one's absolute level (e.g. strengths, interests, dominant traits)
* New: dimensions without an interpretation range now show their own general description instead of a blank "not defined" message
* New: test display direction (RTL/LTR) is now detected automatically from the site's language, removing a manual per-test setting
* New: the "All Tests" admin screen now follows WordPress's native list-table conventions (status filter, hover row actions, mobile-responsive layout)

= 1.0.1 =
* Full English translation of the admin interface and all plugin strings; the source .pot file was regenerated accordingly
* Fixed a bug where a participant's gender was matched against a translated display label instead of a stable internal value, which could silently break gender-based norm matching on non-default languages
* Various WordPress coding-standards and documentation clean-ups

= 1.0.0 =
* Initial public release of Ravanix Lite

== Upgrade Notice ==

= 1.0.2 =
Adds an optional ranked-results display mode and a description fallback for dimensions without interpretation ranges.

= 1.0.1 =
Admin interface fully translated to English; fixes a gender-matching edge case relevant to sites using Ravanix Pro.

= 1.0.0 =
Initial public release.

== Technical Note ==

The Chart.js library is bundled locally with the plugin (no dependency on
an external CDN), suited to environments with limited internet access. The
source translation file is at languages/ravanix-lite.pot, and you can use
tools such as Poedit or Loco Translate to generate .po/.mo files for your
language of choice from it.
