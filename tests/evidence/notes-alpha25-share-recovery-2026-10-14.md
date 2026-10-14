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

- **2026-10-14 Alpha25 ordinary SQL and HTTP regression: BOTH PASS on real disposable WordPress 7.0.** The earlier Alpha24 fixture version mismatch was corrected, then the operator reran the pinned Alpha25 ZIP with a complete 39-check source fixture and 37-check target fixture plus the 44-check real localhost HTTP origin suite. Detailed evidence below. Historical Alpha24 results and reproduction remain separately recorded.
- Actual LiteSpeed / QUIC.cloud origin vs edge delivery, purge, 404-after-revocation and no-store headers: **NOT RUN**.
- Independent host-level quarantine, config/file/SQL full rollback and post-restore key rotation: **NOT RUN**.
- Real Secure Share provider (installed/absent), password/expiry, provider grant revoke/rollback: **NOT RUN**.
- Upgrade/legacy Alpha24 link migration communication, WordPress site configuration with independently protected key: **NOT RUN**.
- R01–R13 complete manual matrix, six-language gettext extraction and locale output, accessibility, performance/scale, activation/uninstall and portability: **NOT RUN or partial as separately indicated**.
- RC1 final versioned build and explicit operator acceptance, source merge, CI/distribution and deploy: **NOT AUTHORIZED**.


## Alpha25 ordinary restore gate: failed precondition and fixture correction

**2026-10-14 first reported Alpha25 ordinary SQL-restore attempt: BLOCKED before content verification** with
`PHP Fatal error: Uncaught RuntimeException: Restore requires installed Alpha24 Notes.`
at `tests/restore/fixture.php:24` via WP-CLI `eval-file`.
The runner correctly installs the pinned Alpha25 ZIP and uses the two disposable databases, but the still-shared historical fixture erroneously required `CB_NOTES_VERSION === '0.1.0-alpha24'`.
The fixture now strictly requires `0.1.0-alpha25`. The Node source contract pins this version check and the independent test-only epoch configuration. No production PHP or release ZIP files changed. **Ordinary Alpha25 SQL recovery must be rerun; no restore PASS can be claimed from the failing attempt.** Because the user's command joined runners with `&&`, the Alpha25 44-check HTTP-origin rerun was not reached.

## Governance

This is a source/test evidence update only. It does not modify customer links, WordPress live databases, keys, Base, Secure Share, CDN or the packaged Alpha25 artifact. No merge, tag, deployment or publication.

## Next operator-only local regression (as of 2026-10-14)

On clean featurebranch, verify the exact HEAD and run:

```bash
cd ~/Downloads/wp-core-blueprint-notes
git fetch origin
git switch feature/notes-rc1-golden-hardening-v1
git pull --ff-only origin feature/notes-rc1-golden-hardening-v1
git rev-parse HEAD
git status --short
bash -n tools/check-restore
bash -n tools/check-http
node tests/restore-runner-contract.cjs
node tests/http-runner-contract.cjs
bash tools/check
sha256sum dist/core-blueprint-notes-0.1.0-alpha25.zip
bash tools/check-restore
bash tools/check-http
```

Expected current Alpha25 package hash: `77035d6ba1c5e352d97f917eb8a4ddcaca09af3b36db155bbb2ab4303610e908`. **No prior Alpha25 PASS is claimed for these two reruns.** Runners use only their fixed disposable local test DB names, fail if already present, and retain their existing cleanup ownership/loopback safeguards. Preserve full output and stop on any fail. This evidence-only update and runner retargeting leave the frozen package's file contents unchanged.

## Final verified ordinary Alpha25 SQL + HTTP runs (2026-10-14)

Operator execution at Notes source HEAD `8225c773c9db9140f2ddedbf68c05101a847198a` using the same previously certified Alpha25 artifact SHA-256 `77035d6ba1c5e352d97f917eb8a4ddcaca09af3b36db155bbb2ab4303610e908`. Checks before test: `php -l tests/restore/fixture.php` PASS; `node tests/restore-runner-contract.cjs` PASS (static gate); `bash tools/check` ended with `Notes source checks: PASS`; `sha256sum -c` returned `dist/core-blueprint-notes-0.1.0-alpha25.zip: OK`.

### Full SQL backup → distinct WordPress destination

```text
Notes restore source fixture: PASS (39 checks)
Notes restore: dump complete source database and import into separate target
Notes full SQL target verification: PASS (37 checks)
Notes full SQL backup/restore: PASS (disposable source -> target)
Notes restore SQL SHA-256: 0ed3687cec55f477fc88965355c6cbacce7e390bdf94576beba7318adca046b5
Notes restore ZIP SHA-256: 77035d6ba1c5e352d97f917eb8a4ddcaca09af3b36db155bbb2ab4303610e908
Notes restore: cleaning up the two dedicated test databases and temporary files
```

### Actual localhost HTTP/REST delivery and revocation

```text
Notes synthetic HTTP fixture: PASS (seed)
Notes synthetic HTTP fixture: PASS (private edit)
Notes synthetic HTTP fixture: PASS (revoked)
Notes real loopback HTTP origin: PASS (44 checks, WP 7.0)
Notes HTTP origin ZIP SHA-256: 77035d6ba1c5e352d97f917eb8a4ddcaca09af3b36db155bbb2ab4303610e908
Notes HTTP: CDN, public hosting and real Secure Share provider NOT RUN
```

**Acceptance decision:** Source-gated 76-check SQL recovery **PASS** and 44-check actual origin HTTP security **PASS** on WordPress 7.0 + **Alpha25**. The already separate Alpha25 epoch-rotation old-link 404/new-link 200 test and WordPress 7.0/7.1.3 PHPUnit integration PASS remain valid. This does **not** close CDN/QUIC.cloud, browser security, real Secure Share, legacy upgrade UX or manual RC1 matrix.

The `/usr/sbin/sendmail: not found` lines during synthetic `wp core install` were non-fatal; this run did not report independent post-cleanup database inspection. No live site, customer share, or production hosting secret was changed.

**Evidence-only branch updates:** This file and following R08 planning files live under `tests/` and do not alter the locked Alpha25 package contents or its checksum. No merge, release or deployment was authorized.
