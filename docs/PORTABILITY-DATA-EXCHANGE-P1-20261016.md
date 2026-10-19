# Notes Portability P1: Base Data Exchange export adapter

**Date:** 2026-10-16\
**Branch:** `feature/notes-rc1-portability-data-exchange-v1`\
**Derived from:** Notes `9625ba3c95de76a865c0d2c70ca255bfc12d62ff`\
**Base reference:** `Core-Blueprint/wp-core-blueprint@1cab1e41bda30334fbd54dec24b105812b5aee0c`, public API 1.2\
**State:** EXPORT-PROVIDER FOUNDATION ONLY. **No browser, download endpoint, import writes, zip transport, RC1 release or Golden status.**

## Scope and public contract

Notes registers a first-party implementation via Base's public interoperability lifecycle:

- Base contract: `core-blueprint/data-exchange.entity/1`
- Provider: `core-blueprint-notes`
- Entity: `personal-notes`
- Advertised capabilities: `export`, `format.json` (NOT import, CSV or mapping)
- Notes schema version: `1`
- Base transport: `CoreBlueprint\Core\DataExchange\Engine::export_json()`, **internal PHP only in this slice**.
- No HTTP endpoint or download UI is exposed; **do not use an untrusted endpoint to call the engine**.

The schema emits exactly one `catalog` record followed by the current authenticated owner's non-trashed notes, ordered by WordPress ID:

- Catalog: the three per-user arrays `notebook`, `category`, `tag` with opaque IDs and names.
- Each note: source-local `reference` (`note:<source-post-id>`), title, raw Gutenberg block serialization, WordPress status, creation/modification GMT, notebook/category/tag references, favorite and pinned preferences.
- Source post IDs are **not cross-site portable identities**. A later importer must map source refs and catalog IDs to distinct target records. No target user/author IDs can be supplied in the export context.
- The record layout deliberately contains **no** share mode, active Public/Secure Share snapshot, bearer, key, provider identifier, secret, revision copy, media asset, site credentials, or audit payload.

Permission and completeness invariants:

1. The current authenticated WordPress user must have `cb_notes_edit`; exports are only for their personally authored notes, even when an administrator has global Notes management privileges.
2. Every fetched note must pass `Access::can_read` and the exact owner/post-type checks. No cross-user count or note body is returned.
3. Malformed catalog entries, broken notebook/category/tag references, orphaned legacy notebook labels or a changed/incomplete page enumeration fail closed with a generic `WP_Error`. Never return a partial “successful” export.
4. Pagination uses 100 IDs per query. Base Data Exchange enforces at most 5,000 total records and 10 MiB. The Notes provider reserves one slot for the catalog: at most **4,999 notes** in this first slice; exceeding it is an explicit error, not truncation.
5. Export is read-only: never migrate, save, revoke, or alter a note just to export it.
6. `can_import()` always returns false and both import callbacks reject calls. This is not a round-trip solution yet.

## Follow-up implementation plan

**P2:** Define stable destination-independent identity/collision policy and preview/apply behavior. Build a fail-closed multi-record import plan that first validates the complete owner catalog and note relations, never publishes notes or restores share grants. Base Data Exchange's sequential fail-stop apply is **not atomic**: design a resumable/idempotent import journal and safe compensation/reconciliation before offering mass import.

**P3:** Authenticated nonce-protected import/export Admin UI and temporary private download transport, no-store/noindex and private file handling. Complete two-site real WordPress restore verification with independent user owners and bad-file/denial cases. A per-user ZIP or Markdown interoperability surface is an optional second transport only after JSON round-trip; do not duplicate Base's canonical engine.

**P4:** Revisions and larger exports (beyond 10 MiB/5,000 records) need a separately versioned and tested extension; do not imply that a note-only JSON export restores WordPress revision history or media.

**P5:** AI integration may reference the same canonical Notes application boundary, but **no Data Exchange export ability is MCP-exposed** by this work. Base Luna's AI Activity/Abilities/MCP audit remains pending and is a separate hard prerequisite for the Golden Audit.

## Exact-branch runtime runner inheritance

The existing Alpha26 disposable test harness accepts only explicit branch names. P1 adds this exact portability branch to eight guarded runners and the source contract. No wildcard, database authority, fixture or runtime policy is relaxed.

## Verification and governance

The source-only smoke `php tests/portability-export-smoke.php` is wired into `bash tools/check`. It covers registration, strict owner scoping, Gutenberg + catalog + marker content, invalid references, malformed catalogs, overflow and import denial. **It is not a true Base Engine/WordPress runtime proof.** Full `bash tools/check`, `bash tools/i18n/check` and an exact HEAD localized candidate build must pass in the owner's checkout before field testing.

No change to Base or Secure Share. No changes to the pinned English source and six i18n catalogs (provider labels reuse existing gettext entries). No merge into main, version bump, CI/Actions, Marketplace publication, or staging/production deployment without explicit GO.
