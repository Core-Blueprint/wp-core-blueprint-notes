# Core Blueprint Notes alpha3 — Visibility & Sharing acceptance

**Candidate:** 0.1.0-alpha3 on `feature/notes-visibility-sharing-alpha3-v1`.
**Environment:** disposable WordPress staging, PHP 8.4+, WordPress 7.0+, compatible Core Blueprint Base API 1.2.
**Governance:** test candidate only. Do not install on production or use genuine confidential notes, passwords or files until all privacy and cache tests have passed. No merge, CI/CD or release without explicit GO.

## 0. Source gate and package

From `~/Downloads/wp-core-blueprint-notes`:

```bash
git fetch origin
git switch feature/notes-visibility-sharing-alpha3-v1 || git switch --track origin/feature/notes-visibility-sharing-alpha3-v1
git pull --ff-only
git status --short
bash tools/check
bash tools/build-release
unzip -t dist/core-blueprint-notes-0.1.0-alpha3.zip
sha256sum dist/core-blueprint-notes-0.1.0-alpha3.zip
git rev-parse HEAD
```

Upload the package to staging, replacing alpha2. `dist/` is local build output, not tracked in the plugin.

## 1. Core privacy and migration

1. As administrator, visit wp-admin after updating; this reconciles share rewrite rules and migrates legacy Notes with `post_status=publish` into `private` in batches of up to 25 per administrator page load.
2. Confirm an alpha2 Note previously displayed Published is still editable but is now internally private. Make a new Note, save via Gutenberg and verify normal authoring does not create a public page or public REST access.
3. In a logged-out browser, request `/wp-json/wp/v2/cb-notes/ID`, `?context=edit`, revisions and autosaves: none may reveal note metadata or content. Also test collection search and WordPress search, feed, sitemap, embeds and REST search.
4. As Editor B, attempt to read Administrator A's Note directly via REST, edit URL and admin list/search. Deny all. As Administrator A, check Base's explicit management override works as documented, but AI still may read only the connected user's own notes.
5. Do not use attachments, external embeds or dynamic/custom blocks in sharing tests. They are intentionally unsupported in alpha3.

## 2. Gutenberg UX

1. Open an existing Note with paragraphs, heading, list and table. The right sidebar should show **Notebook** with field **Name** only once.
2. A new **Sharing** panel appears with the mode **Private**. WordPress Save/Publish must never automatically make it public. Ensure keyboard navigation and native Base modal styling work in light/dark and narrow layouts.
3. Change the Note without saving and confirm `Make public` is unavailable; save and reload first.

## 3. Public Share

1. Select **Make public…**. Verify the *real Base Modal Foundation* opens with **danger** confirmation, `PUBLIC` typed gate and an initially unchecked acknowledgement checkbox.
2. Cancel and verify there is no public URL. Try only one gate satisfied: Make public must remain unavailable.
3. Complete both gates and confirm. Copy the generated `/cb-notes/shared/<key>/` URL, open it as a logged-out visitor; see exactly the frozen allowed text and no private Admin UI, sidebar, Gutenberg controls or scripts.
4. Inspect headers: `Cache-Control` must disallow storage, `X-Robots-Tag` must include `noindex`, CSP must be restrictive. Test with and without LiteSpeed/CDN/reverse cache as relevant.
5. Edit the original Note after sharing, save, and refresh the public URL. **The published copy must remain unchanged** until explicitly reshared.
6. Click Stop sharing, confirm, then revisit the old URL. It must return 404. Share again with a new key, trash the Note, and confirm the URL stops. Restore the Note: old URL must **stay revoked**.
7. Unauthorized users must not be able to access `/wp-json/core-blueprint-notes/v1/sharing/ID` (GET/POST) for another user's Note. Conflicting saved content and stale sharing-tab versions must return 409 instead of exposing stale information.

## 4. Secure Share optional adapter

If compatible Secure Share is installed and booted on staging:

1. In the **Sharing** panel, create a secure link with a supported 1-, 7- or 30-day expiry and optionally a password/PIN. Respect Secure Share's own password policy.
2. The invitation URL must be displayed once. Test recipient access in a clean browser through the authentic Secure Share session and password challenge.
3. Verify direct Notes REST and normal WordPress URLs stay private, while the frozen secure snapshot is delivered only under a valid Secure Share session.
4. Edit the Note; the secure snapshot must not change. Stop sharing; the old secure URL must be denied. Also test revocation and expiration from Secure Share itself.
5. With Secure Share deactivated, Notes must remain usable and Secure sharing unavailable, not silently replaced by a weaker unauthenticated link. Re-enable and verify old revoked links do not reactivate.
6. If the provider isn't detected, capture a sanitized log and stop the Secure Share test; do not work around it by making Notes publicly queryable.

## 5. Negative / failure scenarios

- Expired, revoked, missing or incorrect Secure Share credentials fail closed.
- An unsupported Gutenberg block, embedded image, dynamic shortcode, oversized content or malformed title must never become part of a newly shared snapshot.
- Public sharing without explicit confirmation fails server-side; stale content/version and altered sharing mode also fail.
- If Base Modal Foundation is unavailable, **no Public Share action executes**.
- A failed Secure Share grant issuance must not expose content or leave a working public fallback.
- Sharing remains disabled for users lacking note ownership or the appropriate Notes sharing capability.
- Emergency revocation must work even after a note has changed, without requiring its stale sharing version to match.

## Evidence for acceptance

- Terminal output of `bash tools/check`, build, ZIP integrity, SHA-256, branch HEAD and `git status --short`.
- Screenshots: Notebook/Name, Sharing in Private mode, danger confirmation gate, public link, secure link panel, revoked status, and Core Blueprint Settings Hub.
- Redacted HTTP statuses and response headers for public, unauthorized, restored/trash and secure links. Do not paste shared bearer tokens, passwords/PIN or sensitive note bodies.
- Any PHP warnings/fatals from `wp-content/debug.log`, network failures or console errors, sanitized.
- A two-account privacy matrix (Administrator A / Editor B / anonymous), including REST revisions and autosaves.

**Stop criteria:** any private data visible without explicit authorization, public link surviving revocation, trash/restore resurrecting a link, or a Base modal failure allowing publication. Do not call alpha3 Golden before all runtime tests and user acceptance.
