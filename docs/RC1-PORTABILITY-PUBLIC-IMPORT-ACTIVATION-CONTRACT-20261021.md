# Core Blueprint Notes — public import activation contract review

**Date:** 2026-10-21  
**Decision:** **REVISE / IMPLEMENTATION GO HOLD**. This is a source-only design review. No public apply is enabled.  
**Evidence sources:** Notes `Core-Blueprint/wp-core-blueprint-notes@78a1955cee8bda07dbd1706a6eaffd9e44171c67` (documentation-only descendant of tested product tree); P4 actual product SHA `1954e3152d69586bc83004dd886628b3cf3d99de`; Base `Core-Blueprint/wp-core-blueprint@dcbf5da9b7729efde77cf508a3b90f2929b8d0b1`; Suite Overseer `extensions/notes/rc1-portability-roundtrip-contract-v01-20261019.md` and `rc1-portability-one-batch-handover-20261019.md`.

## Product scope and completed evidence

P4 independent real WordPress two-site source→destination→reexport was **PASS on WordPress 7.0 and 7.1.3** on the exact Notes product source above. It exercised JSON and Markdown ZIP export, private owner-bound real create-only import, distinct local WordPress/catalog IDs, repeat SKIP and journal, Gutenberg/status/classification/marker semantics, historical UTC timestamps, and disposable-run cleanup verified through new read-only preflights.

**P4 is an internal adapter test, NOT a public import / owner UI test.** The public Notes provider in `src/Portability/Provider.php` currently advertises `SUPPORT_EXPORT` + `SUPPORT_JSON` only; Notes `src/Portability/Page.php` has a CSRF-guarded **preview-only** upload form and no confirmed apply action. The Base public `Engine::preview_json()/apply_json()` path has not been accepted end-to-end for Notes. RC1 and customer build remain HOLD.

**Roadmap naming:** The existing canonical implementation plan calls its **final integrated source/build/release gate P5**. This review is the **public import activation prerequisite to that P5 gate**; do not silently rename or overwrite P5 as “Public Import Activation.” A proposed implementation label is **PI-1** (public import) followed by existing **P5** (final acceptance). Owner confirmation of this nomenclature is pending.

## Source contract decision: Notes-owned wrapper around unchanged Base Engine

The accepted Base v1 engine already exposes:
- `Engine::preview_json($bytes, Foundation::MODE_CREATE_ONLY, $context)`: a complete set of normalized per-record plans and canonical Base SHA-256 plan fingerprint.
- `Engine::apply_json($bytes, Foundation::MODE_CREATE_ONLY, $expected_fingerprint, $context)`: re-runs planning, refuses changed fingerprints and sequentially applies with explicit `failed`, `partial` or `complete` reports.
- `resolve_entity()`: checks public Registry descriptor supports `SUPPORT_IMPORT` **and** domain `EntityInterface::can_import($context)` before planning/applying. There is no Base automatic Notes whole-document preflight or atomic multi-record transaction.

The Notes internal `ImportSession::with_validated_json($bytes, $callback)` already validates the **entire archive** and binds a temporary exact-record, current-actor, per-request ticket to `ImportPlanner` and `ImportApplier`. `NotesEntity::can_import` rejects missing/forged tickets. `ImportJournal` persists owner+archive+record deduplication independently of the temporary ticket.

**Proposed safe composition**, subject to RED/GREEN integration proof:
1. The Notes-owned authenticated preview handler validates its WordPress nonce, owner capability, genuine uploaded file, 10 MiB cap and complete JSON/Notes record graph. It enters one `ImportSession::with_validated_json` callback and calls **Base `Engine::preview_json` within that callback**, using ONLY the server-issued private ticket. It presents the Base preview fingerprint and a sanitized summary; never returns the ticket.
2. On a separate explicit confirm action, the user supplies the same archive again, Base fingerprint, purpose-specific nonce and an explicit create-only acknowledgement. A fresh request independently rechecks authentication, full archive, target catalog, journal receipts and current owner. It enters a **new** temporary Notes session and invokes **Base `Engine::apply_json` with the expected fingerprint**. Base must reject stale/change conditions **before any writes**. Notes checks ownership and exact plans again on every record.
3. Only after successful independent API/path tests may `Provider` advertise `SUPPORT_IMPORT`; every other caller lacking the request-local Notes session ticket must remain forbidden. Verify Registry discovery does not make import remotely usable or expose Notes without consent. No Base code change is approved or presently required.
4. The transient ticket is never stored in HTML, JSON responses, logs, user meta, options, a browser session or an external service. Do not accept client-provided `owner`, `post_author`, `cb_notes_import_ticket` or role/capability escalation. Import never activates Public/Secure Share, revisions, media, AI write abilities or outbound network/email.

**Preview ≠ Base apply:** the current `Page::preview_upload()` calls only Notes `ImportSession::record` and produces Notes' private mapping fingerprint, **not** the canonical Base preview fingerprint. That existing fingerprint must NOT be used as the `expected_fingerprint` argument of Base `Engine::apply_json`.

## Request boundary and user experience

Prefer stateless two-request upload: **preview** and **confirm** each read a bounded uploaded archive; do not store raw private JSON/ZIP in WordPress Media Library, a public upload directory, WordPress options, transients, or an uncontrolled server-side temp file. The preview shows counts of created/skipped notes, number of notebook/category/tag entries, status-downgrade warnings, conflicts and excluded content, without logging note titles/bodies or leaking another user's counts. The file may be kept only in browser memory as progressive enhancement; a no-JavaScript fallback can require reselecting the same file, with explicit explanation. The second request is still independently validated.

Use Notes' existing owner-only admin screen, Base semantic admin components, localized notices, nonce and capability enforcement, `no-store`, `nosniff`, `noindex`, safe response headers and an explicit post/redirect/get result. No auto-apply from preview; no cross-user/admin import-as-owner; no generic third-party file URL fetch. Confirmation should clearly describe create-only, source/destination ownership, change of unsupported statuses to private, notes not shared, and exclusions (revisions, media, attachments and share grants).

For Base's `failed` or `partial` results, show accurate bounded counts plus a **recovery required** state and safe next action. A `pending` import journal receipt must never be silently reset, auto-retried or labelled `complete`. A journal error must be distinguishable from malformed archive; the existing generic “archive could not be validated” message is insufficient for recovery. Operator reconciliation, provenance and access control need a separate accepted review contract; do not promise automatic rollback.

## Explicit issues to resolve before public activation

| ID | Severity | Source-backed issue | Closure needed |
| --- | --- | --- | --- |
| PI-01 | BLOCKER | Provider does not advertise `SUPPORT_IMPORT`; owner UI has no apply endpoint | Add only behind request-local whole-document Notes session + Base Engine preview/apply with exact Base fingerprint; prove unauthorized direct calls fail closed |
| PI-02 | BLOCKER | Current preview returns internal Notes plan/fingerprint rather than Base's public fingerprint | Both requests must independently use Base plan API; mismatch/changed actor/archive/catalog/journal before apply => zero writes |
| PI-03 | BLOCKER | Pending journal means ambiguous create; only generic error visible | Owner-safe pending/partial/review UI plus tightly controlled reconciliation policy; no automatic retry/delete |
| PI-04 | BLOCKER | Destination catalog IDs are HMAC-derived using `wp_salt('auth')`, while durable receipt keys survive salt rotation | Verify repeat import after salt rotation, existing labels/receipts, safe idempotency; on collision stop with clear recovery, never duplicate or automatically remap to another owner's label |
| PI-05 | DECISION | Archive digest plus source-local `note:<WP-ID>` is not a persistent identity across changed exports | Document that a changed archive is a **new create-only import** or design approved stable portable IDs; no silent update/merge by title or post ID; give clear collision/duplicate warnings and obtain owner scope acceptance |
| PI-06 | BLOCKER | Existing P4 proves internal CLI path, not browser HTTP security/recovery | Real private WP browser/API multi-owner, administrator and anonymous, invalid upload/nonce, stale plan, failure injection, absent/present Secure Share and cache/CDN evidence |
| PI-07 | QUALITY | Six PO locales have 319 source keys, editorial approval open; new UI copy changes catalogs | Localize and review complete import/recovery/security strings, PHP/JS gettext, 7 locales installed and no unapproved customer distribution |

**No hypothetical Base patch:** first demonstrate PI-01/02 with the current Base v1. If an actual Base contract limitation prevents whole-document Notes attestation before all apply writes, stop and propose one narrow neutral/opt-in Base preflight hook for independent approval. Do not fork the Base engine, mimic its fingerprint, weaken provider checks, or use a hidden direct Notes write endpoint to evade Base.

## Proposed implementation sequence — requires separate GO

**PI-A: source RED + contract tests.** Assert direct Base preview/apply are forbidden without request-local ticket; prove valid session-mediated Base fingerprint, changed file/catalog/actor rejection; two-request equality and no confirmed action before explicit nonce/acknowledgement. Review token absence and admin-post routing in source. No database mutation.

**PI-B: Notes-only controlled activation candidate.** Wire public descriptor support strictly through existing `can_import` gate; Notes-owned read-only Base preview and explicit two-step apply route (no silent mutations). Use Base UI tokens and accessible copy; preserve fail-closed behavior. Make no Base code changes. Maintain existing featurebranch only.

**PI-C: focused unit/integration tests, then independently approved disposable WordPress 7.0 and 7.1.3 runtime tests.** End-to-end Base preview→fingerprint-confirmed apply; replay, changed file and changed destination, invalid nonce/owner, journal partial/pending, simulated failures before/after note creation, restored-salt/collisions, zero resurrected sharing authority, 10 MiB and 5000-record refusals. Execute only after separate operator new-only-schema GO.

**PI-D: real UI + HTTP + accessibility acceptance**, including owner A/B/admin/anonymous, private response and LiteSpeed/QUIC CDN bypass, Light/Dark, mobile/200% zoom, keyboard, safe messages, locale output, no external calls. Any failed privacy/recovery case is HOLD.

**Existing P5 stays the final integrated one-batch gate:** exact frozen Notes/Base heads, Level 1, Level 2, Level 3 fixture/build and SHA, source/i18n reconciliation, full R01–R13 acceptance ledger, RC1 Golden recommendation, then independent explicit GO for merge/release/distribution.

## Governance and outcome

**Source-only architectural conclusion:** **REVISE** before implementation approval; public import remains disabled. Internal P4 two-site PASS remains valid for the exact tested SHA. No CI/Actions, Base change, customer release, staging/production mutation, WordPress.org/Marketplace launch or main merge. Document review is NOT operator approval to enable import, patch code or create databases.

**Next decision requested of owner:** accept (a) Notes-only Base Engine wrapper and (b) PI-1 public import as prerequisite to the existing P5 Golden final gate, including explicit portable-identity and post-salt-rotation policy. After approval, request a separate implementation GO on this same feature branch.
