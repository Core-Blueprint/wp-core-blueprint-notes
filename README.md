# Core Blueprint Notes

A proposed Gutenberg-first, privacy-first Notes extension for the Core Blueprint WordPress Suite.

**Status:** architecture RFC only. There is no installable plugin, test suite or release artifact in this repository yet. No runtime implementation has been approved.

## Product direction

- **Notes:** private working knowledge, meeting notes, ideas and research.
- **Notebooks:** organize notes in WordPress.
- **Native Gutenberg:** canonical editor, posts, autosaves and revisions.
- **Independent module:** requires Core Blueprint Base; optional CRM, Work, Helpdesk and Docs links through public contracts.
- **AI-ready, not AI-dependent:** WordPress Abilities API and an optional MCP Adapter may expose *explicitly approved* read-only actions. No automatic AI access, background transfer or silent write access.

**This is not the separate Core Blueprint webapp Notes extension** (`christiaanbruinsma/core-blueprint-extension-notes`).

## Architecture documents

1. [WordPress Architecture RFC v1](docs/ARCHITECTURE-V1-RFC.md): domain ownership, Gutenberg data model, privacy, scope, release gates and open decisions.
2. [Integration & Abilities Contracts RFC v1](docs/INTEGRATION-CONTRACTS-V1-RFC.md): proposed public contracts, cross-module links, AI permissions, MCP exposure and security tests.

Both documents are **proposals**, not confirmed public APIs. Their PHP/ability names are illustrative and must not be treated as implemented.

## Current decisions

- Product name: **Core Blueprint Notes**.
- Editor: **Gutenberg-first**, not a custom editor.
- Privacy: **private by default**; team-sharing policy requires explicit design acceptance.
- Plugin prerequisite: **Core Blueprint Base required**; no standalone fallback.
- Sibling integrations and MCP: **optional and fail-soft**.
- No merge, runtime implementation, release build, publication or CI/CD is authorized by this documentation branch.

## Next gate

Review the RFCs; confirm personal-only versus shared/team notes for the first release, one-versus-many notebook membership and whether private attachments should be deferred. Then approve a separate implementation plan. 
