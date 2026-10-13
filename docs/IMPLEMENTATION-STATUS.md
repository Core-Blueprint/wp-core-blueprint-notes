# Core Blueprint Notes — current implementation status

**Current test candidate:** 0.1.0-alpha9.
**Branch:** `feature/notes-gutenberg-ux-g1-v1`.
**Status:** G1–G2 implemented; local sourcegate/build and new Gutenberg runtime acceptance pending. No Golden or production claim.

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
- PHP/REST storage, authorization, frozen snapshots, Base and Secure Share code have not been modified in Alpha9.

## Ownership boundaries

- Base (mandatory) continues to own Settings Hub, UI Modal Foundation, capabilities/governance infrastructure; this branch does not modify Base.
- Secure Share (optional) continues to own grant and secure transport; this branch does not modify Secure Share.
- Notes owns classification, author isolation, shared snapshot projection and private/public mode authority.
- Team notebooks, hierarchy and shared classification catalogs require separate design acceptance.

## Validation gates

For alpha9, run the canonical sourcegate and release ZIP build on the exact branch HEAD and record SHA-256. Acceptance focuses on the two Gutenberg panel changes, including inline catalog CRUD, owner-bound meta, unsaved edit handling, private sharing and responsive/accessibility behaviors. Earlier Alpha8 was reported stable by the user; the new alpha9 interface still requires its own staging acceptance. Mocked source checks do not replace WordPress runtime proof.

[Unified alpha4 acceptance](ALPHA4-UNIFIED-ACCEPTANCE.md).

No merge, CI/CD, production installation or WordPress.org submission without explicit GO.
