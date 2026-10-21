# Core Blueprint Notes — PI-1A Public Import Contract v1.0

**Date:** 2026-10-21
**Decision:** PI-1A contract scope FREEZE approved by owner. **PI-1B implementation GO HOLD**, public import still OFF.
**Product evidence:** P4 real independent Site A → Site B PASS on WordPress 7.0 and 7.1.3 for Notes source `1954e3152d69586bc83004dd886628b3cf3d99de` with Base `dcbf5da9b7729efde77cf508a3b90f2929b8d0b1`.
**Review:** `docs/RC1-PORTABILITY-PUBLIC-IMPORT-ACTIVATION-CONTRACT-20261021.md`.
**Ownership:** Notes owns source validation, UI, permissions, mappings, journal and writes. Base owns JSON transport, registry, canonical plan fingerprint and import orchestrator. No Base product-code modification approved.

## Naming and governance

**PI-1A** = this contract freeze. **PI-1B** = Notes-only source implementation. **PI-1C** = isolated WP/security runtime. **PI-1D** = browser/HTTP/localization acceptance. Existing **P5** remains the final integrated RC1 Golden build/release gate and must not be renamed to public import.

This is a documentation-only approval. No Notes or Base source changes, provider activation, test database creation, CI, merge, tag, Marketplace/customer release, deployment or production operation are authorized by it. New, separate GO is required for PI-1B and again for any PI-1C disposable DB mutations.

## D1 — Create-only and identity

- Only Base `Foundation::MODE_CREATE_ONLY` is allowed. No update/merge by title or WordPress source post ID, no admin impersonation, no silent overwrite or deletion.
- Archive digest is computed from Notes validated record graph; imported per-note receipts are owner + digest + source-reference bound. Reimporting the same graph, even with another `exported_at`, must SKIP after verifying the target WordPress note, owner, classifications and journal. Preview/apply also bind to exact uploaded bytes and **Base** plan fingerprint.
- The present source `note:<numeric WordPress ID>` and archive digest are **not stable identity across changed exports**. The schema has no authoritative source-site UUID or durable per-note cross-export ID. Hence a changed archive is a new create-only candidate; user must receive explicit warning and acknowledge **possible duplicates**. No promise to detect all same-source changes. A conflict with existing distinct label names/IDs must fail closed, not silently merge.
- A later stable portable identity requires a separate versioned schema and migration decision; no export-time mutation just to synthesize an identity for RC1.

## D2 — Catalog IDs and auth-salt rotation

- Before public activation, replace the current `ImportSession` → `ImportPreview` use of `wp_salt('auth')`. The journal is salt-independent; derived catalog IDs currently are not.
- Use a versioned site-local **persistent random mapping seed**, at least 32 cryptographically random bytes generated independently of WP salts. It must be private, non-autoloaded, created once with exclusive `add_option` + verified reread and backed up with site data. Never send/export/log/display the seed, and never silently regenerate an invalid or missing seed once import provenance may exist.
- Generate catalog IDs via versioned domain-separated deterministic HMAC of seed, destination owner, validated archive digest, catalog kind and original opaque label ID. Keep 32 lowercase hexadecimal target IDs and refuse collisions with unrelated labels. All actor, digest, kind and source-ID inputs must be authenticated/validated.
- A changed/missing seed, legacy imported state, salt rotation, corrupt catalog or conflicting journal receipt must **fail closed** with recovery-required status; no automatic remapping, state deletion or second Note creation. Real WP tests must prove stable mapping and complete journal receipts across `AUTH_KEY`/salt rotation, including partial imports.
- No public-import state was accepted before this phase; any historical internal/import-test state handling must be explicitly examined rather than migrated blindly.

## D3 — Durable recovery without false success

- Base import is sequential and nontransactional. UI must distinguish `complete`, `failed`, `partial`, and `recovery required`; only independently verified records count as complete.
- Notes must reserve a journal intent before each note insert, validate owner/content/status/catalog/created+modified GMT timestamps and receipt linkage before marking complete. Pending/ambiguous inserts remain pending and never auto-retry, auto-delete or claim all-or-nothing rollback.
- An owner may view only safe statuses/counts and remedial guidance for their own imports. Privileged reconciliation must verify the exact archive digest, owner, persisted receipt, post and all expected fields before any manual transition. A repair write requires separate approved tests and explicit scope; no generic “retry pending” button.
- Concurrency, double-submit, stale target/catalog CAS, write fault, worker crash, timeout or rotated mapping seed must stop safely. Reporting must not expose private bodies, filenames beyond safe labels, authorization tokens or foreign user counts.

## D4 — Notes wrapper of public Base Engine

**Preview**: authenticated owner, purpose-specific nonce, genuine uploaded file, 10 MiB bound, complete Notes graph preflight. Within a fresh `ImportSession::with_validated_json($bytes, callback)`, call **`Engine::preview_json($bytes, MODE_CREATE_ONLY, $context)`** using the server-only temporary session ticket. Display its **Base fingerprint**, bounded counts, warning on changed archive/possible duplicate Notes, status normalization and exclusions. The current Notes private mapping fingerprint is NOT a Base apply fingerprint.

**Confirm**: a distinct nonce-protected POST with explicit create-only acknowledgement, a freshly uploaded **identical** file (or only browser-memory retained file) and its SHA-256 bytes digest plus expected **Base fingerprint**. The server must recheck owner, nonce, actual upload, complete archive, bytes digest, current catalog/journal, and build a NEW request-local Notes session. Inside it call only **`Engine::apply_json($bytes, MODE_CREATE_ONLY, $expected_base_fingerprint, $context)`**. The Base engine must replan and reject stale fingerprints before writes. Notes must recheck exact actor and per-record plan prior to each write.

**Security/UI**: only Notes' existing private owner admin screen; never persist raw uploaded private archives in public `uploads/`, options, transients, mail, logs or an external service. Never put the Notes session ticket in HTML/JS/API. Without browser-JS users can select the same archive again. Use Base UI tokens, translation-safe notices, accessibility/focus, `no-store`, `noindex`, `nosniff` and post/redirect/get results that cannot reapply on refresh. No imported Public/Secure share authority, AI writes, external fetches, revisions or media.

`Provider::SUPPORT_IMPORT` remains absent until negative/positive public Base contract tests have proved that **all** direct Engine calls lacking the exact request-local Notes ticket are forbidden even with a registered import descriptor. Any demonstrated missing generic Base primitive requires a separate Base review/GO, never a Notes fork of the Engine.

## Required acceptance gates

| Gate | Required result |
| --- | --- |
| PI-G1 | Canonical Base preview → expected fingerprint → confirmed apply; wrong bytes, actor, target, plan, mode or fingerprint => no writes |
| PI-G2 | Anonymous, owner B, admin-as-other, forged nonce/upload/session ticket/direct Engine, oversized input refused without leak |
| PI-G3 | Same graph repeat SKIP after journal verification; changed export explicit duplicate warning; unrelated label collision refusal |
| PI-G4 | Salt rotation retains correct ID remapping; missing/corrupt seed, ambiguous old mapping or pending import fails closed |
| PI-G5 | Catalog CAS, date write, post/meta, journal completion, interrupted/concurrent insert and partial recovery evidence |
| PI-G6 | No sharing grants, remote authority, prior public URLs, confidential note bodies or foreign record counts |
| PI-G7 | Installed UI owner A/B/admin/anonymous; Light/Dark/mobile/zoom/keyboard, origin+CDN private headers, 7 locales |
| PI-G8 | Existing P4 two-site semantic roundtrip and cleanup rechecked on exact new product HEAD on WP 7.0 and 7.1.3 |
| PI-G9 | Final P5 R01–R13 ledger, six-locale editorial approval, reproducible ZIP/SHA + independent release GO |

**Hard STOP** for unauthorized data, imported share authority, unrecoverable silent partials, stale fingerprint accepted, unverified pending retry, timestamp changes, or cleanup ownership mismatch.

## Execution sequence and next decision

1. PI-1A docs freeze, this GO: **complete once committed**; P4 PASS stays tied to its old exact tested product SHA.
2. PI-1B consolidated source RED/GREEN batch (one implementation GO): mapping seed + journal recovery contract + public Base preview/apply + two-request UI, no speculative Base modifications, no repeated complete release builds per micro-patch.
3. PI-1C separately approved disposable WP 7.0/7.1.3 new-only test schemas; protect `core_blueprint_notes_test` and leave shared MariaDB helper unchanged.
4. PI-1D browser/http/accessibility/i18n; then existing P5 integrated RC1 Golden.
5. GO for merges, distribution and deployment is separate; all remain HOLD today.

**Escalation rule:** If true source/Level-2 testing reveals that safe Notes-owned complete graph preflight cannot be maintained through the Base public API, return **CONTRACT REVISE**, stop write activation and propose only a neutral optional Base batch-preflight hook for review. Never bypass the Engine.
