# Notes RC1 i18n | canonical first-party tooling

**CONTENT HOLD**: the Notes repository does not yet contain six reviewed locale PO catalogs.
English is the source language; required locales: nl_NL, de_DE, fr_FR, es_ES, it_IT, pt_PT.

The tools reuse the pinned canonical Core Blueprint i18n engine 1.1.0 from Bookings without changes.
Environment: Python 3, WP-CLI wp i18n commands, gettext msgmerge, msgattrib, msgfmt, Git.

Read-only validation: python3 tools/i18n/check-reference ; bash tools/i18n/check
Mutating operator-only synchronization: bash tools/i18n/update

The update command requires the six existing reviewed PO sources and refuses missing catalogs.
The check command refuses stale, fuzzy and untranslated strings. No live translation service is used.
Do not create empty catalogs or substitute English copies as approved translation evidence.
Source POT must come from current PHP and JavaScript using WP-CLI make-pot.
Generate JS translation JSON using WP-CLI make-json only from reviewed PO sources.
Customer packaging and browser runtime acceptance remain blocked until catalog review and tests.
The Alpha26 ZIP remains an internal fixture, never an accepted release.
