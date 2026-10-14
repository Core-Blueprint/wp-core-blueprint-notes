# Notes RC1 | Historical backup share rollback security decision

**Status: SECURITY POLICY AND PRODUCT DECISION REQUIRED, NOT IMPLEMENTED OR RUN (2026-10-14).**

## Concrete exposure and evidence

The successful WordPress 7.0 SQL restore test took the backup **after** a synthetic Public share was revoked, and the successful 44-check localhost HTTP test revoked a token without rolling back its database. Those results prove revocation on the current DB state, **not revocation persistence through a restore of an older DB snapshot**.

In `src/Sharing/Controller.php`, the public share authorization uses local postmeta: `MODE_META` (`_cb_notes_share_mode`), `PUBLIC_META` (`_cb_notes_public_snapshot`) and `PUBLIC_KEY` (`_cb_notes_public_key`). The public delivery path checks this state. A full restore of an **older database containing an active public snapshot and its key** can therefore cause a previously revoked link to become valid again. A database-local revocation marker from the newer state would also be rolled back. This is a real architectural rollback hazard, but **the historical-backup HTTP replay scenario has not yet been executed**. Treat the risk as a release security blocker until a policy and mitigations have been accepted and tested.

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

## Gate and product decisions

- **OPEN P0/security design:** fail-closed handling of old-backup active sharing keys, including cache and optional real-provider behavior.
- **No false closure:** the 76-check SQL restore PASS and 44-check localhost HTTP PASS retain their status, but neither covers this scenario.
- **No implementation GO:** do not modify user data, live hosting, Base, sharing metadata, CDN or provider, and do not merge or promote to RC1 on the basis of this proposal.
- Product owner must sign off whether RC1 requires an automated rollback-resistant barrier or a documented offline recovery/quarantine/reset workflow and the exact resulting acceptance test. Prefer an actually enforced barrier over a purely documentary warning.
