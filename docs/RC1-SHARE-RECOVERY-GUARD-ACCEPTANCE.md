# Notes Alpha25 | Public Share Recovery Guard

**Status: FEATURE BRANCH IMPLEMENTATION, NOT YET RUNTIME ACCEPTED (2026-10-14).**

This is the operator and QA guide for the explicitly approved Share Recovery Guard v1 implementation. **Do not deploy this candidate to production or upgrade real customers until the security and migration gates are accepted.** Legacy Alpha24 Public share URLs become unusable by design.

## What changes for users

- Private Notes, native Gutenberg, notebooks, categories, labels, personal data and optional Secure Share functionality are independent of the Public key.
- An optional **Public** share can be issued only when the installation has a valid host-provisioned `CB_NOTES_SHARE_EPOCH_KEY`.
- A new frozen Public snapshot is HMAC-SHA-256 authenticated over its token, note ID, title/content hash, timestamp and WordPress home URL using a versioned format.
- Legacy unsigned Alpha24 Public shares and signatures from earlier keys/home URLs return 404. The editor hides their obsolete Public URL, explains the problem, and permits **Stop sharing**. Old URLs are not automatically reissued.
- A misconfigured/missing key causes a clear REST 503 on attempts to create a Public share and disables the Gutenberg Public action. It **does not** make the underlying private Notes visible or erase Note content. Revocation remains possible.

## Provision the key (operator, not end user)

Provision a **unique 32-byte cryptographically random secret represented by 64 lowercase hex characters**, separate for each WordPress installation. The only supported public-sharing key constant is:

`CB_NOTES_SHARE_EPOCH_KEY`

It must be defined **before WordPress/plugin bootstrap**, typically in a protected `wp-config.php` integration with hosting-provided environment/secret configuration. For example, when the host provides a securely managed `CB_NOTES_SHARE_EPOCH_KEY` environment value, the operator can configure a guarded mapping:

```php
$key = getenv('CB_NOTES_SHARE_EPOCH_KEY');
if (is_string($key) && preg_match('/^[a-f0-9]{64}$/D', $key)) {
    define('CB_NOTES_SHARE_EPOCH_KEY', $key);
}
```

Never hardcode a sample/shared secret from documentation, WordPress options, a theme, a public plugin repository, a log, a URL or an AI prompt. Use your hosting secret controls and appropriate filesystem access restrictions. Missing/malformed host secrets keep Public sharing **disabled**.

The key is **not** created or saved by Notes; doing that inside a WordPress SQL option would leave rollback vulnerability unresolved. If the hosting backup includes `wp-config.php` or the host secret itself, restoring that same old material also rolls back the guard. In that case enforce external traffic quarantine and rotate to an entirely new key **after the full restore and before opening public traffic**. Host access controls must block origin bypass, not just WordPress pages; purge any applicable LiteSpeed/QUIC.cloud caches.

The current implementation does **not** automatically detect database rollback, does **not** manage or store keys in WordPress Admin, and does **not** revoke independent grants held by a real Secure Share provider. CDN, full-host rollback and Secure Share remain separate RC1 gates.

## Upgrade from Alpha24

**Breaking security behavior:** existing Alpha24 Public frozen snapshots are unsigned. Upon upgrading to Alpha25 they are rejected, even if a correct key is provisioned. Existing private note contents and history remain intact. Operators must inform customers before an upgrade and guide note owners to stop old shares and deliberately reissue fresh links after configuration. Do not simply make all old links work again: that would reintroduce the confirmed P0 hazard.

Before a production upgrade, also establish a process to notify impacted note owners and purge any external cached copies. Links copied or cached outside the site cannot be retroactively erased.

## Negative test sequence

The new `tools/check-rollback-guard` runner creates **two disposable WordPress 7.0 installations with the identical home URL**, separately configured with synthetic epoch E1 (source) and E2 (target). It builds `dist/core-blueprint-notes-0.1.0-alpha25.zip` from the current featurebranch and its current `tools/check` source gates.

1. Seed a private synthetic Gutenberg note and explicitly grant a signed Public E1 snapshot.
2. Save full synthetic SQL backup **while Public is active**.
3. Revoke the key in source E1.
4. Restore the old active-grant SQL backup into target E2, with the same home URL.
5. Assert target still stores the *historical* Public metadata and private content but refuses authorization under E2; owner REST must not advertise the invalid URL.
6. Start a loopback-only HTTP origin and require anonymous 404 for the old key.
7. As owner, explicitly stop sharing and issue a new E2-signed Public snapshot. Require old key 404, new key 200 over the same HTTP origin.
8. Shut down only the owned loopback server and remove only the two fixed disposable SQL databases and private fixture files.

The test does **not** alter any production data, hosting secrets, Base, or an actual third-party Secure Share provider. It is a narrow DB-only rollback with an independent target epoch, not a full-hosting rollback with old PHP/key material restored. A status from a source-only Node contract does not count as this test's runtime result.

```bash
cd ~/Downloads/wp-core-blueprint-notes
bash tools/check
bash tools/build-release
sha256sum dist/core-blueprint-notes-0.1.0-alpha25.zip
bash tools/check-rollback-guard
```

Expected **only if the entire runtime test succeeds**:

`Notes historical share recovery guard: PASS (old HTTP 404; reissued HTTP 200)`

## RC1 release decision

Even if the Alpha25 runner passes, RC1 remains open until at least WP 7.0 and 7.1.3 real integration, standard SQL backup/restore on the new artifact, external cache/CDN, real Secure Share absent/present tests, manual R01–R13, six locale/a11y checks, and a two-build deterministic artifact comparison are recorded. **No merge or deployment without separate explicit GO.**
