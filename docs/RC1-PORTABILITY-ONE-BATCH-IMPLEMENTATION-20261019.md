# Notes RC1 — Data Portability One-Batch Implementation

**Status:** INTEGRATED DEVELOPMENT BATCH APPROVED IN PRINCIPLE; CODE/RUNTIME ACCEPTANCE NOT YET COMPLETE.
**Date:** 2026-10-19
**Batch branch:** `feature/notes-rc1-portability-roundtrip-batch-20261019`
**Frozen UI baseline:** `f7d1015b2ea5b348e6993e76c0f9babbaddf1dd4`
**Overseer design:** `extensions/notes/rc1-portability-roundtrip-contract-v01-20261019.md`
**Base baseline:** `Core-Blueprint/wp-core-blueprint@d7b251fa0fe8df3e5b53dc1e9f1b866df508905e` (Data Exchange classes unchanged since accepted `dcbf5da...`)

## Batch objective

Deliver one integrated, owner-private Notes v1 portability experience, then validate **once** as a coherent candidate, instead of repeated one-line build/patch cycles:

- Exact Notes Data Exchange JSON export through the existing Base Engine.
- JSON import with complete file-wide validation, preview and explicit confirmation.
- Safe create-only owner import with source/target identity mapping, catalog relation remapping, duplicate/archive handling and durable recovery visibility.
- Readable `Markdown + YAML Front Matter` ZIP export with explicit Gutenberg-conversion limitations; no new data processor or external dependency.
- Base-themed authenticated, discoverable owner UI for export and import; no redundant Notes submenu.
- Real disposable source→destination→re-export semantic round-trip and negative security/failure tests.
- Reproducible internal Alpha26 development fixture and six-locale source/i18n reconciliation; RC1 customer release separately gated.

**Explicitly outside this RC1 batch:** CSV interchange, ENEX adapter, Markdown ZIP import, silent merge/update-existing, cross-owner/admin impersonation, collaboration, external asset copying, full revision history or attachments. No external cloud/AI processing. No new Core Blueprint Base capability without a separately reviewed, generalizable need.

## Guardrails and invariant source

- Notes' previous P1 `src/Portability/NotesEntity.php` `export_records()` is read-only, authenticated current-owner only, 1 catalog + Notes, max 4999 notes within Base 5000 records and 10 MiB. Preserve P1 real WordPress 7.0 / 7.1.3 test evidence, not weaken it.
- `CoreBlueprint\Core\DataExchange\Engine::preview_json()/apply_json()` is the canonical JSON transport. Do not fork envelope parsing or bypass Base for import writes.
- Base's `plan_import` is per-record and `apply_import` sequential fail-stop. **Notes must provide trustworthy full-graph preflight** before exposing any import writes. If impossible through its public Entity contract, stop and submit a small additive neutral batch-preflight proposal to Base; never fake whole-batch validation with per-record success alone.
- All imported Notes are authorized to the currently authenticated destination owner. Reject context owner/user/author overrides, foreign catalog IDs, duplicate source references, unknown schema, invalid types, unsolicited post status and malformed/not-natively-safe Gutenberg content.
- An imported Note must start **not shared**, with no source Public/Secure Share grant, snapshot, URL, provider key, read ability, token, authorization, or secret resurrected. Preserve source-site sharing unchanged.
- Allow only **create_only** in RC1. No implicit updates by matching title/WordPress numeric ID. Repeated import of the **same archive** must be idempotent or explicitly refuse with a safe no-write status; never silently duplicate. A different export/changed archive is an independent import subject to clear collision preview. No claims about stable cross-export identity without a versioned persistent source-ID design.
- Catalog definitions are first-class owner-private records, not WordPress taxonomies. Import must explicitly map source opaque notebook/category/tag IDs to destination owner IDs, honor 150 labels/kind and 12 tags/note, and refuse unresolvable collisions. Do not merge labels by name alone.
- Import preview is read-only and validates the **complete** document and all referenced labels, not only individual rows. Apply re-preflights the exact archive and target state, checks the plan fingerprint and expiry/actor, and refuses changed state.
- **Durable recovery:** pre-commit written intent, provenance, per-record progress, safe retry/compensation design; injected failure in note insertion, metadata linking, user-catalog write, journal write and retry must not lead to silent duplicates, accidental deletion or a misleading complete outcome. If recovery cannot be demonstrated, keep the import endpoint disabled and mark batch BLOCKED instead of claiming RC1.
- Download/upload must be owner-capability and CSRF guarded, cache-proof (`no-store`, `nosniff`, `noindex`), bounded, privacy safe and avoid storing private archives in publicly served locations. Prefer an in-browser, per-session file retaining flow for preview→confirmed apply over writing raw archives to WordPress uploads.
- Avoid dependency on Google/Microsoft, WordPress.org or third-party plugins.
- The active English source and six locale catalogs remain canonical; use literal gettext, review translations and update Git blob pins only after real extraction.

## One coordinated implementation batch: stages (no intermediate full builds)

| Stage | Work | Required proof before next stage |
|---|---|---|
| P2-A (contract) | Freeze versioned JSON field map, source/target IDs, archive digest/repeat policy, catalog limit/collision resolution, status/metadata fidelity, journal semantics and exposure exclusions | One reviewed schema/fixture/negative-matrix contract; zero mutations |
| P2-B (engine) | Notes full-document preflight + secure Base preview/apply adapter; create-only domain operations and persistent import journal; prove no writes in invalid/preview cases | Focused RED→GREEN tests including genuine isolated WP fault injection |
| P3-A (UI) | Authenticated first-party JSON download and upload/preview/confirm actions with one source of truth, Base token UI, same owner permissions | Browser owner/nonowner/cross-user/nonce/cache/large-file refusal tests |
| P3-B (open export) | Markdown + YAML Front Matter per Note, safe filenames, ZIP archive + manifest, explicit conversion warning/optional raw Gutenberg preservation on unsupported constructs | No overwrite, correct escaping, export without leakage, no hidden extra network path |
| P4 (integration) | Source Site A → Destination Site B (independent owners), export→preview→import→re-export, semantic compare; retry after injected faults | Real WP 7.0/7.1.3, Base current, optionally Secure Share present/absent, A/B/admin/anonymous denial |
| P5 (one final gate) | One full source suite, six-locale check, one internal deterministic rebuild/checksum, UI Light/Dark/responsive/a11y and release-document review | Exact HEAD, green logs, clean tree, ZIP SHA, acceptance ledger and separate GO/NO-GO |

**Fast development discipline:** use tightly scoped syntax/test checks while coding. Run the expensive canonical `tools/check` via `tools/build-alpha26-fixture` **once** for a frozen batch candidate, followed by required isolated real-WordPress tests. Do not run full Level 2 and repeat ZIP generation for every small code adjustment. Re-run failed focused cases then the exact-head final gate.

## Required test matrix

1. Pure contract: JSON schema and version, catalog order/count, note field restrictions, invalid references, duplicate IDs, names, tags, overlength/oversized and unknown fields.
2. Privacy: A/B owner isolation, anonymous refusal, administrator cannot silently import for other owner, note count metadata never leaked, nonce/replay failures, invalid uploads.
3. Semantics: all original Gutenberg block serialization preserved by JSON, notebook/category/tag relationships correct after target remap, markers/status/timestamps deterministic according to contract, zero inherited shares, export on destination matches normalized source semantic content (not local IDs).
4. Conflict/retries: repeat same archive, altered archive, identical notebook names, source WordPress ID collision, existing target owner notes, interrupted import and recoverable partial result with visible remediation.
5. Faults: catalog user_meta write, wp_insert_post, post-meta write, journal persistence, permission change between preview/apply, concurrent previews, stale fingerprint and failure after N records.
6. Archive: Markdown escaping/YAML quoting, Unicode, path traversal resistance, duplicate filenames, unsupported Gutenberg blocks, no accidental public temp files, no images/attachments promised as bundled.
7. Environments: 7.0 / 7.1.3 PHP 8.4+, mandatory Base, absent/present Secure Share, Light/Dark, 680px, 200% zoom, keyboard and screen-reader labels, HTTP download cache/CDN exclusion.
8. Limits: 10 MiB, 5000 total records, 4999 Notes with one catalog, bounded runtime memory, no silent truncation.

## Decision gate for Base

No Base source patch is authorized or assumed. Escalate **only if** real implementation proves the current per-record Entity contract cannot safely receive the full-document preflight evidence and still use the public Base `Engine::preview_json/apply_json` interface. A request to Base must be one additive, opt-in, domain-neutral preflight interface and must not move Notes catalog logic, Markdown conversion, journaling or owner UI into Base. Base source modifications require separate explicit GO and regression acceptance.

## Batch operator workflow

1. Development work occurs **only** on `feature/notes-rc1-portability-roundtrip-batch-20261019`, created from accepted UI baseline.
2. Review code and targeted tests after each stage, but do **not** demand repeated full release checks between stages.
3. Once stage P2-B safely works, perform integration tests against approved disposable DBs; never touch historical protected `core_blueprint_notes_test`, production or the dirty Secure Share checkout.
4. After P2–P4 complete, pin exact Notes and Base SHAs, run one complete source/i18n/internal-build cycle, ensure `git diff --check`, clean worktree, reproducible ZIP digest and detailed acceptance R01–R13.
5. No merge, CI/Actions, tag, customer release, distribution, staging installation or production deployment without separate explicit operator GO.

## Acceptance ledger

- Frozen prior Notes UI baseline: **operator visually stable**; toggle build PASS.
- Existing P1 JSON export-only: **SOURCE / isolated Base runtime PASS** on earlier revisions.
- This one-batch import/Markdown/owner UI: **IMPLEMENTATION NOT YET VERIFIED**; only branch and batch contract prepared.
- Any Base extension: **NOT PROPOSED / NOT IMPLEMENTED**.
- Customer RC1 / Golden: **NO-GO** pending the complete above evidence.
