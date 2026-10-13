# Notes 0.1.0-alpha4 — Unified staging acceptance

**Branch:** \`feature/notes-personal-organization-alpha4-v1\`.
**Scope:** all alpha3 Private/Public/Secure Share behavior **plus** alpha4 personal Notebooks/Categories/Tags and native library organization.
**Environment:** disposable WordPress staging, PHP 8.4+, WordPress 7.0+, compatible Core Blueprint Base API 1.2. Do not test with genuine confidential data.
**Governance:** test only, no merge, CI/CD or production deployment without explicit GO.

## 0. Local sourcegate and ZIP

\`\`\`bash
cd ~/Downloads/wp-core-blueprint-notes
git fetch origin --prune
git switch --track origin/feature/notes-personal-organization-alpha4-v1 || git switch feature/notes-personal-organization-alpha4-v1
git pull --ff-only
git status --short
bash tools/check
bash tools/build-release
unzip -t dist/core-blueprint-notes-0.1.0-alpha4.zip
sha256sum dist/core-blueprint-notes-0.1.0-alpha4.zip
git rev-parse HEAD
\`\`\`

Confirm exact branch HEAD against handover; package root must remain \`core-blueprint-notes/\`. \`dist/\` is untracked local build output. Save checksum and logs. **Do not install if any test fails.**

## 1. Upgrade, legacy Notebook migration and Gutenberg

1. Back up staging. Upload \`dist/core-blueprint-notes-0.1.0-alpha4.zip\` as a replacement for alpha3. Refresh editor assets and open Notes with a test author.
2. The existing Notes editor, private status, Settings Hub and Sharing panel must still work without any visible Secure Share upsell when Secure Share is absent.
3. Open a note with the old text Notebook label \`Projects\`. Confirm the migration creates one private Notebook named Projects and preserves its assignment. Other notes with the same old label reuse that Notebook. A second user with the same label receives a separate private ID.
4. In Gutenberg, find **Notebook (Name)**, **Category (Type)** and **Tags**. Create Notebook \`Clients\`, Category \`Meetings\` and tags \`strategy\` and \`follow-up\`.
5. Assign one Notebook, one Category and both Tags, save and reopen. All assignments must persist. A note without Notebook or Category remains valid ("Unfiled"). Twelve is the maximum number of tags assigned to one note.
6. Verify the editor still supports title, paragraphs, headings, list, table, revisions, autosave and private status. No custom public post permalink should become accessible.

## 2. Personal catalog administration

1. Visit **Notes → Organize**. See personal Notebooks, Categories and Tags with create, rename and remove actions.
2. Rename \`Clients\` to \`Clients & Projects\`. Return to the note: the new name appears without losing the assignment because the internal ID is unchanged.
3. Try to create \`meetings\` when \`Meetings\` already exists. Expect a duplicate error; do not create two case-insensitively identical labels.
4. Remove an unused test tag: the Base danger modal with required acknowledgement must open. Cancel and verify it remains. Confirm removal and verify it disappears.
5. Remove a tag used by a note: the note must remain intact but that tag must be removed from its classification. Repeat with an assigned Category and Notebook; contents and revisions remain untouched.
6. The manager safely rejects deletion of a label used by more than 250 notes rather than performing an unbounded bulk operation. No mass test-data generation is required for normal acceptance.
7. Test keyboard navigation, narrow width and WordPress light/dark presentation. There must be no extra custom CSS framework.

## 3. Notes library filters

1. Open **Notes → All Notes** as Editor A. Confirm columns Notebook, Category and Tags show human-readable labels.
2. Filter by Notebook, then Category, then a Tag. Combined filters should narrow results rather than reveal another user's notes.
3. Text search remains author-scoped. Clearing filters restores the author's normal list.
4. Invalid or manually substituted foreign IDs must not expose another user's notes or metadata. Global status count views must not leak cross-owner totals.

## 4. Mandatory two-user privacy

Create Editor A and Editor B, each with private notes. Run browser sessions separately.

1. B must never see A's notebook/category/tag names, suggestions, options, IDs mapped to readable names, note counts or filtered results. Identically named labels should remain independent.
2. B cannot assign A's opaque catalog IDs through Gutenberg REST, query \`GET /wp-json/core-blueprint-notes/v1/organization\` as A, or change A's items with REST POST. B's own catalog works normally.
3. Test exact Notes REST reads, previews, revisions, autosaves and collections, with B and anonymously. Private content, tags and labels must not leak.
4. Verify the site's authorized administrator can manage Notes content under the existing \`cb_notes_manage_all\` policy but cannot silently relabel another owner's personal organization with the administrator's catalog.
5. **STOP** on any cross-user label, content or count leak.

## 5. Public Share (alpha3 runtime closure)

1. From a test private Note using allowed static text/list/table blocks, click **Make public…**. Verify Base Modal Foundation opens with danger-styled confirm, \`PUBLIC\` typed gate and initially unchecked explicit acknowledgement.
2. Cancel: the Note remains private. Neither one gate alone nor a missing Base modal may create public access.
3. Complete both gates. A separate unlisted \`/cb-notes/shared/<opaque-key>/\` URL is generated. As an anonymous visitor, verify only the **frozen content** is accessible, not Notebook, Category or Tags metadata.
4. Verify anonymous normal WP REST, sitemap, feed, search and ordinary post URL remain private; public snapshot response uses no-store and noindex.
5. Edit original Note after sharing. Public copy must not change. **Stop sharing** must immediately disable the public URL (404).
6. Share again, trash the Note, restore it. Old public URL must **never** resume working.
7. Test supported static-block restrictions: embedded media, custom/dynamic blocks and unsupported HTML must fail safely, not leak.
8. Check LiteSpeed/QUIC.cloud cache bypass/no-store and logged-out fresh sessions; do not rely on noindex as authentication.

## 6. Secure Share optional integration

Test only when a compatible Secure Share development build is installed and approved on disposable staging.

1. Its available UI appears only when its public API is active. Create a frozen view-only link, select expiry, optionally set compliant password/PIN.
2. Verify authenticated recipient session, password gate, expiration, grant revocation and immediate Notes-side **Stop sharing**.
3. Original Notes REST remains owner-scoped. Turning off Secure Share removes the optional UI and must not fall back to an insecure URL.
4. Reactivating an expired or revoked adapter must never reactivate revoked Notes snapshots.

## Evidence and decision

Provide: sourcegate output, build checksum, exact HEAD, screenshots of the 3 Gutenberg panels and Organize UI, Notes list with filtered results, Base danger modal, public snapshot and revoked URL; sanitized HTTP statuses/headers for anonymous and cross-user REST, cache checks and optional Secure Share access. Never post bearer invitations, real passwords, private note bodies or other secrets.

**STOP and report** for plugin activation failure, PHP fatal, data loss, any cross-user organization disclosure, publication without explicit confirmation, revocation failure or unexpected public REST content.

**Release status remains alpha.** Passing source checks alone does not complete security acceptance.
