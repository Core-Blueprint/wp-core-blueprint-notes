# Core Blueprint Notes — Professional Productivity P1 acceptance

**Version:** 0.1.0-alpha12
**Branch:** feature/notes-productivity-p1-favorites-pins-v1
**Scope:** Favorites and Pins, independent quick actions in Library, saved views, pinned-first default order.
**Status:** staging candidate. No Golden claim or deployment without explicit GO.

## Sourcegate and release

    cd ~/Downloads/wp-core-blueprint-notes
    git fetch origin --prune
    git switch feature/notes-productivity-p1-favorites-pins-v1 || git switch --track origin/feature/notes-productivity-p1-favorites-pins-v1
    git pull --ff-only
    git rev-parse HEAD
    php tests/library-markers-smoke.php
    php tests/library-query-smoke.php
    node tests/library-markers-interaction.cjs
    bash tools/check
    bash tools/build-release
    unzip -t dist/core-blueprint-notes-0.1.0-alpha12.zip
    sha256sum dist/core-blueprint-notes-0.1.0-alpha12.zip
    git status --short

## WordPress staging acceptance

1. Install alpha10 on disposable staging. Previously accepted Library and Gutenberg editing must remain operational; no notes, revisions, notebooks, tags or share states may be changed by the upgrade.
2. In Notes → Library, each personal note displays four equally sized Base icon-only actions in order: Star (Favorite), Pin, Pencil (Edit) and Trash (Move to Trash). Edit and Trash have accessible labels and titles describing the exact note. Trash still opens the Base danger-confirmation modal, and Cancel leaves the note unchanged. Actions are compact Base controls, keyboard accessible, with visible active states, proper tooltips and aria-pressed. The star and pin glyphs must be visually centered, not hanging below their buttons, at normal zoom and 200% zoom in Light and Dark Mode. No JavaScript means no action is offered.
3. Click Star on a note in All Notes. It should turn active without a page reload, and the note appears in Favorites. Unstar it: pin state must not change. Test browser refresh and reopening Gutenberg to confirm persistence.
4. Click Pin on a different note. The Library recalculates page ordering and displays this note first on the default Recently modified sort. Unpin it: natural modified sorting returns. Pinning does not automatically Favorite a note.
5. Open Favorites, Pinned, All Notes and a Notebook. Check active sidebar state, URL deep links, search/filter combinations, page counts and empty-state copy. On Favorites/Pinned, removing the active marker refreshes the result. Explicit Title A–Z and Least recently modified sorts override pin priority.
6. Try two note authors. An Editor or Administrator must never read, see counts for, or toggle another author's marker from the personal Library. Test an AJAX request with a copied foreign note ID, then an invalid nonce: expect authorization errors, no mutation or data disclosure.
7. Trash and restore a starred/pinned test note using the established Base danger confirmation. Trashed notes do not appear in Library views. A restored personal note can retain its personal markers; existing shared links must remain revoked under the earlier Share lifecycle contract.
8. Confirm private sharing snapshots do not include Favorite/Pin flags, notebooks, categories, tags, or any marker actions. The existing Public and optional Secure Share flows must remain unchanged.
9. Check 20+ notes with two pins across pagination, responsive mobile layout, light/dark themes, keyboard focus and 200% zoom. The P1 SQL sort must not alter any other WordPress post/CPT list.
10. Simulate a failed AJAX response or temporary network loss and confirm the marker does not change visually. Feedback is announced; disabled controls recover for retry.

## Architecture and governance

The flags are stored as protected Notes post metadata: _cb_notes_favorite, _cb_notes_pinned (value '1' or absent). The AJAX handler checks a per-note WordPress nonce, Notes edit capability, the original note author and object edit permission. These flags are not registered with Gutenberg REST and are never copied into shared snapshots. No user-level catalog, Base, Secure Share or Gutenberg content changes.

P1 is intentionally small. Bulk editing, keyboard command palettes and hierarchical notebooks remain later decisions. No merge, CI/CD, production upload or WordPress.org publication without a separate GO.
