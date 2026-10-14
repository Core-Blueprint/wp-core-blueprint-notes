# Notes Alpha25: R08 Secure Share acceptance plan

**Status (2026-10-14): R08.1 ACTUAL SOURCE COMPATIBILITY PASS; R08.2 real-provider CLI/SQL runner PREPARED, NOT RUN; R08.3 browser HTTPS gate NOT RUN.** This file is under tests/evidence and is intentionally omitted from the pinned Alpha25 ZIP.

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

## R08.1 actual operator compatibility evidence: PASS

The operator confirmed that the existing `~/Downloads/wp-core-blueprint-secure-share` repository was checked out on `fix/core-admin-components-contract-v1` with uncommitted edits in `src/Integration/Suite.php` and `tests/suite-integration-regression.php`. These existing changes were **not** discarded, stashed, committed, switched or tested as final R08 authority. Instead, an independent clean `~/Downloads/wp-core-blueprint-secure-share-r08` worktree was opened at the published `main` commit `88c15c45a4898dd1c43c1dfad1d7265f5b693350`; `bash tools/check` completed `Secure Share SS4 checks: PASS`. Notes at `fc07c8f81970063badfde6c92f7b4be3bac65a34` then returned:

```text
Notes R08 Secure Share API compatibility source: PASS
Notes source HEAD: fc07c8f81970063badfde6c92f7b4be3bac65a34
Secure Share source HEAD: 88c15c45a4898dd1c43c1dfad1d7265f5b693350
R08 real HTTPS, provider persistence, password, expiry, revoke and rollback: NOT RUN
```

The initial `git worktree add origin/main` and subsequent `FETCH_HEAD` attempts did not establish a new checkout; the **already existing clean worktree** above is independently verified by the successful HEAD/status and SS4 checks. R08.1 is **CLOSED for source compatibility** only. This is not a runtime provider or external HTTPS PASS.

## R08.2 guarded real-provider WordPress CLI/SQL test: PREPARED, NOT RUN

The test-only files `tests/r08/wordpress-config.php`, `tests/r08/fixture.php`, `tests/r08/runner-contract.cjs` and `tools/check-secure-runtime` are staged on the existing Notes hardening branch. Their safety-source contract is wired into `bash tools/check` without requiring optional Secure Share to be installed merely to run Notes source checks.

The runner:
- Requires a **clean** detached Secure Share worktree pinned to `88c15c45a4898dd1c43c1dfad1d7265f5b693350` and a clean local Base.
- Requires the immutable Alpha25 ZIP SHA-256 `77035d6ba1c5e352d97f917eb8a4ddcaca09af3b36db155bbb2ab4303610e908`.
- Refuses an existing `core_blueprint_notes_r08_test` SQL database; creates one synthetic disposable MariaDB test schema and temporary WordPress 7.0 CLI-only site under `/tmp/core-blueprint-tests/notes-r08-secure-provider`. No HTTP server is exposed.
- Activates real Base, Notes Alpha25 and real Secure Share on that isolated site; verifies the actual Secure Share schema, public API readiness and Notes provider registration.
- Uses a synthetic private note and two test editors to prove cross-user issue denial, real password-protected grant creation, provider SQL persistence and expiry, bearer hash lookup, denial before password verification, authorized provider delivery of frozen HTML, unchanged share after private edit, Notes local revocation and real provider grant revocation with revision advancement, and rejection of the old bearer and stale context.
- Keeps the bearer, invitation fragment, password and grant UUID out of routine output. The temporary site and test database are cleaned through an ownership-aware trap; provider failures remain a genuine failure and are not silently converted into PASS.

**Run after pulling updated Notes test-only branch (the existing Alpha25 release package remains immutable):**

```bash
cd ~/Downloads/wp-core-blueprint-notes
git fetch origin
git switch feature/notes-rc1-golden-hardening-v1
git pull --ff-only origin feature/notes-rc1-golden-hardening-v1
git rev-parse HEAD
git status --short
php -l tests/r08/wordpress-config.php
php -l tests/r08/fixture.php
bash -n tools/check-secure-runtime
node tests/r08/runner-contract.cjs
bash tools/check
echo "77035d6ba1c5e352d97f917eb8a4ddcaca09af3b36db155bbb2ab4303610e908  dist/core-blueprint-notes-0.1.0-alpha25.zip" | sha256sum -c -
bash tools/check-secure-runtime
```

Expected result **only if the genuine PHP/WordPress/provider SQL test succeeds**: `Notes R08.2 real-provider runtime: PASS (isolated WP 7.0 CLI)`. If the isolated activation, schema readiness or any provider assertion fails, keep R08.2 **BLOCKED** and repair only the test or affected product after a reviewed patch. Do not infer that browser password gates and cookies have been verified from the CLI fixture, which supplies a vetted real provider context but bypasses interactive browser transport.

## R08.2 source gate correction after first operator attempt (2026-10-14)

Operator pulled Notes at `dda441a313cb2fcf2ed26a92aaf60f3a189e312e`. PHP lint for `tests/r08/wordpress-config.php` and `tests/r08/fixture.php`, plus full Bash syntax of `tools/check-secure-runtime`, were successful. The first standalone `node tests/r08/runner-contract.cjs` failed **before** starting any WordPress site or test database:

```text
R08 real-provider source contract BLOCKED: Fixture must exercise password, expiry-bound grant and revocation
```

**Cause:** test source gate used case-sensitive substring `password-protected grant denies context` but the fixture's actual assertion text starts `Password-protected` (capital P). The implementation was present. The test contract now checks the actual `Services::accessService()->context(...false/true)`, `PasswordHasher::verify()`, expiry, revoked state, revision advancement, bearer lookup and provider delivery calls rather than human-readable label spelling.

An additional source review corrected the foreign-owner REST expectation: WordPress normally returns **403 Forbidden** to a logged-in user denied at `permission_callback` (the original R08 fixture incorrectly required 404). This is a test assertion correction, not a change to access control.

**No genuine R08.2 runtime test has been executed.** Due to the chained `&&`, neither `bash tools/check` nor `bash tools/check-secure-runtime` was reached on this attempt. The immutable Alpha25 ZIP, Base, local Secure Share source/worktree and Notes product code were not changed. R08.2 remains OPEN pending actual test output.

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
