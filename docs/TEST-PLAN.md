# Notes 0.1.0-alpha2 — One-batch acceptance plan

**Environment:** disposable staging, backed up, WordPress 7.0+, PHP 8.4+, Core Blueprint Base with Core API 1.2. **No production use or sensitive attachments yet.**

## 1. Installation and dependency

1. Check out the feature branch and run `bash tools/check`, then `bash tools/build-release`.
2. Install `dist/core-blueprint-notes-0.1.0-alpha2.zip` in **Plugins → Add New → Upload Plugin**, with compatible Base active.
3. Verify **Core Blueprint Notes** appears with required Base dependency and shows a new **Notes** menu.
4. In a separate disposable instance, test missing/inactive/incompatible Base; expect activation refusal or inert plugin and clear required notice. Do not disable Base on shared test sites.

## 2. Settings Hub integration (alpha2)

1. Open **Core Blueprint → Extensions**. Find the official **Notes** tile under **Business** (no duplicate tiles or theme/style divergence).
2. Open **Notes**. Base must show its existing page heading and **Extension information** panel, followed by Notes' **Overview / AI Access** tabs. There must **not** be a second Notes page title below Extension information.
3. Overview: verify **My notes** counts only the current user's own notes, **Editor** says Gutenberg, and **AI access** reports the saved switch state.
4. Select **Manage Notes**: this must open the existing native Notes list. Return to Settings Hub, then select **AI Access**.
5. Verify the switch is OFF initially. Turn it ON, save, reload, and confirm ON persists; then turn it OFF and confirm it persists. Leave OFF when not actively testing MCP.
6. Choose **Notes → AI Access** in the WordPress sidebar. It must redirect to the **same central AI Access tab**, not render another settings form.
7. Verify all tabs, form labels and controls are keyboard-accessible and visibly styled by Base in light/dark modes and narrow widths. Check there is no duplicate H1.
8. As an Editor (without `manage_options`), make sure **Notes editing still works** but Notes settings are inaccessible and no private counts are revealed.

## 3. Native Gutenberg

5. As Administrator A, create Notes **Project Alpha** and **Meeting Notes** with headings, paragraphs, lists and a table.
6. Open the document settings sidebar **Notebook** panel. Set labels **Projects** and **Meetings**; save and reload. They must persist.
7. Test normal editing, autosave, WordPress revisions/restore, trash/untrash and note deletion.
8. Test normal public website search, sitemap, feeds and direct public URLs: Notes must not be published publicly.
9. Standard media uploads/embeds may be public; do **not** use confidential media in this alpha.

## 4. Mandatory two-user privacy

10. Create Editor B and Subscriber C. B should create and edit only B's notes. B must not see A's titles, body, Notebook labels, revisions or note counts in list/search.
11. Try B directly visiting A's note edit URL, preview URL and `GET /wp-json/wp/v2/cb-notes/{A_ID}?context=edit`; verify access denied. Also test the collection route with `author={A_USER_ID}`, `context=view` and `context=edit`.
12. As anonymous and Subscriber C, try the same REST requests, and revisions/autosave endpoints; nothing private may leak. C should not see the Notes admin menu.
13. Check Administrator A's explicit `cb_notes_manage_all` override against B's note. Confirm that this override is acceptable for your organization.
14. **STOP condition:** any user can see another user's content, Notebook label, restricted revisions or metadata without an authorized override.

## 5. Abilities / MCP (optional installed adapter)

15. Verify **Notes → AI Access** is OFF by default and the three Notes abilities are not externally discoverable through the authenticated MCP Adapter.
16. Enable the switch as Administrator A, reconnect the MCP client, and test `list-notes`, `search-notes`, `read-note`.
17. Verify no create/update/delete abilities exist. A client not assigned `cb_notes_use_ai` must be denied. If you deliberately assign that ability to B, B must only be able to read B's notes.
18. Disable the switch and retest: execution must be denied again.
19. Do not paste API keys/application passwords into the chat or logs.

## 6. Evidence requested

- PHP, WordPress and Base version; Notes activation and plugin-screen screenshot.
- Screenshots: Extensions hub with Notes tile, Notes Overview with Base header, Notes AI Access OFF/ON, Notes list as A/B and Gutenberg with Notebook sidebar.
- REST HTTP status/response for A-own and B-to-A note access (redact content).
- If tested, MCP discovery list, denied read result and permission identity (no credentials).
- Sanitized `wp-content/debug.log` warning/error lines if any.
- Any visual or keyboard navigation issues in Gutenberg.

## Acceptance decision

Only after runtime privacy verification, Gutenberg round-trips, negative tests, and user acceptance can the alpha become a release candidate. This branch is not approved to merge, publish, deploy or run CI/CD.
