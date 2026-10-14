# Notes RC1 | Fail-closed historical-share recovery guard: implementation specification

**Status: FEATURE-BRANCH GO RECEIVED, ALPHA25 IMPLEMENTATION STAGED; RUNTIME SECURITY VALIDATION NOT YET PASS.** Linked risk reproduction: `RC1-HISTORICAL-SHARE-ROLLBACK-DECISION.md`, 2026-10-14 on WordPress 7.0. The Alpha24 authorization predicate reaccepts an old Public key after restoration of an older SQL snapshot. This spec does **not** authorize altering production data, host settings, Base, or existing Public/Secure Share links.

## Security guarantee and explicit limitations

**Required outcome:** An old, previously revoked share must not be retrievable when its associated historical backup is restored through the documented **quarantined recovery procedure**. This must remain true *before* the restored site is reopened to unauthenticated traffic, and through repeated origin and cache tests after reopening.

WordPress-local `postmeta`, `options`, transients and local audit rows in **the same SQL snapshot** cannot by themselves prove revocation is monotonic. Even a second local filesystem value is reversible if the hosting provider restores an old snapshot of **both files and database**. Accordingly:

- No claim of automatic, guaranteed revocation across *arbitrary* full-host rollback without an independent durable trust anchor.
- A WordPress/PHP plugin cannot retroactively block an **older copy of its own PHP code** restored into a publicly reachable site. Quarantine must be enforced outside that rollback domain (webserver/hosting access control, separate controlled edge).
- Response `Cache-Control: no-store` and CDN purge are important but are **not** sufficient to preserve revoked-key state across SQL or filesystem restoration.

## Proposed RC1 approach: controlled recovery barrier and share epoch

A. **Out-of-database site epoch/key**, provisioned and access-controlled per installation. The final mechanism must be compatible with ordinary self-hosted/shared WordPress hosting and not depend on third-party SaaS. A protected host config/environment secret can be used; never expose it through the frontend, REST API, plugin repository, UI debug output, cache, or logs. A WordPress option is **not** an acceptable anchor.

B. **Binding of newly issued Public grants to the current epoch.** Sign an immutable, versioned record over the opaque share key, the WordPress note ID, a frozen snapshot hash, site/trust-domain identifier and grant schema/version using an HMAC with the configured key. For Public delivery verify the signature in constant time **and** all existing owner-independent note/mode/key/snapshot invariants. A signature must never authorize an otherwise private or trashed note. The share token itself must never be printed in logs.

C. **No silent legacy bypass.** Existing Alpha24 shares have no epoch-bound signature. Defaulting to accepting them would reintroduce the exact bug. The rollout needs an operator-approved migration policy that either revokes legacy links and asks owners to reissue new ones, or migrates **only with a verified, explicit consent/acknowledgement and complete acceptance coverage**. Missing, unreadable, malformed or mismatched epoch material must result in **deny**, never in acceptance of a legacy unsigned grant.

D. **Recovery lock outside the restored SQL state.** For an older-backup restore: put all public Notes delivery and relevant Secure Share delivery under an independently enforced network/host quarantine **before** restoring. Rotate/recreate the site epoch *after* quarantine but *before* reopening traffic; then restore the SQL archive; verify that all snapshot signatures bound to the prior epoch now fail. If the provider also restores host configuration/files, rotate again **after** that restore and verify at the origin before reopening. Do not let a WordPress-admin click substitute for host isolation while restoring old files.

E. **Secure Share is a separate authority.** Its external grant lifecycle requires explicit provider-level revocation or emergency lock, not merely removal of Notes `SECURE_META`. If the provider's own state can also roll back, require provider-level independent recovery approval/rotation. Public and Secure Share gates remain separately evidenced.

F. **Friendly operator UX.** Notes Settings Hub should display current recovery readiness (without revealing secrets), an explicit warning when shares cannot be safely issued, and a guarded *Recovery: revoke all share links* action for approved administrators (Base danger confirmation). A guided, copyable hosting checklist and clear owner notifications are essential for non-technical users. Such UI is supportive; it must not claim that it can ensure pre-restore quarantine by itself.

## Architecture choices requiring signoff

| Choice | Security impact | RC1 recommendation |
|---|---|---|
| WordPress DB-only revocation list | Restoring old SQL rolls it back | **Reject** as sole control |
| Immutable revocation ledger outside SQL backup | Can deny old tokens across DB restores if ledger retained; append durability and backup scope require care | Investigate only if protected shared-hosting storage is verifiable |
| Explicit host/site epoch rotation with HMAC-bound snapshots | Denies old grants **after rotation**; needs trustworthy external state and operator restore procedure | **Preferred narrow RC1 implementation**, with no unconditional rollback guarantee |
| Forced revocation of Alpha24 unsigned Public links on upgrade | Prevents legacy bypass but breaks old sharing URLs | Prefer secure fail-closed default, **requires operator GO** |
| Automatic restore detection based only on SQL timestamps | Vulnerable to rollback of marker and/or clock changes | **Reject** |
| Auto-open restored site before quarantine/rotation | Can serve resurrected links | **Reject** |

## Implementation boundary and proposed staged changes

1. **Design/contract gate:** specify key provisioning source, supported shared-hosting fallbacks, site identity, rotation authorization, grant envelope schema, error handling and no-legacy-bypass policy. Independent security review of key rotation and backup domain assumptions.
2. **Product patch after GO:** update Notes sharing issuance and authorization with epoch-bound signature, explicit fail-closed behavior, and documented migration policy; do not change private note contents, owner catalog, or ordinary save/revision semantics. Keep new code in Notes unless a verified Base API supports the same contract without an implicit global capability change.
3. **Recovery action after GO:** a separately reviewed administrative/CLI sequence that rotates the epoch and invalidates restored Public grants *before* the hosting quarantine is removed. For Secure Share additionally require provider-side revocation evidence.
4. **Negative integration regression:** create active snapshot under epoch E1, backup while active, revoke on source, rotate target epoch to E2, import older active snapshot, then assert denial by both `Controller::public_snapshot_allowed()` and actual anonymous HTTP 404. Assert the original source remains revoked, all private Gutenberg note and catalog data survive restore, no raw keys appear in logs, and unrelated WordPress content remains intact.
5. **Negative failure matrix:** missing/invalid epoch; insufficient file permissions; read-only/failed key storage; corrupted signature; wrong trust domain; restored old plugin files; and full files+database rollback. Every unsupported case must fail closed or remain externally quarantined. Verify that routine extension updates do not unnecessarily rotate the epoch.
6. **Real origin and infrastructure:** re-run WP 7.0 and 7.1.3 PHPUnit, disposable SQL restore, HTTP origin, and separate LiteSpeed/QUIC.cloud plus real Secure Share provider acceptance. Track artifact ZIP SHA for any product change. Never reuse the Alpha24 checksum to represent a newly built release.

## Defined acceptance result

- **FAIL** if any old share becomes reachable after epoch rotation or during the documented quarantine boundary.
- **FAIL** if missing key/signature falls back to legacy acceptance or if a cross-site cloned epoch grants authority in the wrong trust domain.
- **NOT RUN** for external CDN, real Secure Share, entire-site filesystem backup and every unexecuted negative scenario.
- **PASS** only for precisely demonstrated scenarios, with exact version/branch/ZIP hash, identities, source and target state, redacted HTTP/REST evidence, and a complete operator-approved recovery runbook.
- **Promotion blocker:** do not promote Notes to `v1.0.0-rc1` while the confirmed P0 historical-share rollback risk is unmitigated.

## Featurebranch implementation approved, release unapproved

Explicit featurebranch GO was received on 2026-10-14. Alpha25 now includes `PublicShareGuard.php`, HMAC-bound Public snapshot issuance and verification, a missing-key fail-closed REST/UI contract, isolated test fixtures and a guarded negative restore runner. **Runtime tests remain pending** and no host secret is generated by Notes. This GO does not authorize a merge, production migration, live key rotation, customer-link disruption, RC1 promotion or publishing.
