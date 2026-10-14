# Notes Alpha25: R08 Secure Share acceptance plan

**Status (2026-10-14): Optional API compatibility source gate PREPARED, NOT RUN. Real Secure Share/HTTPS gate NOT RUN.** This file is under tests/evidence and is intentionally omitted from the pinned Alpha25 ZIP.

## Actual first-party provider inspected

Provider repository: `christiaanbruinsma/wp-core-blueprint-secure-share`, default branch `main`, version `0.1.0-dev` (SS4 pre-v1). The currently exposed public interface includes:
- `SecureShare::isAvailable()`, `registerProvider(ResourceProvider)`, `issue(ShareRequest): IssuedShare`, `revoke(grantId, expectedRevision): bool`.
- `ResourceProvider::providerId()`, `deliver(AccessContext): ResourceResponse`; Notes must fail closed with `ResourceUnavailable` on a mismatched note, key, mode, grant UUID or grant revision.
- `ShareRequest` accepts provider/resource identity, view action, optional password, expiry and creator.
- `IssuedShare::invitationUrl()` contains an HTTPS URL plus a **fragment bearer**. The runtime exchanges it for a path-scoped Secure/HttpOnly/SameSite browser cookie, checks password/expiry and revalidates grant revision and provider-local Notes snapshot.

Run the opt-in **source compatibility** test against the real local checkout:
```bash
cd ~/Downloads/wp-core-blueprint-notes
node tests/secure-share-api-compat.cjs
```
It reads `~/Downloads/wp-core-blueprint-secure-share` by default (or explicit absolute `CB_NOTES_SECURE_SHARE_REPO` environment path), requires clean tracked working trees, records both HEADs and performs no network/WordPress/SQL actions. This is **not wired into `bash tools/check`** because Secure Share is optional.

## R08 real runtime cases (NOT RUN)

| ID | Requirement | Expected |
|---|---|---|
| R08-A | Provider entirely absent | Notes' private workspace remains usable, `secure_available=false`, Secure request fail-closed, no phantom invite/UI control |
| R08-B | Real compatible provider installed on isolated Base + Alpha25 | Registered provider ID is `core-blueprint-notes`; valid owner alone may issue a grant |
| R08-C | HTTPS invite + fragment exchange, password and expiry | Fragment not sent in HTTP GET, locked content invisible before proof, scope-specific secure session |
| R08-D | Wrong password, throttling, direct route, unauthorized user | Deny and no private Note or bearer/password leakage |
| R08-E | Revoke while authenticated browser session exists | Subsequent HTTPS GET/refresh denies and provider grant revoked, not merely Notes snapshot removed |
| R08-F | Trash/restore and Secure → Private/Public | Old grant and sessions remain denied, even after restoration |
| R08-G | Provider issuance/revoke failures | Fail-closed Notes delivery; if provider revocation fails, durable operator review/reconciliation required |
| R08-H | Expired/revised grant and mismatched resource key/grant ID/revision | Denied by both Secure Share runtime and Notes provider |
| R08-I | Historical DB or full hosting rollback of provider-side grant | Separate provider revocation/recovery proof; the Public HMAC epoch alone does not address Secure Share grants |
| R08-J | Plugin disabled/unavailable then reenabled | No fatal or unintended previously issued access |

## Security conditions

- Only synthetic Notes, dedicated test accounts and a disposable isolated site. No production or customer data.
- Pin and record the exact Secure Share repository commit; run its `bash tools/check` before the Notes integration runtime.
- Real R08-C–I **require HTTPS**: bare `php -S 127.0.0.1` over HTTP cannot validate the fragment-to-secure-cookie flow. Use an isolated TLS-enabled environment with restricted ingress and no real provider/customer credentials.
- Do not log or publish any raw invitation fragment, password, bearer or grant secret.
- A concrete provider risk: `Controller::revoke_old_secure()` removes Notes' local secure metadata **before** provider revoke. An exception is logged without sensitive values, but a returned `false` may not be surfaced; Notes then denies delivery locally while a provider grant may still exist. R08 must distinguish both statuses and define a tested operator reconciliation path.
- No live DNS, CDN changes, deployment, merge, CI/Actions, tags or release without separate GO.

**Next:** run the opt-in real-source compatibility check, then build a separately guarded real-provider runtime fixture and HTTPS validation. R08 stays **NOT RUN** until concrete evidence is recorded. The Alpha25 SQL (76 checks), HTTP (44 checks) and epoch-rotation rollback tests remain PASS for their previously tested scope.
