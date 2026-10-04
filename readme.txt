=== Ravanix – Smart Psychological Assessment ===
Contributors: ravanix
Tags: psychological test, questionnaire, assessment, psychometric, scoring
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build, run, and score psychological questionnaires in WordPress with charted profiles and automatic result interpretation.

== Description ==

Ravanix is a free, dynamic engine for building, managing, and running
psychological questionnaires in WordPress. Site admins can design any kind
of test (personality, clinical, screening, satisfaction surveys, etc.) with
custom dimensions (subscales), multiple question types (5- and 7-point
Likert, yes/no, custom multiple-choice), reverse scoring, custom
interpretation ranges, and AND/OR conditional (branching/skip) logic. Once a
user completes a test, they see their psychological profile along with a
bar chart and an interpretive description for each dimension, and the admin
can view every participant's results in the admin panel. Every result stays
traceable to the exact version of the questionnaire's definition it was
scored against, even after the test is later edited, and to precisely which
consent wording (if any) the participant agreed to.

= Key features (free, in this plugin) =

* A fully dynamic test builder: any number of tests, each with custom dimensions and questions
* Question types: 5- and 7-point Likert, yes/no, custom multiple-choice with any numeric value
* Reverse scoring and an importance weight for each question
* Definable interpretation ranges per dimension, with a custom description
* Conditional Logic: show a question only when one or more earlier answers match, combined with AND or OR and six comparison operators (equals, is not, greater/less than, greater/less-or-equal). Question randomization stays dependency-aware, so it never places a question before another one its condition depends on
* Test Versioning: an immutable snapshot of a test's scoring-relevant definition is kept every time it meaningfully changes, so every result stays traceable to exactly which version of the questionnaire scored it -- the result page says so explicitly if the test has since been edited
* Informed consent: a site-wide or per-test notice the participant must expand and agree to before starting, with each result recording exactly which wording was shown and when (consent provenance)
* Save & Resume: answers always autosave in-browser; an optional button also lets a logged-in participant resume on another device
* Display a test via shortcode, or as its own custom-post-type page with an SEO-friendly slug, tags, and categories
* The WordPress classic editor for test, dimension, and consent-notice text
* Bulk paste-import of questions and answer options; pagination with a progress bar for long questionnaires
* Configurable participant fields (name, education, mobile, email, age, gender)
* A Material Design 3-inspired look on the front-end, with an adjustable brand color and automatic light/dark appearance
* Bar chart for results with Chart.js (bundled locally, no external CDN)
* An admin panel to review every participant's results, including their total completion time
* Anti-spam protection (honeypot field, minimum completion time, rate limiting)
* Displays correctly in RTL (e.g. Persian/Arabic) or LTR, automatically following the site's language; fully translation-ready (i18n)
* WordPress Export and Erase Personal Data integration (Tools menu), plus an opt-in "delete all data on uninstall" setting

= Ravanix Pro =

A separate, paid companion plugin, **Ravanix Pro**, is available from
[psykey.ir](https://psykey.ir) and adds: T/Z scores and percentile rank from
norm tables (with full provenance metadata -- source, population, country,
sample size, collection year, methodology), composite (higher-order) factor
scores, validity scales, response-quality analysis (unusually fast
completion, straight-lining), forced-choice/ipsative questions, PDF and
CSV/Excel export, full JSON import/export, execution limits and access
codes, WooCommerce integration, and a "My Results" dashboard with a
timeline chart that can switch between raw score, percentage, Z-score,
T-score, and percentile. Ravanix Pro requires this plugin to be installed
and active; it is not hosted on WordPress.org. See the "Upgrade to Pro"
page in the plugin's own menu for a full comparison.

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

Yes, via each question's Conditional Logic setting: add one or more
conditions ("this earlier question" / "equals, is not, greater than, less
than, greater-or-equal, less-or-equal" / "this value"), and choose whether
all of them must be true (AND) or any one of them (OR). A hidden question
isn't required and is excluded from its dimension's score, like any
unanswered one. If question randomization is also on for that test, a
question is still never placed before another one its condition(s) depend
on -- randomization only shuffles what's left free to move.

= If I edit a published questionnaire, do old results change meaning? =

No. Ravanix keeps an immutable snapshot of a test's scoring-relevant
definition (questions, answer options, weights, reverse keys,
interpretations, and -- with Ravanix Pro -- norms, composites, and branching)
every time it meaningfully changes, and stamps each result with exactly
which version scored it. Editing a live test never rewrites what an old
result means; the result page says so explicitly if the questionnaire has
since been edited, rather than silently implying an old and a new attempt
are directly comparable.

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

= Can a logged-in participant get a copy of their own data? =

Yes, via WordPress's own Tools -> Export Personal Data, for the same kind
of request (tied to a registered user's email) as the erasure tool above.
The export includes which test, when, their raw per-item answers (with the
exact question wording they were shown), the resulting dimension scores,
their consent record, completion time, and any validity/response-quality
flag. As with erasure, this only covers logged-in users.

= What's the difference between Ravanix and Ravanix Pro? =

Ravanix (this plugin) is fully functional on its own. Ravanix Pro is a
separate, paid add-on for professional/research use (norm-based scoring
with full provenance metadata, composite factors, validity scales,
response-quality analysis, PDF/CSV export, data portability, access
control, selling access to tests). See "Upgrade to Pro" in the plugin's
menu for a full comparison.

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

Each completed result also stores: total completion time; if a consent
notice was required, a short content hash of the exact wording shown and
when it was agreed to (not the full consent text itself, and not tied to
any wording change made afterward); and, if Ravanix Pro's response-quality
analysis is active, a plain-language note rather than raw signal data.

Participant info fields (name, email, etc.) are only collected if the
admin turns them on. See the FAQ above for uninstall data deletion, and for
WordPress's Erase/Export Personal Data tools for logged-in users.

== Changelog ==

Full history (every release before 1.3.0) is in changelog.txt.

= 2.1.0 =
* New: validity scales (Ravanix Pro) now support multiple rules per scale, combined with AND or OR, and five operators (>=, <=, equals, between, absolute value) instead of a single fixed ">= threshold" -- existing single-threshold scales keep working exactly as before, and quietly migrate to the new format the next time they're saved
* Fixed: composite/dimension norm provenance metadata (source, population, country, sample size, etc. -- added in 2.0.0) was never included in the JSON test import/export format, so it was silently lost on export/re-import; now carried through like every other norm field

= 2.0.1 =
* New: Personal Data Exporter (Tools -> Export Personal Data), completing the pair with the existing Personal Data Eraser -- a registered user's own questionnaire data (which test, when, raw per-item answers with the exact question wording they were shown, dimension scores, consent record, completion time, and any validity/response-quality flags) can now be included in a WordPress data export request. Like the Eraser, this can only act on a request tied to a registered user's email address; a guest submission has no account to look the request up by
* Security/quality audit: reviewed every SQL query, output-escaping site, and new database table introduced this development cycle for injection, XSS, and object-ownership issues -- no new findings

= 2.0.0 =
* Fixed: the participant results list (admin) rendered unreadably on narrow mobile screens on RTL sites -- WordPress core's own default responsive list-table behavior (a floated label next to each cell's value) can position the two on top of each other on an RTL admin screen instead of side by side. This table now has its own explicit, direction-safe mobile layout (a bordered "card" per row, each field's label and value in their own flex row) instead of relying on that core default
* Fixed: the dimension/composite score tables on the individual result page overflowed horizontally off the edge of the screen on mobile instead of scrolling within the page; now wrapped in the same horizontal-scroll container already used elsewhere in the admin (e.g. the Dimensions/Questions/Norms tables)
* This release is versioned 2.0 to mark a major cumulative milestone rather than a single change -- see 1.4.0 through 1.4.8 below for the full detail of everything it includes: Test Versioning (immutable per-test definition snapshots, with results traceable to exactly which version scored them), AND/OR Conditional Logic with six comparison operators and dependency-aware question randomization, consent provenance (versioned/timestamped per result), response-quality analysis (Ravanix Pro), full norm provenance metadata (norm set, source, population, country, sample size, collection year, methodology -- Ravanix Pro), a trend-chart metric selector (raw/percentage/Z/T/percentile -- Ravanix Pro), and a systematic object-ownership/security hardening pass across every admin save-and-delete handler

= 1.4.8 =
* Fixed (important): a Conditional Logic condition checked against an *unanswered* source question could incorrectly evaluate as if that question's first listed answer option had been selected (a jQuery .val() quirk on a set of same-name radio buttons with none checked), causing a dependent question to show or hide incorrectly from the very start of the test -- most visibly, a question the participant never realized was relevant could end up required, blocking submission with no obvious explanation. Verified fixed with an automated browser test covering single-page, multi-page, equals/not-equals conditions
* Hardened: a malformed Conditional Logic condition can now never break the rest of the page's scripting (including the submit button itself) -- it's skipped with a console warning instead

= 1.4.7 =
* New: results_scores table gained a norm_metadata_json column (schema-only in Lite; populated by Ravanix Pro 1.2.11's new norm provenance fields) so a dimension score stays linked to exactly which norm reference it was scored against, even if that norm is later edited

= 1.4.6 =
* New: total completion time (elapsed_ms, already collected for the existing minimum-completion-time anti-spam check) is now stored on every result and shown on the result page -- lays the groundwork for Ravanix Pro's response-quality analysis

= 1.4.5 =
* New: Conditional Logic now supports multiple conditions per question, combined with AND or OR, and four extra comparison operators (is not / greater than / less than / greater-or-equal / less-or-equal) alongside the original "equals" -- previously a question could only depend on exactly one other question's answer being equal to a fixed value. Existing single-condition questions keep working exactly as before, and quietly migrate to the new format the next time they're saved
* Fixed: when a test had both question randomization and Conditional Logic turned on, the random order could place a dependent question before the question it depends on, making it impossible to satisfy that condition; randomization is now dependency-aware -- a question can never be placed before another question its Conditional Logic depends on, directly or transitively -- while everything with no such relationship still shuffles freely

= 1.4.4 =
* New: Test Versioning -- an immutable snapshot of a test's scoring-relevant definition (dimensions, questions, answer options, weights, reverse keys, interpretations, and -- with Ravanix Pro -- norms, composites, branching) is now recorded the first time a result is submitted against it, and again whenever that definition changes; every result is stamped with exactly which version scored it. This is the foundation the roadmap's later Test Battery/Organizations work depends on, and immediately shows up on the result page: if a questionnaire has since been edited, older results now say so explicitly instead of silently implying they're still comparable to a fresh attempt. Existing results predating this update show as "not recorded" rather than a guessed version
* New: the research export columns (CSV, SPSS syntax/data, R script) now include the definition version each result was scored against

= 1.4.3 =
* New: consent provenance -- each result now records consent_version (a short content hash of the exact consent wording shown at submission time) and consented_at alongside the existing consent_agreed flag, so a later reviewer can tell not just whether a participant agreed, but to precisely which wording and when; shown on the result page when consent was required
* Fixed: object-ownership checks on every dimension/question/interpretation admin save-and-delete handler (and, in Ravanix Pro, norm/composite handlers) -- a posted child ID is now always verified against its claimed parent test before being written to, instead of being trusted on its own
* Fixed: deleting a dimension left orphaned rows behind in the norms table and the Pro multi-dimension-scoring link table; both are now cleaned up along with interpretations
* Fixed: a hardcoded SVG icon in the questionnaire-list "Titles only" layout was flagged by Plugin Check for missing output escaping; now passed through wp_kses() with a scoped allowlist
* Fixed (Ravanix Pro): norm matching could select an age- or gender-specific norm group even when the participant's age or gender wasn't collected, silently applying the wrong reference population's Z/T/percentile; a demographic-specific norm group is now only ever matched when that same demographic datum is actually known for the participant
* Fixed (Ravanix Pro): the SPSS/R data CSV, .sps syntax file, and .R script downloads didn't include a UTF-8 BOM, so Persian/non-Latin test, dimension, and participant-field text could display as garbled characters when opened in Excel or a plain text editor on Windows
* Fixed (Ravanix Pro): a few numeric fields in the JSON test-import path (validity_threshold, and min_age/max_age on both dimension and composite norms) weren't cast to a number like every sibling field, so a malformed import file could store a non-numeric value in a numeric column
* Housekeeping: the .pot translation template is now fully in sync with the source (every translatable string has a matching entry, and a few strings that were present with a stray escaped-apostrophe artifact and a stale line reference are now correct)

= 1.4.2 =
* New: [ravanix_test_list] and the questionnaire-list block now support limit="N" (0, the default, shows every published test) and order="newest|random" -- available in the block editor as "Maximum number to show" and "Order", for every layout (Grid, List, Titles only alike)

= 1.4.1 =
* New: a third "Titles only" layout option for the questionnaire-list Gutenberg block (and the underlying [ravanix_test_list] shortcode, via layout="titles") -- shows just each published test's title, marked with a small Ravanix glyph instead of a bullet, with no image/excerpt/button. In the block editor, the column-count and image/excerpt toggles are hidden while this layout is selected, since none of them apply to it

= 1.3.10 =
* Fixed: the actual root cause of the Settings page tabs not working under an RTL/Persian admin language (1.3.9's fix was a real, worthwhile improvement, but not the cause on the reporting site): WordPress derives a top-level menu page's hook-name component from sanitize_title() of its translatable *menu title*, not from the literal slug passed to add_menu_page() -- so when a Persian translation for "Ravanix" is active, the Settings page's actual hook was something other than the hardcoded "ravanix_page_ravanix-settings" this plugin was comparing against, and its CSS/JS silently never loaded at all (confirmed via the reporting user's own document.styleSheets/document.scripts console output). The hook is now captured from add_submenu_page()'s own return value instead of guessed

= 1.3.9 =
* Fixed: on the redesigned Settings page (1.3.8), the tabs could fail to switch at all under an RTL admin language (worked fine under LTR) -- on the reporting site, this traced back to a host/security-plugin Content Security Policy blocking eval(), which broke the rich-text editor's RTL-specific code and, because that failure wasn't isolated, took tab-switching down with it too. Tab switching no longer depends on the rich-text editor succeeding at all; if it fails for any reason, the affected field just stays a plain text box instead of breaking the rest of the page

= 1.3.8 =
* New: redesigned Settings page -- 4 tabs (General, Notifications, Roles & Permissions, Tools) with card-grouped sections, replacing the previous single long-scrolling page. All existing fields kept exactly as before (same names/values); only the layout changed
* Fixed: the "Copy" buttons next to each shortcode, previously plain text, are now actual buttons that copy the shortcode to the clipboard
* Accessibility: the new toggle switches have a visible focus indicator for keyboard navigation (WCAG 2.4.7), and the tab bar uses proper ARIA roles (role="tablist"/"tab", aria-selected)

= 1.3.7 =
* Fixed: the "Ravanix Manager" role (added in 1.3.6) had only the read capability besides its Ravanix-specific ones, which is indistinguishable from a mere site visitor/customer to plugins like WooCommerce that redirect anyone without edit_posts away from wp-admin to the frontend account page. Adding edit_posts (the same baseline capability WordPress's own Contributor role has, for the same reason) fixes wp-admin access on any site where such a plugin is active, on both new and already-created "Ravanix Manager" roles
* Improved: notification messages (Settings → Email notifications) now use the Classic Editor (rich text: bold, links, paragraphs) instead of a plain textarea, and are sent as formatted HTML email instead of plain text. Every placeholder value that can come from a participant's own submitted text ({participant_name}) is escaped before being placed into the formatted message, since that message is now HTML

= 1.3.6 =
* New: role-based permissions. Six capabilities (manage tests, view results, delete results, export results, import/export test structure, manage settings) can each be granted to any WordPress role from Settings → Roles & Permissions, so e.g. a counselor can manage tests and view results without full site administration. A ready-made "Ravanix Manager" role is available under Users → Add New as a quick start. A site administrator always keeps every capability regardless of this table -- enforced at the WordPress capability-resolution level (a 'user_has_cap' filter), not just in this plugin's own code, so this can never turn into a lockout
* Improved: the "Participant Results" list only shows bulk-select checkboxes and the "Delete" action to a user who actually has the new delete-results capability

= 1.3.5 =
* New: two extension points in the email-notification system (added in 1.3.4) for Ravanix Pro to use -- 'ravanix_notification_settings' (per-test recipient/subject/body/on-off overrides) and 'ravanix_notification_placeholders' (extra {tokens}, such as Pro's opt-in {scores_summary}). No effect on Lite-only sites; Lite itself never adds a callback to either
* New: 8 new columns on the tests table (notify_admin_mode and related, notify_participant_mode and related) -- inert placeholders unless Ravanix Pro 1.2.4+ is active; existing tests default to "use the site-wide setting", identical behavior to before this update

= 1.3.4 =
* New: optional email notifications after a test is submitted -- to the site admin (with a link to the result in wp-admin) and/or to the participant (a simple thank-you), configurable under Settings. Neither email ever includes scores or answers, since email isn't encrypted and this data is treated as sensitive; the participant email is only sent if that submission actually collected an email address

= 1.3.3 =
* Improved: the gap between the "Add New..." form and its list/table (Dimensions, Questions, Interpretation Ranges tabs) is narrower (15px, was 30px), and there's now a dedicated in-between layout (45%/55%) for tablet/small-laptop widths (783–1100px), between the desktop 40%/60% split and the single-column mobile view

= 1.3.2 =
* Improved: loading a test's full structure (dimensions, questions, options, interpretations, norms, composites) now uses a fixed, small number of batched database queries instead of one query per dimension/question/composite, and the result is cached — noticeably faster on large questionnaires (200+ items), with no change to stored data or behavior
* Improved: activating/updating the plugin on a site with many existing questionnaires no longer risks a timeout re-syncing the custom-post-type mirror for every one of them in a single request; on larger sites this now finishes in the background instead
* Security: the anonymous-participant token (used to let a guest view their own result later) is now generated with PHP's cryptographic random-number source rather than a general-purpose password generator; a token issued before this update keeps working unchanged
* Fixed: an admin-chosen interpretation-range color that was light could make the fixed white badge text (result page, admin result view, PDF/CSV reports) hard to read; it now gets the same automatic WCAG-AA contrast check already applied to the brand color, computed fresh each time rather than stored
* Fixed: the "Add New..." / list two-column layout shared by the Dimensions, Questions, and Interpretation Ranges tabs used a fixed-pixel form column next to a flex-grow list column, which became visibly unbalanced on wide screens (the form column stayed a small fixed width while the list column claimed all the rest); it's now a CSS Grid with a fixed 40%/60% split that holds steady at any screen width
* Fixed: the Questions and Interpretation Ranges tables could overflow their column and the page instead of scrolling internally, the same issue already fixed on the Dimensions tab in a previous release; now consistent across all three tabs
* Fixed: two colors used on the "Upgrade to Pro" admin screen (the button, and the ✓/✕ icons in the comparison table) measured below WCAG AA contrast against their background
* Fixed: a database error while saving a test was shown to the admin with the raw SQL error text; it's now a generic message, with the real error going to the debug log instead

= 1.3.1 =
* Fixed: several admin tabs (Dimensions, Questions, Interpretation Ranges, and Ravanix Pro's Norms and Composite Factors) needed horizontal scrolling on their main table; the "Add New..." form column next to it is now 20% narrower and no longer expands to steal that space

= 1.3.0 =
* New: test intro now shows question count, estimated time, and total participants so far, each with its own icon (replaces the old plain "Number of questions" text)
* Improved: "Start Test" and "Submit answers" buttons are noticeably larger and more prominent

== Upgrade Notice ==

= 2.1.0 =
Adds a new database table (validity rules) automatically on activation. Existing single-threshold validity scales keep working unchanged. If you use Ravanix Pro, update it to 2.1.0+ at the same time.

= 2.0.1 =
Recommended: adds the Personal Data Exporter (Tools -> Export Personal Data). No database changes since 2.0.0.

= 2.0.0 =
Major update: Test Versioning, richer AND/OR Conditional Logic, consent provenance, two mobile-display fixes. Adds new DB tables/columns on activation -- back up first. If you use Ravanix Pro, update it to 2.0.0+ at the same time.

= 1.3.2 =
Recommended: faster loading for large questionnaires, safer background site updates, and several accessibility/security fixes. No database changes.

= 1.3.1 =
Recommended: fixes horizontal-scroll layout issues on several admin tabs.

= 1.3.0 =
Recommended: fixes dark-mode readability that 1.2.3 missed on three pages, plus a horizontal-overflow bug on the Dimensions admin tab.

== Technical Note ==

Chart.js is bundled locally (no external CDN). The source translation
file is at languages/ravanix.pot; use Poedit or Loco Translate to build
.po/.mo files for your language from it.
