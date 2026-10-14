# Notes Alpha25 | Operator acceptance evidence, 2026-10-14

**Evidence status:** actual local operator CLI output verified in conversation. Replaces earlier "NOT YET RUNTIME ACCEPTED" labels **only within the narrower tested scopes below**. This evidence file is under `tests/` and is intentionally omitted from the release ZIP so the already-verified artifact checksum is not invalidated by documentation changes.

## Exact source + release artifact

- Repository: `christiaanbruinsma/wp-core-blueprint-notes`
- Branch: `feature/notes-rc1-golden-hardening-v1`
- Tested source HEAD: `8995238745d6ae3e6a9d7feda2c885023e358871`
- Base source HEAD recorded by WordPress suite: `898fe9f7a1b15f5c6aec02ceb283f56051f8ca81`
- Version: `0.1.0-alpha25` (development candidate, **not** v1.0.0-rc1)
- Artifact: `dist/core-blueprint-notes-0.1.0-alpha25.zip`
- ZIP SHA-256: `77035d6ba1c5e352d97f917eb8a4ddcaca09af3b36db155bbb2ab4303610e908`
- Two independently invoked `bash tools/build-release` runs produced the **same SHA-256**; `diff -u` was empty; `unzip -t` reported no archive errors.
- A previous Alpha24 artifact remains separately pinned for the historic vulnerability reproduction: `22665ea1ca9445f1cd4092f0738e1965fab720b8a6e406c582c6b3f8d09c6dfc`.

## Reported tests and observed output

| Gate | Observed evidence | Scope/limitations |
|---|---|---|
| PHP source lint, JS and UI contracts | `Notes source checks: PASS`; independent HMAC contract PASS; rollback recovery runner source contract PASS | Source safety, not a substitute for runtime |
| WordPress 7.0 integration | `OK (9 tests, 221 assertions)` and `Core Blueprint Notes Level 2 integration: PASS (WP 7.0)` | WordPress test DB, Base integration, not CDN |
| WordPress 7.1.3 integration | `OK (9 tests, 221 assertions)` and `Core Blueprint Notes Level 2 integration: PASS (WP 7.1.3)` | WordPress test DB, Base integration, not CDN |
| Missing recovery key, new E1 signature, E2 rotation, cloned home URL, unsigned Alpha24, tampered title/content | All 8 independent PHP/HMAC checks PASS | Synthetic keys, no real secrets |
| Deterministic Alpha25 package | 2 ZIP builds same SHA `77035d...`; `unzip -t` PASS | Fixed source HEAD and development artifact |
| Guarded full SQL rollback + restored-target HTTP | **PASS**, see results below | WordPress 7.0 isolated loopback, synthetic records; no CDN or full filesystem rollback |

### Actual rollback acceptance sequence

The operator ran `bash tools/check-rollback-guard` on the tested source and ZIP.

```text
Notes rollback rehearsal: source active-share seed PASS (7 checks)
Notes rollback rehearsal: source revocation PASS (7 checks)
Notes guarded rollback: restored old Public grant DENIED (9 checks)
Notes rollback rehearsal: original source remains revoked PASS (5 checks)
Notes guarded rollback: safe owner reissue PASS (12 checks)
Notes historical share recovery guard: PASS (old HTTP 404; reissued HTTP 200)
Notes recovery test SQL SHA-256: 0eac7a8cca6f5f9e731a82fcad223ce1d79edd0baf3c1803a86ef43ae359f773
Notes Alpha25 test ZIP SHA-256: 77035d6ba1c5e352d97f917eb8a4ddcaca09af3b36db155bbb2ab4303610e908
CDN, full-host rollback and real Secure Share: NOT RUN
```

The source E1 and restored target E2 used **the same WordPress home URL** and distinct synthetic epoch secrets. Historical Public mode and bearer metadata were still present in the restored target; the authorization predicate denied them due to the rotated secret, and the anonymous loopback origin returned 404. The owner explicitly revoked/reissued a new grant under E2; the new bearer returned HTTP 200.

**Conclusion:** P0 historical active-share SQL-backup rollback is **MITIGATED AND ACCEPTED for the guarded E1/E2 SQL-only restore scenario**. This is **not** a universal guarantee against restoring old PHP files and an old host-side key, old CDN copies, or external Secure Share provider state. The complete RC1 security gate therefore remains open.

## Still open before RC1 promotion

- The existing `tools/check-restore` (76-check ordinary SQL backup/restore) and `tools/check-http` (44-check A/B HTTP/REST/headers) remain **hard-pinned to Alpha24**, not Alpha25. Their earlier PASS results are historical evidence and **do not establish Alpha25 regressions**. Either add independently guarded Alpha25 copies or safely retarget these runners while preserving the original pinned baseline evidence. **NOT RUN on Alpha25.**
- Actual LiteSpeed / QUIC.cloud origin vs edge delivery, purge, 404-after-revocation and no-store headers: **NOT RUN**.
- Independent host-level quarantine, config/file/SQL full rollback and post-restore key rotation: **NOT RUN**.
- Real Secure Share provider (installed/absent), password/expiry, provider grant revoke/rollback: **NOT RUN**.
- Upgrade/legacy Alpha24 link migration communication, WordPress site configuration with independently protected key: **NOT RUN**.
- R01–R13 complete manual matrix, six-language gettext extraction and locale output, accessibility, performance/scale, activation/uninstall and portability: **NOT RUN or partial as separately indicated**.
- RC1 final versioned build and explicit operator acceptance, source merge, CI/distribution and deploy: **NOT AUTHORIZED**.

## Governance

This is a source/test evidence update only. It does not modify customer links, WordPress live databases, keys, Base, Secure Share, CDN or the packaged Alpha25 artifact. No merge, tag, deployment or publication.
