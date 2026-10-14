# Notes R08.2 acceptance evidence, 2026-10-14

## Operator execution: PASS

Notes featurebranch tested HEAD: `09d9858de9db2515e6a8a98a41d71d7065a675ba`.

Actual Secure Share provider source HEAD: `88c15c45a4898dd1c43c1dfad1d7265f5b693350` (clean isolated worktree at `~/Downloads/wp-core-blueprint-secure-share-r08`).

Artifact: `dist/core-blueprint-notes-0.1.0-alpha25.zip`; independently verified SHA-256 `77035d6ba1c5e352d97f917eb8a4ddcaca09af3b36db155bbb2ab4303610e908`.

The operator executed `node tests/r08/runner-contract.cjs`, `bash tools/check`, ZIP checksum validation and `bash tools/check-secure-runtime` successfully:

```text
Notes R08 first-party provider runtime source contract: PASS (runtime NOT EXECUTED)
Notes source checks: PASS
dist/core-blueprint-notes-0.1.0-alpha25.zip: OK
Notes R08 Secure Share API compatibility source: PASS
Notes source HEAD: 09d9858de9db2515e6a8a98a41d71d7065a675ba
Secure Share source HEAD: 88c15c45a4898dd1c43c1dfad1d7265f5b693350
Notes R08 first-party Secure Share CLI runtime: PASS (25 checks)
Notes R08.2 real-provider runtime: PASS (isolated WP 7.0 CLI)
HTTPS browser, cookies, CDN and provider-side old-backup rollback: NOT RUN
Cleaning up the single dedicated disposable R08 database and temporary site.
```

The R08.2 test uses the **actual first-party Secure Share extension**, its real grant persistence and registered Notes resource provider. It verifies synthetic owner/cross-owner authorization, an HTTPS-format invitation with secret fragment, password verification and expiry, database grant lookup, a provider-authorized frozen Note, Notes revocation, persisted provider grant revocation, denial of a replayed bearer and denial of a stale provider context.

**Accepted:** R08.1 API compatibility and R08.2 real-provider WordPress 7.0 CLI/database integration.

**Not accepted/not run:** TLS transport, JavaScript browser fragment transfer, cookie attributes observed over HTTPS, authenticated browser password POSTs and active browser session invalidation, edge/CDN cache isolation, expired-link wall-clock replay, provider restoration of an old SQL or host backup. These remain explicit release blockers.

The absent local sendmail binary generated warnings, not a failed test. A cleanup announcement was observed, not an independent filesystem/DB cleanup audit.

## R08.3 next gate

A separate opt-in source audit at `tests/r08/https-security-source-contract.cjs` now inspects the real pinned Secure Share checkout and Notes provider. It checks source invariants for mandatory HTTPS, fragment removal, CSRF-like exchange intent, session cookie scope and flags, password and access revalidation, CSP/cache headers and Notes grant state. This is **source verification only** and must never be reported as an executed HTTPS browser test.

```bash
cd ~/Downloads/wp-core-blueprint-notes
CB_NOTES_SECURE_SHARE_REPO="$HOME/Downloads/wp-core-blueprint-secure-share-r08" \
  node tests/r08/https-security-source-contract.cjs
```

After source acceptance, run a separate, isolated TLS-enabled, synthetic-data HTTPS origin scenario with real browser interaction. Do not use customer data or deploy to a live site. Preserve privacy-sensitive token material outside logs, GitHub and screenshots.

No merge, deploy, CI, DNS, customer grants or product changes were authorized or performed in this evidence update.
