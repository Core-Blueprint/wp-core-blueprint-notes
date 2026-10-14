# Notes RC1 | Historical backup share rollback security decision

**Status: RISK REPRODUCED on isolated WordPress 7.0 / Notes Alpha24 (2026-10-14). P0 SECURITY MITIGATION NOT IMPLEMENTED.**

## Concrete exposure and evidence

The successful WordPress 7.0 SQL restore test took the backup **after** a synthetic Public share was revoked, and the successful 44-check localhost HTTP test revoked a token without rolling back its database. Those results prove revocation on the current DB state, **not revocation persistence through a restore of an older DB snapshot**.

In `src/Sharing/Controller.php`, the public share authorization uses local postmeta: `MODE_META` (`_cb_notes_share_mode`), `PUBLIC_META` (`_cb_notes_public_snapshot`) and `PUBLIC_KEY` (`_cb_notes_public_key`). The public delivery path checks this state. A full restore of an **older database containing an active public snapshot and its key** can therefore cause a previously revoked link to become valid again. A database-local revocation marker from the newer state would also be rolled back. This is a **reproduced authorization-level rollback defect**. The old SQL backup replay has now run successfully in isolated WordPress 7.0. A separate HTTP request to the restored target and its CDN has **not** been executed. Treat the risk as a release security blocker until mitigation has been accepted, implemented and tested.

## Reproduction evidence: completed 2026-10-14

The operator executed `bash tools/check-rollback-repro` on `feature/notes-rc1-golden-hardening-v1`, source HEAD `6adffe914b0e1bc57496bdabc519334e463bf78e`, after full `bash tools/check` and rollback fixture source checks PASS.

| Step | Actual result |
|---|---|
| Create synthetic private WordPress note and authorized Public snapshot | `Notes rollback rehearsal: source active-share seed PASS (7 checks)` |
| Store full SQL archive **while the share is active** | Source database exported to private temporary SQL file |
| Revoke the share in the current source database | `Notes rollback rehearsal: source revocation PASS (7 checks)` |
| Import older active-share SQL into a separate WordPress target database | Completed without reported SQL error |
| Evaluate the restored target's `Controller::public_snapshot_allowed()` | `Notes rollback rehearsal: RISK REPRODUCED after old SQL restore (5 checks)` |
| Revalidate source revocation after the target restore | `Notes rollback rehearsal: original source remains revoked PASS (5 checks)` |
| Runner summary | `Notes historical share rollback: RISK REPRODUCED in isolated Alpha24 SQL restore` |

- Source: WordPress 7.0 / synthetic Alpha24 Notes artifact; two prechecked disposable MariaDB databases in local test container
- Backup SQL SHA-256: `84b5ba7da10dc7e5d36c84f2cc4398e26205c75893f34e0c9ef9efbccaebbc39`
- Alpha24 ZIP SHA-256: `22665ea1ca9445f1cd4092f0738e1965fab720b8a6e406c582c6b3f8d09c6dfc`
- Cleanup: runner reached its test database and file cleanup announcement; independent teardown inspection was not reported
- Scope: **the actual WordPress authorization predicate on a restored SQL database**, not an externally served restored HTTP page, CDN cache, complete-hosting restore or Secure Share grant
- Security outcome: reproduction **PASS**, RC1 safety acceptance **FAIL/BLOCKED** until a separate mitigation and negative security test PASS

The 76 SQL source-to-target recovery checks and 44 HTTP loopback checks remain accurate for their previously reported cases and do not imply safety under historical backup replay.

## Minimum safe operator recovery procedure (PROPOSED, not currently automated)

1. **Quarantine first.** Put the restored WordPress site behind maintenance/network access controls that block all unauthenticated requests, including public share routes, direct origin access and CDN edges. Validate controls independently **before importing an older backup**. Do not rely on cache-control response headers alone; stale edge content needs separate invalidation.
2. Restore the complete site/database into the quarantined target, with appropriate Base version, user/author identities and the prior SQL-recovery safeguards.
3. **Fail-closed sharing reset.** Before reopening traffic, use a reviewed Notes recovery command to invalidate **all restored Public sharing keys and snapshots**, set all Notes share modes to private, and invalidate local Secure Share snapshots/grant references. A production command is **not yet implemented**; do not directly run bulk SQL on customer data or assume activating the plugin performs this reset. Preserve private Notes contents.
4. With a real Secure Share provider, independently revoke/disable every potentially restored grant through a provider-supported recovery mechanism before public traffic resumes. Deleting only the local Notes metadata is not sufficient proof of provider-side grant invalidation.
5. Purge/disable relevant LiteSpeed and QUIC.cloud caches; separately prove origin and edge 404 for **previously known synthetic tokens**. Rotate any applicable broader site/operator credentials according to Base recovery policy. Restore trust approvals must not be blindly inherited from another domain.
6. Verify with two owner accounts, unauthorized account, anonymous visitor and repeated HTTP checks. Re-enable public access only after explicit operator signoff, and allow owners to issue **new** links if needed. Record non-sensitive recovery evidence.

This procedure has operational trade-offs: it intentionally invalidates *all* shared links on restore, including ones the user wanted to remain active. It is only a proposed safety-first default and requires a documented product and operator decision.

## Better long-term enforcement

Prefer a **fail-closed restore/recovery sharing barrier whose trust state is not rolled back with the WordPress database**. Possible approaches: an explicit out-of-band deployment/restore epoch in an independently protected host configuration, or a durable revocation/epoch ledger separate from the database snapshot being restored. If the site can restore both the DB and the barrier state from the same old backup, no absolute monotonic-revocation guarantee exists. The solution must remain self-hosted and work on mainstream shared hosting, without external SaaS dependency. Avoid automatic revocation on normal WordPress updates or upgrades.

Keep any recovery token/epoch outside public web paths, version it deliberately, require operator review, and do not accidentally copy/reuse it across different trust domains. Do not log public bearer values.

## Targeted negative test (TO DESIGN AND EXECUTE)

1. Create disposable source WordPress site and synthetic private note A, then explicitly Public-share it.
2. Confirm a synthetic key yields 200; take complete disposable SQL backup **while the share is active**.
3. Revoke the share in the live synthetic source and confirm repeated 404.
4. Restore the **older active-share backup** into a separate, quarantined synthetic WordPress target. No production or staging data, no real token and no CDN changes.
5. Verify the target's public share URL **remains inaccessible throughout recovery**. The test must exercise the actual proposed barrier, not simply omit HTTP requests during the vulnerable interval.
6. Apply the documented recovery reset and verify 404 before unquarantining. With optional Secure Share, verify the provider grant also cannot deliver.
7. Establish exact evidence, compatibility and cleanup. If link delivery becomes 200 at any point after it was revoked, mark **FAIL** and block RC1 until the failure is remediated.

## Isolated reproduction runner (ACTUAL RUNTIME PASS, evidence above)

A separate strictly synthetic runner, `tools/check-rollback-repro`, is now staged on `feature/notes-rc1-golden-hardening-v1`. It does **not** change Notes product code, WordPress production data, Base, the CDN, Secure Share or customer links. It uses two new fixed MariaDB databases (`core_blueprint_notes_rollback_source` and `core_blueprint_notes_rollback_target`) and two separately bootstrapped disposable WordPress 7.0 installations. It refuses existing database names and an incorrect container, branch or checksum; cleanup ownership is acquired only after both test databases have been created. Its synthetic CLI-only configuration cannot serve public HTTP traffic.

This is intentionally a **vulnerability reproduction**, not a remediation or a safe-restore acceptance test. It seeds a synthetic private note and expressly authorizes its Public frozen snapshot, makes a SQL backup **while the share is active**, revokes the key in the current source database, restores the older backup into a separate target and checks whether Alpha24's local `Controller::public_snapshot_allowed()` permits the historic key there. It also confirms that the current source remains revoked. No bearer token is printed, and the SQL archive is held privately outside Git/webroot and removed at test completion.

Run source-only validation first, then the isolated reproduction:

```bash
cd ~/Downloads/wp-core-blueprint-notes
git fetch origin
git switch feature/notes-rc1-golden-hardening-v1
git pull --ff-only origin feature/notes-rc1-golden-hardening-v1
bash -n tools/check-rollback-repro
php -l tests/rollback/wordpress-config.php
php -l tests/rollback/fixture.php
node tests/rollback-runner-contract.cjs
bash tools/check
sha256sum dist/core-blueprint-notes-0.1.0-alpha24.zip
bash tools/check-rollback-repro
```

**Observed:** `Notes historical share rollback: RISK REPRODUCED in isolated Alpha24 SQL restore` (2026-10-14). This is experimental proof of the hazard, **not** evidence of security. The runner still does not test public HTTP, hosting or cache behavior. The Alpha25 featurebranch now contains a fail-closed HMAC Public share guard and a separate `tools/check-rollback-guard` negative acceptance runner. Both require successful actual runtime verification before mitigation can be marked PASS.

## Approved Alpha25 implementation on featurebranch (RUNTIME NOT YET PASS)

See [RC1-RESTORE-SHARING-EPOCH-DESIGN.md](RC1-RESTORE-SHARING-EPOCH-DESIGN.md) for the concrete implementation boundary: an independently provisioned site epoch bound into newly issued Public snapshots, an explicit host-level quarantine and epoch rotation before an old backup is reopened, strict refusal of unsigned Alpha24 legacy grants, and separate real Secure Share provider revocation. The test gate must verify actual anonymous HTTP 404 on the historical backup **after** the protection has been implemented, not only the PHP authorization predicate.

**Security limitation:** no plugin-only SQL or filesystem state can guarantee that revocations survive restoration of that exact old state. The operating procedure and trust anchor have to remain outside the rollback domain; if files and config are also rolled back, re-quarantine and rotate again before any public traffic. The Alpha25 featurebranch now implements signed Public share verification, which will intentionally invalidate unsigned Alpha24 URLs once actually installed; this is **not** a production deployment or a fully accepted recovery flow.

## Gate and product decisions

- **CONFIRMED P0/security blocker:** older-backup restore resurrects authorization of previously revoked Public sharing keys. Fail-closed protection, cache and optional real-provider behavior remain unresolved.
- **No false closure:** the 76-check SQL restore PASS and 44-check localhost HTTP PASS retain their status, but neither covers this scenario.
- **Implementation GO received for Notes featurebranch only:** Alpha25 now stages an HMAC-signed Public recovery guard and a dedicated old-backup HTTP negative runner. Neither a runtime PASS nor production deployment has been authorized; do not alter real site data, hosting keys, Base, CDN or provider. Separate merge/release GO is required.
- Product owner must sign off whether RC1 requires an automated rollback-resistant barrier or a documented offline recovery/quarantine/reset workflow and the exact resulting acceptance test. Prefer an actually enforced barrier over a purely documentary warning.
