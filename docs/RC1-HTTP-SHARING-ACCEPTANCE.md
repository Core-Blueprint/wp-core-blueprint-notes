# Notes RC1 | HTTP, CDN and sharing security acceptance

**Status: PLAN ONLY, NOT RUN (2026-10-14).** The separate SQL restore runner passed, but it does not make public HTTP requests through a web server or CDN. This gate must use a controlled test WordPress installation and **only synthetic notes**. Use the existing accepted Alpha24 ZIP with its recorded SHA-256, not a newly built artifact with different documentation.

## Test environment and STOP rules

- Record the WordPress and PHP versions, Notes ZIP hash, Base API version, web server, LiteSpeed/QUIC.cloud configuration if present, HTTP origin, CDN hostname and cache rules. Test direct-to-origin and via CDN separately when both are available.
- Use two synthetic editor accounts A/B, an authorized site administrator and a fresh anonymous client. For authenticated REST requests, use appropriately authorized test accounts and WordPress-supported authentication (e.g. nonce/cookies in a browser). Never copy live credentials or access tokens into issue comments, commands, logs or repository files.
- Use a dedicated disposable test site. Do **not** enable public access to real notes. Do not modify production DNS, cache policies or genuine provider grants to make a test pass.
- **STOP** on anonymous/private content leakage, foreign-owner REST access, stale cached public content after revocation, unexpected live grant, non-private note visibility or absent required security headers.

## R03: Native REST and privacy over actual HTTP

1. With editor A, create a synthetic private Gutenberg note and a private notebook/category/tag catalog.
2. Call exact `/wp-json/wp/v2/cb-notes/<A_NOTE_ID>?context=edit` and revision/autosave endpoints as A: expected owner access. Confirm owner list is scoped to A.
3. As editor B and anonymous client, repeat exact reads, list queries, write attempts, delete attempts, revision and autosave requests. Expect denied results and **no note title/body/classification/ID leakage**, including in errors, redirects, counts and query projections.
4. Confirm the WordPress single-post frontend, WordPress search, feeds, oEmbed, REST users/media endpoints and sitemap do not reveal A's private Notes data. Record negative outcomes without saving note bodies into logs.
5. Verify public Gutenberg `Publish` cannot convert Notes into normally public posts.

## R07: Explicit public snapshot, headers and revocation

1. On the disposable test site, create a **synthetic** allowlisted static-block note and explicitly confirm Public sharing with the required `PUBLIC` acknowledgement. Record share-path shape without placing the full bearer key in shared evidence.
2. Anonymous GET `/cb-notes/shared/<TEST_KEY>/` must return 200 and **only** the frozen snapshot. Browser and origin HTTP response must include at minimum:
   - `Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0`
   - `X-Robots-Tag: noindex, nofollow, noarchive`
   - `Referrer-Policy: no-referrer`
   - `X-Content-Type-Options: nosniff`
   - `X-Frame-Options: DENY`
   - Restrictive `Content-Security-Policy` (compare with current `Sharing\Controller::response_headers()`).
3. Edit the private source note. Existing public snapshot must **remain frozen** and not expose revised content; regular private REST remains denied to anonymous.
4. Revoke the share using A's authorized sharing action. Immediately and repeatedly request the same share URL: expect 404 with no disclosed snapshot. Repeat after note Trash and Restore, after cache warm-up, and using different anonymous clients.
5. Repeat direct-to-origin and via CDN/LiteSpeed. An origin-level 404 is **not** sufficient if an edge cache still serves previous content. Record sanitized response status, cache decision/header and policy evidence (never the raw bearer URL).
6. Test invalid/malformed keys and route permutations, as well as dangerous/embedded blocks (which must not enter a public frozen snapshot).
7. Separate unresolved security question: restoring an **older full backup containing a once-active public key** may resurrect access at the origin unless an external/non-rollback revocation mechanism prevents it. Explicitly test or define the documented restore/rekey policy **before broad production claims**.

## R08: Optional Secure Share

- **Absent**: Notes must function normally, show no broken Secure Share UI, and expose no secure share link.
- **Present**: with the real compatible Secure Share provider, test password, expiry, key isolation, failed issuance, grant revocation and provider-unavailable conditions. Check actual anonymous HTTP delivery after revocation, not just mocked PHP.
- Do not enable Secure Share solely to clear the first HTTP gate if it is out of scope; keep absent and present scenarios separate.

## Evidence and completion

Record test site type, version/build, user category, scenario ID, sanitized HTTP status, expected/actual cache/robots headers, results before and after revocation, plus the exact Notes artifact SHA. Keep screenshots/response bodies synthetic and do not store bearer links.

**PASS** only after actual HTTP origin and applicable CDN behaviors have been observed. **FAIL** on any privacy or revocation leak. **NOT RUN** remains until these procedures have actual recorded outcomes. This document is a test plan, not evidence of completed HTTP tests.
