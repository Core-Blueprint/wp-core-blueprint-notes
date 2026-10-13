# Core Blueprint Notes Library — Golden batch staging acceptance

**Candidate:** 0.1.0-alpha7  
**Branch:** feature/notes-library-golden-batch-v1  
**Scope:** Library B1–B5 combined; no Base or Secure Share patch.  
**Status:** ready for LOCAL sourcegate and STAGING runtime testing. Do not label Golden or production-ready until test evidence and user acceptance are complete.

## A. Local sourcegate and release build

Run after checking out the feature branch:

    cd ~/Downloads/wp-core-blueprint-notes
    git fetch origin --prune
    git switch --track origin/feature/notes-library-golden-batch-v1
    git pull --ff-only
    git status --short
    git rev-parse HEAD
    bash tools/check
    bash tools/build-release
    unzip -t dist/core-blueprint-notes-0.1.0-alpha7.zip
    sha256sum dist/core-blueprint-notes-0.1.0-alpha7.zip

If the branch already exists locally, use git switch feature/notes-library-golden-batch-v1 instead of git switch --track. Stop if any command fails. The archive must contain core-blueprint-notes/ as its single plugin root. The sourcegate exercises isolated PHP/JS checks; it does not start WordPress or prove runtime security.

## B. Installation

1. Back up disposable staging and use no real confidential content.
2. Install the rebuilt alpha7 ZIP as a replacement for alpha6 or an earlier alpha, on disposable staging only. Core Blueprint Base API 1.2+ must remain active.
3. Open Notes → Library. Confirm it is the first Notes submenu entry, followed by All Notes, Add Note and Organize, with AI Access last for administrators. Confirm Notes → All Notes remains available as the native fallback. The Settings Hub workspace button must open Library.

## C. Library UX (B1–B4)

1. On an empty personal account, Library explains the empty result and allows immediate New note. Create a note in Gutenberg and return to Library.
2. Add Notes A/B/C with different modification times. Confirm newest-first by default and correct modified timestamps.
3. Create two notebooks plus one unfiled note. Select All Notes, each notebook, and Unfiled. Validate that selection and results agree, and deep links retain the current notebook.
4. Search by a unique title or text phrase. Clear search, and confirm the list returns. Confirm unknown search has a useful reset action.
5. Under More filters, combine Category, Tag and notebook. Test sorting by title and oldest first. Refresh and copy a filtered URL into a second browser tab. Verify it reproduces the same result for the SAME logged-in owner.
6. Produce at least 25 test notes. Page forward/back, filter and sort across pages. Verify no duplicates or missing rows.
7. Use Edit on one note. Confirm Gutenberg opens with original blocks, autosaves and revisions intact.
8. Use Move to Trash. Cancel the Base danger modal and verify the note is unchanged. Confirm a second attempt and verify the note disappears; restore through Notes → All Notes → Trash. If JavaScript is disabled, the quick-trash control must not be actionable.
9. Verify empty-state messages for a truly empty Library, an empty Notebook, Unfiled, an unsuccessful search/filter and an out-of-range page. Base modal unavailable must not offer an unconfirmed Trash path.
10. Test keyboard focus, labels, search form, advanced-filter disclosure, navigation aria-current and screen reader announcements.
11. Inspect wide desktop, approximately 1000px, 680px and narrow mobile widths, plus Base Light/Dark Mode including live switching. No horizontal overflow, unreadable controls or styling regressions.

## D. Mandatory privacy and security

Use two independent Editor accounts, A and B, with distinct private notes and equally named personal labels. Also test logged out.

1. Editor A only sees A's notes and catalog in Library. Editor B only sees B's notes/catalog. Even with copied filters or opaque notebook IDs, no other author's titles, counts or classification labels may appear.
2. Admin override must not change the Library's PERSONAL owner scope; normal WordPress Notes list retains the separately authorized admin capabilities.
3. Try a foreign notebook/category/tag ID in the URL; expect no matching results and no foreign label disclosure.
4. Verify original Notes REST endpoints, Gutenberg revisions/autosaves, normal admin search, and abilities remain owner-scoped. Anonymous requests must not reveal content.
5. Re-test Public Share opt-in and revoke including fresh logged-out browser, no-store/noindex headers and LiteSpeed/QUIC caching. Shared snapshots must contain no personal notebook/category/tag labels.
6. If Secure Share is installed on staging, test protected recipient access, expiry and immediate revocation. Absent Secure Share must not display disabled or commercial controls.
7. Confirm no private note becomes public through Gutenberg Publish, Library links, or trash/restore.

**Stop immediately** for cross-user data disclosure, public REST leakage, sharing revocation failure, data loss, activation fatal errors or broken native Gutenberg editing.

## E. Performance, evidence and acceptance

- Try 100–300 disposable notes divided across notebooks. Evaluate query timings and rendered page size using available profiler/browser tools. No objective performance limit is claimed until measured.
- Record exact HEAD, output of tools/check, ZIP integrity/checksum, desktop/mobile and both theme screenshots, navigation/search/filter results, two-user authorization evidence (sanitized), share-revocation HTTP behavior and any JS/PHP errors.
- Alpha7 adds one integrated Notes Library UX-polish acceptance: Base danger modal, compact list, contextual empty states. No intermediate build required. The WordPress CPT parent route remains native for compatibility; the Library is the first submenu entry.
- Open follow-ups: full alpha4 two-user REST and sharing closure where not yet proven. These remain release blockers even if the Library looks stable.

**Governance:** no merge, deploy, CI/CD or publication without an explicit separate GO.
