# Notes RC1 — PI-1C source-only localization and documentation reconciliation

**Date:** 2026-10-21  
**Repository:** `Core-Blueprint/wp-core-blueprint-notes`  
**Branch:** `feature/notes-rc1-portability-roundtrip-batch-20261019`  
**Audited parent:** `e9627e884df47227593a0af643dacfb667976a28`  
**Scope:** source catalog synchronization, exact catalog-source integrity pins, stale import comments and documentation ONLY. No Base, import behavior, database schema, CI, release, merge or deployment changes.

## Observed acceptance before this documentation/source-only change

- Operator Level 1 source checks: PASS on the audited parent.
- WordPress 7.0 Level 2: PASS, runner-owned disposable schema cleanup PASS.
- WordPress 7.1.3 Level 2: PASS (21 tests, 570 assertions), cleanup PASS.
- P4 read-only preflights on WordPress 7.0 and 7.1.3: PASS, including real WP-CLI positional argument probe. No new sites or schemas.
- Internal Alpha26 fixture ZIP integrity: PASS on the audited parent; **it contains no compiled languages/** and is not a localized customer release.
- Earlier installed P4 two-site PASS belongs to Notes SHA `1954e3152d69586bc83004dd886628b3cf3d99de`, NOT the newer import-activation candidate.

## Catalog reconciliation

The previously versioned POT and six PO sources each contained **319** gettext source entries. Current `src/Portability/Page.php` replaced two older preview-only messages and introduced twelve import confirmation, duplicate/recovery, and completion messages. This source-only patch reconciles all seven catalogs to **329** active entries (319 - 2 + 12), with exact matching keys and `%1$d` / `%2$d` placeholders. The existing catalog Git-blob integrity test is updated to the new content; the six translations remain **editorial proposals, not human-approved content**.

The pinned canonical i18n tooling/reference and production string sources are unchanged. Canonical installed WordPress POT re-extraction, `bash tools/i18n/check`, `msgfmt` compilation and browser rendering on all six locales must be executed on the exact resulting commit before i18n SOURCE PASS is awarded. The historical POT/PO metadata timestamps are retained until a separate canonical generation/review run; no claim of fresh WP-CLI-generated catalog references is made by this remote source-only patch.

## Comment and documentation cleanup

Obsolete source comments saying that `SUPPORT_IMPORT` is not advertised or that the import adapter is dormant have been corrected. Actual behavior remains: Base advertises export/import/JSON; `NotesEntity::can_import()` accepts ONLY the temporary private `ImportSession` request ticket. Owner import confirmation, Base fingerprint validation, append-intent journal and failure-recovery semantics are unchanged.

## Still required before Golden

1. Operator runs `bash tools/check`, `bash tools/i18n/check`, and `node tests/i18n-git-source-contract.cjs` on the final exact SHA, checks clean Git status, and validates all six locale PO files with gettext `msgfmt`. On RED, stop and inspect; no acceptance claim.
2. Rerun P4 two-site synthetic Site A -> Site B on the final source and both WordPress 7.0 / 7.1.3, using a **new separate GO** for two new disposable Notes-only schemas per version. Verify absence of those schemas after each run with a fresh read-only P4 preflight. Protected `core_blueprint_notes_test` remains untouched.
3. PI-1D authenticated browser, HTTP/HTTPS, nonce/upload/owner/anonymous, invalid/stale plans, recovery/pending journal, Light/Dark, responsive/zoom, keyboard/a11y and QUIC/LiteSpeed cache bypass acceptance. The P4 synthetic CLI path does not prove these.
4. Human editorial approval of the six complete catalogs; operator verifies WordPress PHP MO and 30 script JSON catalogs. The localized candidate builder currently delegates to an Alpha26 batch with narrower historical branch pins; do not broaden/mute those guards in a content patch. Review the separate packaging/runbook gate before generating a **localized** installation candidate.
5. P5 exact-SHA Golden matrix, release artifact reproducibility, R01-R13 acceptance and explicit operator release/merge GO. `tools/build-release` intentionally remains fail-closed.

**Decision:** Source-only reconciliation candidate prepared. No Golden, i18n runtime, release or P4 current-SHA acceptance is implied.
