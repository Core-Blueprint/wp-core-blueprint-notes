# Notes RC1 — implemented architecture and data lifecycle

**Status:** source-backed RC1 hardening inventory, 2026-10-14. NOT runtime-certified.  
**Candidate branch:** `feature/notes-rc1-golden-hardening-v1` (Alpha24; source tree not yet frozen).

This is the architecture **actually implemented**, distinct from the historical `ARCHITECTURE-V1-RFC.md` *proposal*. That original RFC's hierarchical `cb_notebook` taxonomy and export interface were **not shipped**.

## Canonical records and boundaries

| Data | Storage | Authority | Release portability |
|---|---|---|---|
| Note title, Gutenberg blocks, author, revision, autosave and status | WordPress `wp_posts`, `cb_note` private CPT, core revisions | WordPress + `CB\Notes\Access` | Full database backup; note-only WXR behavior must be tested |
| Notebook/category/tag definitions | Per-user `wp_usermeta`, `cb_notes_personal_catalog_v1`; opaque 32-char identifiers | `CB\Notes\Organization\Catalog`, current owner | **Not reconstructible from a note-only WXR export** |
| A note's notebook / category / up to twelve tags | `wp_postmeta` IDs `cb_notes_notebook_id`, `cb_notes_category_id`, `cb_notes_tag_ids` | Notes ownership + owner catalog validation | IDs have meaning only when the owner catalog and author mapping are restored together |
| Old text notebook labels | Legacy `cb_notes_notebook` post meta | Owner-scoped bounded migration | Migration must be tested using disposable alpha1/2 data |
| Favorites / pins | Protected post meta `_cb_notes_favorite`, `_cb_notes_pinned` | Owner-only mutation checks, outside REST projection | Optional UX preference, not sharing authority |
| List/Table user display preference | User meta under Notes Display service | Authenticated owner/capability | Not a record or sharing grant |
| Sharing and frozen copy | Note-owned protected post meta: share mode, public snapshot/key, secure snapshot provider grant | Notes `Sharing\Controller`; `SecureProvider` optional | Never package live bearer tokens, grants, active share URLs or snapshots as general-purpose portable exports |
| Base governance events | Base EventRegistry/Audit | Base | No note bodies, password/PIN or bearer secrets in audit payloads |

There is **no global `cb_notebook` / `cb_note_tag` taxonomy** and no proprietary Notes data tables.

## Life cycle

1. Creation and revisions use Gutenberg's native WordPress CPT. Native Publish does **not** change the private Notes authorization policy. All note reads and list queries remain owner-gated except the explicitly authorized `cb_notes_manage_all` override.
2. Personal catalog create/rename/delete modifies owner-specific `user_meta`. Removing a label first attempts a bounded (<=250) cleanup of matching owner notes, including Trash; beyond the bound it refuses. **Alpha24 hardening:** the bounded cleanup first checks every matching owner note, then applies exact changes. It attempts compensation in reverse order if a later post-meta write or final catalog user-meta save fails. Added source smoke cases inject failures after the first write and at catalog save. **Remaining runtime risk:** WordPress does not provide an atomic post-meta transaction here; concurrent edits, compensation failure, real database failures and restore/backup recovery still require acceptance. A compensation failure surfaces an explicit recovery error instead of claiming that cleanup succeeded.
3. Favorites/Pins are independent preferences and never grant access or change the frozen shared snapshot.
4. Sharing a Note is an explicit author-only operation, distinct from Gutenberg Save. The copied content is an allowlisted frozen snapshot. Public requires a typed, untranslated `PUBLIC` acknowledgment and Base danger confirmation. Secure Share is optional and must be tested with the actual provider.
5. Trash and permanent deletion invoke WordPress lifecycle hooks and revoke Notes-managed public keys and secure grant metadata. **Must be tested:** both normal and bulk Trash, restored notes, delayed caches/CDNs and provider revoke failures. Never claim completed revocation based solely on unit stubs.
6. Deactivation must preserve content and private catalogs. There is no shipped `uninstall.php`; a destructive uninstall routine must **not** be added without a separate data-retention decision. Document WordPress plugin removal behavior without promising complete erasure.

## Export and recovery: explicit RC1 contract decision

**Current supported recovery claim, pending real restore acceptance:** a complete, appropriately access-controlled WordPress database backup including `wp_posts`, revisions, `wp_postmeta`, `wp_users`, and `wp_usermeta` can preserve the Notes data graph when restored with a consistent user/author mapping and Base. Verify using a disposable restore, not a post-only WXR export.

**Not supported or claimed for RC1 without a new approved scope and round-trip test:**
- Independent user-facing Notebook/Note export/import with stable ID remapping.
- Portable labels reconstructed from ordinary WXR or content-only JSON; category and tag names live outside those Notes.
- Private attachment backups (attachments are not shipped as a managed Notes feature).
- Bulk account-to-account transfer of private Notes; author remapping is a privacy operation and must be authorized separately.

**Decision needed before actual RC1:** product owner confirms a documented full-site backup/restore workflow as RC1 minimum, or explicitly authorizes an additional protected export capability. Existing README defers independent export/import; historical RFC's proposed export was not delivered.

## Localization and content safety

The candidate's five JS entrypoints use literal WordPress gettext keys, formatting and plural calls. Each enqueue declares `wp-i18n` and registers the `core-blueprint-notes` translation domain. JS fallback English in isolated tests does not mean translations are supplied. **Language catalogs are not yet verified or bundled.** EN source with NL/DE/FR/ES/IT/PT targets follows Suite RC fallback policy; real language-pack, script JSON and placeholder tests remain release evidence.

## Security/release acceptance remains open

Follow `RC1-GOLDEN-HARDENING-ACCEPTANCE.md` and the Overseer RC-01...RC-05 matrix. A source check or mock does **not** prove WordPress multi-user access, REST/media/revisions, anonymous behavior, Secure Share delivery, public cache exclusions, restore or production readiness.
