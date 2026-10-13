# Core Blueprint Notes — current implementation status

**Current test candidate:** 0.1.0-alpha18.
**Branch:** `feature/notes-library-overview-golden-v1`.
**Status:** Alpha18 Library Overview staging candidate. Isolated JS contracts passed; full local sourcegate, build and WordPress Light/Dark/owner runtime acceptance outstanding. Not Golden or production-ready.

## Scope added in alpha4

1. Personal catalog with three classification kinds: one notebook, one category and multiple tags (up to 12). Catalog definitions are private, owner-scoped user meta. Notes store only opaque catalog IDs in authorized post meta.
2. Alpha4 originally exposed separate Gutenberg document panels for all three classification kinds. Alpha9 replaces these with one progressive Organize panel, while preserving the original storage and owner-only REST contracts.
3. Native private **Notes → Organize** page with create, rename and delete using the Base danger confirmation where needed.
4. Notes Admin list shows resolved labels and filters by the owner's private catalog; no global term REST routes or cross-owner autocomplete.
5. Alpha1/2 text Notebook labels migrate to stable catalog IDs in bounded author-only batches. Label removal cleans up references on at most 250 owned Notes per action, otherwise fails without an unbounded operation.
6. Alpha4 security contracts and PHP/Node smoke tests added to the standard \`tools/check\` gate. Existing alpha3 private/Public/Secure Share behavior remains.

## Organize UI Golden polish — tabbed organizer (runtime acceptance open)

- **Notes → Organize** is one compact, accessible Base-managed interface. Notebooks, Categories and Tags are direct WordPress-style tabs using the canonical Base `nav-tabs` component.
- Switching tabs updates the visible list immediately without a full page reload. The `section` URL parameter supports deep linking and preserves the Notes admin URL.
- A contextual **Add new** button reveals the create form in the active section. Rename is inline. Remove still uses the Base danger confirmation with acknowledgement; cancellation performs no mutation.
- Success is a compact screenreader-announced status line instead of the former oversized green notice. Errors use Base's semantic `notices` styling.
- Base `AdminTheme::register_screen()` declares `nav-tabs`, `notices`, cards, fields, buttons, form-controls and panels. The now-unused Select Picker and its overflow exception have been removed from this screen.
- Color and spacing remain controlled by Base tokens. No Base repository changes, datamodel migrations or Secure Share changes were made.
- Canonical `tools/check` runs a mocked JavaScript interaction smoke for tabs and CRUD, plus existing organization/privacy/sharing regressions.
- Acceptance requires real WordPress testing of tab switching, direct section URLs, keyboard/focus, dark/light themes, Add new/Rename/Remove, and Gutenberg/list privacy regressions.

## Library and Gutenberg developments

- **Alpha5–8 Notes Library**: personal workspace, notebook navigation, search, classification filters, pagination, context-aware empty states, Base danger confirmation and Base compact action buttons. The user reported visual and functional stability before G1 development.
- **Alpha9 Gutenberg G1**: one Organize document panel; Notebook is immediately visible; Category and Tags behind progressive disclosure, automatically expanded when classifications already exist. Inline personal-label creation and the twelve-tag limit remain.
- **Alpha9 Gutenberg G2**: an explicit Sharing status; separate Save/Share guidance; explanation that shared snapshots do not change when saving the private original. New sharing is blocked for unsaved edits; revocation remains available.
- PHP/REST storage, authorization, frozen snapshots, Base and Secure Share code were not modified in Alpha9.
- **Alpha10–13 P1**: owner-authorized Favorite and Pinned protected post-meta flags, AJAX marker toggles, separate saved views and pinned-first default ordering. Alpha11 centers the Star/Pin glyphs without overriding Base sizes. Alpha12 makes Edit and Trash icon-only buttons with localized per-note tooltips and accessible labels, while preserving the Base confirmation requirement and native WordPress trash route. Alpha13 ensures the Base danger color/focus semantics survive WordPress admin button cascade in the Library; no local color palette introduced. Gutenberg content and frozen public/secure snapshots remain unchanged.

## P2 Professional Productivity (Alpha14–15)

- **Alpha16 menu consolidation:** hide only the duplicate All Notes submenu entry when Library is present; preserve the underlying native WordPress list endpoint, Gutenberg/CPT registration, role capabilities, and unfamiliar third-party submenu entries. Restore the native menu fallback if Library is absent. The Notes parent targets the first visible Library entry.
- Adds checkbox selection of the visible Library page (maximum 20 rows) and a Base-themed contextual toolbar. Alpha15 replaces two simultaneous forms with one Action selector, conditional destination field and Apply; the independent red Trash action remains Base-confirmed. Selection never crosses search, notebook, or pagination boundaries.
- Owner-only AJAX action `cb_notes_bulk`: per-note ownership and edit/delete permissions, per-user catalog destination validation, explicit nonce, strict bounded distinct note IDs.
- Independently apply notebook moves, category changes, or `wp_trash_post`. No bulk tags or irreversible deletion. Trash invokes existing Notes share-link revocation lifecycle hooks.
- Response returns exact updated/failed IDs and counts, including partial failures; client identifies failed visible note names with safe text content and offers manual refresh to maintain correct server-side filters.
- No changes to Secure Share, frozen snapshots, Gutenberg storage or Base design foundations.

## Library Overview & P2 Toolbar Alignment (Alpha17)

- Retains the single owner-scoped Library, sidebar, saved views, filters, 20-per-page query, native Gutenberg editing and four compact row actions.
- Alpha18 removes the inherited bottom margin from the selectable Library result heading only, vertically centering the `Your notes` title with its checkbox. Sidebar headings keep their spacing.
- Separates timestamp/status from classification metadata; displays an explicit Notebook label with Unfiled fallback, Category only when assigned, and at most three personal tags plus an accessible +N tooltip for remaining tags. Values resolve exclusively through the owner's existing private catalog and are escaped; no taxonomy, REST or public-share changes.
- The contextual bulk toolbar uses one Base-derived sizing token across Action/destination selects, standard-density Apply and red Trash, inside a responsive one-rail grid. The row-level Favorite/Pin/Edit/Trash buttons remain compact.
- New Library Overview JS contract is included in tools/check. Full local sourcegate and WordPress staging acceptance remain required before Golden.

## Ownership boundaries

- Base (mandatory) continues to own Settings Hub, UI Modal Foundation, capabilities/governance infrastructure; this branch does not modify Base.
- Secure Share (optional) continues to own grant and secure transport; this branch does not modify Secure Share.
- Notes owns classification, author isolation, shared snapshot projection and private/public mode authority.
- Team notebooks, hierarchy and shared classification catalogs require separate design acceptance.

## Validation gates

For alpha17, run the canonical sourcegate and release ZIP build on the exact branch HEAD and record SHA-256. Confirm at least one WordPress runtime test for selecting visible Notes only, author/capability isolation, correct notebook/category destinations, partial failures, Base-confirmed Trash, share revocation and Light/Dark accessibility. Alpha13 P1 was visually and functionally accepted by the user. New P2 code remains a staging candidate until accepted. Mocked source checks do not replace WordPress runtime proof.

[Unified alpha4 acceptance](ALPHA4-UNIFIED-ACCEPTANCE.md).

No merge, CI/CD, production installation or WordPress.org submission without explicit GO.
