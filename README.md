# Core Blueprint Notes

**Version:** \`0.1.0-alpha23\` | **Status:** staging test candidate, not Golden or production-ready.

Core Blueprint Notes is a WordPress-native Gutenberg private knowledge workspace. Core Blueprint Base API 1.2+ is required. Secure Share is an optional integration, not a requirement.

## Current functions

- **Library Table View P2.7 (alpha23):** optional semantic Table mode with Title, Notebook, Category, Tags, Modified and shared Favorite/Pin/Edit/Trash actions. Both modes use the same owner-scoped Library query, filters and 20-per-page bulk selection. User preference persists with a nonce-protected WordPress admin action; List is the default. Title and Modified headers toggle ASC/DESC (A–Z/Z–A and newest/oldest); sorting does not automatically open More filters. Alpha21 separates sort-label underlines from direction arrows and replaces List/Table text links with accessible Base compact icon-only buttons (localized title and aria-label, active primary variant, current-page indicator). Alpha22 keeps each view-toggle border and keyboard focus outline at exactly 1px, removing the extra Base button halo without changing other buttons. Alpha23 gives the Library a quieter sidebar footer: a token-based divider and accessible slider-icon **Organize notes** action without underline, retaining the same admin destination and keyboard focus. The default pinned-first order remains distinct from explicit chronological sorting; mobile Table View scrolls within the Library panel.
- **Library Overview alpha18:** the same owner-scoped Library rows now show a quiet modified/status line and a separate compact classification line with labeled Notebook, optional Category and up to three personal Tags (+N for overflow, with an accessible tooltip). The P2 Action, destination, Apply and Base Danger Trash controls now share a single Base-derived control height and responsive command rail. No new queries, taxonomy, write endpoints or duplicate Library view.
- **Professional Productivity P2:** page-local selection of up to 20 visible notes; streamlined Base bulk toolbar: choose one operation, reveal only its notebook/category destination, then Apply; separate red Move to Trash action. All destinations are explicit, owner-valid and protected by the existing WordPress authorization. The Trash batch requires Base danger confirmation, retains share-link revocation, and reports partial success without an implicit page reload. No tags or hidden cross-page selections.
- **Professional Productivity P1:** independent personal Favorites and Pins. Star and pin quick actions use Base compact icon-only buttons with centered Dashicons and nonce-protected AJAX. Favorites and Pinned have dedicated Library views; pins sort first by default without overriding explicit title/oldest sorts. Protected post-meta flags are not exposed in Gutenberg REST or frozen shared copies.
- **Notes → Library:** primary (top) Notes submenu workspace, New note, newest-first list, server-side title/content search, Notebook and Unfiled navigation, combined Category/Tag filters, sort, bounded pagination, Four equal-size Base icon-only actions: Favorite, Pin, Edit (pencil) and Move to Trash (trash icon). Edit and Trash include per-note accessible labels and native tooltips; Trash uses Base danger tokens for its red outline/icon, hover and keyboard-focus states even alongside WP admin CSS; it still requires the Base danger confirmation. Gutenberg stays the sole editor. Alpha16 removes the duplicate All Notes submenu item whenever Library is registered; WordPress's native list URL remains available to legacy deep links and Gutenberg integrations. If Library is missing, native All Notes remains a safe navigation fallback. The optional AI Access shortcut sits last. The Settings Hub workspace link opens Library.

- Native Gutenberg Notes with revisions, autosaves, strict owner authorization and optional administrator override.
- Personal **Notebooks** (one per Note), **Categories** (one per Note) and **Tags** (up to 12 per Note).
- Owner-private organization catalog stored in **user meta**, with opaque identifiers stored in authorized Note post meta. This is intentionally **not** a global WordPress taxonomy, so term labels, suggestions and filters are never shared by default.
- Gutenberg has one progressive **Organize** document panel: Notebook is immediately visible; Categories and Tags are disclosed on demand and open automatically when already assigned. Users can select existing personal labels or create one contextually. Use **Notes → Organize** for a compact Base-styled organizer with direct Notebooks/Categories/Tags tabs, contextual Add new, inline rename and confirmed removal. Removal cleans up references on up to 250 owned Notes; larger operations are explicitly refused pending a reviewed bulk process. Old Notebook text labels migrate on the owner's admin visits in batches of 30.
- Notes list columns for Notebook, Category and Tags, with combined owner-scoped filters. Internal organization metadata is never added to Public or Secure Share snapshots.
- Private-by-default \`cb_note\` WordPress post type. WordPress's native Publish action does **not** make Notes externally public.
- **Editor Sharing UX:** clearly indicates private versus active public/secure copy; saving the original does not update frozen shared copies. Creating a new share requires a saved note; revocation remains available even with unsaved edits.
- **Public Share:** separate unlisted URL with frozen allowlisted static Gutenberg HTML, explicitly created only through a Base danger modal with typed \`PUBLIC\` and required acknowledgement. Noindex/no-store are not substitutes for authorization.
- **Secure Share:** optional standalone premium-capable provider adapter using Secure Share's public consumer API. If absent, no prompt, warning, upsell or nonfunctional control appears. Secure links support available provider-side protection, expiry and revocation; only frozen view snapshots are delivered.
- Shared links can be revoked. Trash/delete permanently disarms Notes-managed public and secure delivery. The original Note remains private.
- Optional read-only Abilities/MCP integration; AI abilities remain disabled by default and author-scoped.
- Base Settings Hub tile under **Business**; Overview and AI Access settings.

## Privacy boundaries

No global Notes taxonomies or term endpoints are registered. REST organization operations always use the authenticated user's own catalog. A site administrator with \`cb_notes_manage_all\` can manage other authors' Note content under the established policy, but their different personal labels cannot be silently assigned to another author's note.

Tag/media/attachment visibility in third-party blocks is **not** supported for shared snapshots. Dynamic blocks, embeds and media are rejected. Hierarchical notebooks, team notebooks, collaboration, independent export/import and private file attachments remain explicitly out of scope. Core Blueprint Notes never claims secrecy against a server administrator or compromised hosting account.

## Build and staging acceptance

\`\`\`bash
bash tools/check
bash tools/build-release
unzip -t dist/core-blueprint-notes-0.1.0-alpha23.zip
sha256sum dist/core-blueprint-notes-0.1.0-alpha23.zip
\`\`\`

Artifact root: \`core-blueprint-notes/\`. The PHP and JavaScript sourcegate includes P2 bulk owner/nonce/catalog/lifecycle regression and UI interaction checks, Sharing, personal organization, Gutenberg organization and sharing guidance interactions, Library queries, marker security/interactions, menu ordering, contextual empty states and Base-modal interaction checks. This is **source-level validation**, not proof of real WordPress security or cross-user isolation.

For the current Alpha23 Table View and sidebar polish, use [Table View acceptance](docs/NOTES-LIBRARY-TABLE-P27-ALPHA22-ACCEPTANCE.md). For the earlier Alpha18 overview/alignment changes, use [Library Overview acceptance](docs/NOTES-LIBRARY-OVERVIEW-ALPHA18-ACCEPTANCE.md). For earlier Alpha16 navigation/P2 changes, use [Productivity P2 acceptance](docs/NOTES-PRODUCTIVITY-P2-ACCEPTANCE.md). P1 is covered by [Productivity P1 acceptance](docs/NOTES-PRODUCTIVITY-P1-ACCEPTANCE.md). The earlier editor change remains documented in [Gutenberg UX Golden G1–G2 acceptance](docs/GUTENBERG-UX-G1-ACCEPTANCE.md). The previously accepted Library remains covered by the [Notes Library Golden batch staging acceptance](docs/NOTES-LIBRARY-GOLDEN-BATCH-ACCEPTANCE.md) to validate B1–B5 together, including full alpha4 privacy/sharing regression checks. The earlier [alpha4 unified acceptance](docs/ALPHA4-UNIFIED-ACCEPTANCE.md) remains as historical scope/evidence.

**Governance:** no merge, CI/CD, WordPress.org publication or production deployment without separate explicit GO.
