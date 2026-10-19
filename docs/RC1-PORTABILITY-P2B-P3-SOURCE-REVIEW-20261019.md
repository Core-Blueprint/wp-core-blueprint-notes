# Core Blueprint Notes — RC1 Portability P2-B / P3 source review

**Date:** 2026-10-19
**Branch:** `feature/notes-rc1-portability-roundtrip-batch-20261019`
**Status:** SOURCE implementation candidate **UNTESTED locally**. No browser/runtime acceptance, Golden, merge or customer release.
**Base:** Current Data Exchange v1 contract; no Base patch.

## Implemented on the feature branch

- `ArchivePreflight` validates the entire catalog + Notes record graph before import planning; 5000 record/10 MiB bounds, exact fields, tags, cross-reference and UTF-8 safeguards.
- `ImportPreview` creates keyed owner-private destination catalog IDs, refuses silent name collisions, shows a create-only plan and warnings for status downgrades. Rechecks digest before mapping. Takes **only verified** journal receipts for prior completed notes.
- `ImportSession` binds a complete validated JSON envelope, current WordPress actor and a one-request private random ticket. The capability disappears in `finally` and is never exposed to a browser. Unexpected per-record changes fail closed.
- `ImportPlanner` generates strict Base `create_only` catalog/note/skip plans. The source ID is never treated as a target WordPress ID.
- `ImportJournal` atomically reserves an owner+archive+record intent via unique WordPress option name, verifies a post/owner/receipt before marking complete and **refuses all ambiguous retries**. A pending status requires review; no automatic duplicate insert is permitted. Real concurrent failures and recovery UI still need integration proof.
- `ImportApplier` implements an **inert** whole-session-validated catalog/note write adapter, using existing owner catalog meta and WordPress post/meta APIs. It reserves a journal intent before any note insert; verifies actual WP post and meta before completion; does not import sharing authority or foreign author. Its code is not an approved runtime write pathway.
- `NotesEntity` delegates planning/applying only when `ImportSession::authorized()`; `Provider` intentionally **still advertises only export + JSON**. Base `Engine::apply_json` therefore refuses a public import even if directly invoked.
- `OwnerExport` keeps JSON on the Base engine and Markdown ZIP on Notes. ZIP contains per-note Markdown/YAML, original Gutenberg raw sidecars and an exclusions/limitations manifest. The ZIP PHP extension must exist on the hosting environment or return a clear error; no public archive upload is created.
- `Portability\Page` provides an owner-only secondary WordPress Notes admin screen linked from Library. It is registered with the Notes parent then hides only its submenu row. Downloads are POST+nonce-protected and no-store/noindex. JSON upload is capped at 10 MiB, must be an actual upload, and offers **preview only**. No public apply route is registered.
- New owner UI gettext entries were reconciled across EN POT and NL/DE/FR/ES/IT/PT to **319 source messages**; 7 Git catalog blob pins revised. Installed translations not yet accepted.

## Necessary focused checks (before enabling provider import)

Use only a fresh/clean local feature branch checkout. Do not run Level 2 or build a customer ZIP at this stage.

```bash
cd ~/Downloads/wp-core-blueprint-notes
git fetch origin --prune
git switch feature/notes-rc1-portability-roundtrip-batch-20261019
git pull --ff-only
git rev-parse HEAD
git diff --check origin/main...HEAD

for path in src/Portability/{ArchivePreflight,ImportPreview,ImportSession,ImportPlanner,ImportJournal,ImportApplier,MarkdownArchive,OwnerExport,Page,NotesEntity}.php; do
    php -l "$path"
done
for path in tests/portability-{archive-preflight,import-preview,import-session,import-planner,import-journal,import-applier,markdown}-smoke.php; do
    php "$path"
done
for path in tests/portability-{import-applier,owner-export,page}-contract.cjs; do
    node "$path"
done
bash tools/i18n/check
git status --short
```

Report actual exit statuses. No PASS is inferred from this document, and `git status --short` must remain clean. If a focused test fails, correct that issue on this branch before continuing.

## Blockers to import activation

1. **Real WordPress Base Engine integration:** Source tests with mocks do not prove `Engine::preview_json()` and `Engine::apply_json()` use the validated Notes session correctly. Confirm exact fingerprint match and no accidental provider bypass.
2. **Owner data CAS and journal durability:** Test physical MariaDB with WP 7.0 / 7.1.3 and inject catalog-meta write failure, post-insert failure, post-meta omission, journal completion failure, concurrent/multiple request attempts and interrupted network/worker. Confirm clear pending/recovery states without any duplicate or silent data loss. Review WordPress user-meta edge cases (missing, duplicate, malformed rows).
3. **Cross-site private round-trip:** independent source and target installations/accounts, repeat same archive, categories/tags/favorites/pins, Gutenberg source, empty/large archives, locale/UTF-8, status and timestamps, absent/present Secure Share. Validate semantic re-export.
4. **Cache, admin, UI, portability documentation:** private downloads over HTTPS/CDN with no-store, actual browser upload refusal/security, keyboard, Base Light/Dark and mobile, ZIP extension and archive inspection. Verify no new Notes submenu appears.
5. **Provider activation:** only after tests above and independent source GO may `Provider` advertise `SUPPORT_IMPORT`. Add an explicit confirmed apply UI, bound to a new whole-document preview and owner nonce. Never enable hidden writes before these gates.

## Known limitations

- Markdown is a best-effort readable format; raw Gutenberg sidecars preserve the original blocks. No Markdown import, CSV or ENEX in this batch.
- ZIP creates short-lived, private temporary filesystem bytes and removes the staging file in `finally`. PHP ZIP availability on Cloud86 must be verified.
- Base limits remain 10 MiB / 5000 records (1 catalog plus up to 4999 Notes).
- A complete journal receipt does not mean source revision history, media, remote sharing or local WordPress post IDs are portable.
- A pending receipt intentionally blocks an automatic retry. A separately accepted recovery/reconciliation UX is still required for general customer use.
- No merges, tags, CI/Actions, staging/production deployment or customer release without separate explicit GO.
