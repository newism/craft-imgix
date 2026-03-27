---
title: "refactor: Improve Imgix Asset Transformer docs quality"
type: refactor
status: active
date: 2026-04-02
---

# refactor: Improve Imgix Asset Transformer docs quality

## Overview

Review and improve all documentation for the Imgix Asset Transformer plugin to match the quality and completeness of the not-found-redirects plugin docs. Capture a new full-page screenshot of the configuration debugging settings page.

## Problem Frame

The docs are in good shape overall but have a few gaps: a typo in configuration.md, an underdocumented placeholder SVG page, missing `subPath` config block, an incomplete README documentation link, and a missing screenshot for the configuration debugging section header. The not-found-redirects docs set the quality bar — consistent structure, thorough coverage, screenshots where useful.

## Requirements Trace

- R1. Fix known content errors (typo in `imgixDefaultParams` description)
- R2. Flesh out underdocumented pages (placeholder SVG)
- ~~R3. Add missing configuration reference for `subPath` per-volume setting~~ (deferred to next pass)
- R4. Capture full-page screenshot of the settings/debugging page
- R5. Add screenshot to the Debugging Configuration section
- R6. Fix README documentation link to point to the actual docs site
- R7. Keep existing content and structure intact — additive improvements only

## Scope Boundaries

- No new doc pages (events, developer reference, etc.) — out of scope for this pass
- No VitePress config or theme changes
- No code changes to the plugin source
- No changes to existing screenshots — only adding the new one

## Context & Research

### Relevant Code and Patterns

- `docs/configuration.md` — the main page with config blocks and debugging section
- `docs/placeholder-svg.md` — underdocumented, only 3 lines of content
- `docs/screenshots/` — existing screenshots (6 files, all from 2025-03-28)
- `src/models/VolumeSettings.php` — has `subPath` property not documented with a `:::config` block
- `src/templates/settings/_index.twig` — the settings page that needs to be screenshotted
- `README.md` — has incomplete documentation link
- Screenshot convention from not-found-redirects: Chrome DevTools MCP, 1440x810 viewport, `take_screenshot` with `filePath`

### Screenshot Convention (from not-found-redirects CLAUDE.md)

- Viewport: 1440x810 (16:9) — user requested 1440px width
- Tool: Chrome DevTools MCP against `https://craft-cloud.ddev.site`
- Full page screenshot for the settings page
- Save to `docs/screenshots/`

## Key Technical Decisions

- **Full-page screenshot rather than viewport-only**: The settings page has three sections (Settings, Volume Overrides, Volume Debug) that extend beyond the viewport. A full-page capture shows the complete debugging experience in one image.
- **Keep `:::config` block style**: Matches the existing pattern in configuration.md and the shared VitePress `configPlugin`.
## Open Questions

### Resolved During Planning

- **Where does the screenshot go?** → `docs/screenshots/settings-debugging.png` — follows existing naming convention (`settings-config.png`, `settings-full.png`, `settings-table.png`)
- **What to add to placeholder SVG page?** → Explain what it generates (transparent SVG data URI), why it's useful (CLS prevention, LQIP placeholder patterns), and show example output.
- **Docs URL valid?** → `https://plugins.newism.com.au/imgix-asset-transformer/` confirmed live

### Deferred to Next Pass

- `subPath` `:::config` block and path diagram updates — user wants to review after first round

### Deferred to Implementation

- Exact alt text for the new screenshot — depends on what the page looks like when captured

## Implementation Units

- [ ] **Unit 1: Fix typo in configuration.md**

**Goal:** Fix "mgix rendering parameters" → "Imgix rendering parameters"

**Requirements:** R1

**Dependencies:** None

**Files:**
- Modify: `docs/configuration.md`

**Approach:**
- Line 57: `'mgix rendering parameters'` → `'Imgix rendering parameters'`

**Patterns to follow:**
- Other `:::config` descriptions in the same file

**Test scenarios:**
- Test expectation: none — single-character typo fix

**Verification:**
- The word "mgix" no longer appears in the file

---

- [ ] **Unit 2: Flesh out placeholder SVG documentation**

**Goal:** Expand the placeholder SVG page from 3 lines to a useful reference

**Requirements:** R2

**Dependencies:** None

**Files:**
- Modify: `docs/placeholder-svg.md`

**Approach:**
- Explain what `imgix.getPlaceholderSVG(string $width, string $height)` returns (a URL-encoded transparent SVG data URI — `data:image/svg+xml;charset=utf-8,...`). Note: existing examples pass integers which work via PHP type coercion
- Explain when to use it: preventing CLS (Cumulative Layout Shift) by reserving space for images before they load, LQIP placeholder patterns
- Show a practical template example using it as a `src` with `srcset` for lazy loading
- Keep it concise — this is a utility function, not a feature page

**Patterns to follow:**
- Writing style and frontmatter from other docs in this plugin
- The Twig extension source for accurate function signatures

**Test scenarios:**
- Test expectation: none — documentation content only

**Verification:**
- Page explains what the function returns, why it's useful, and shows a practical example

---

- [ ] **Unit 3: Capture full-page screenshot of settings/debugging page**

**Goal:** Take a full-page screenshot of the plugin settings page at `https://craft-cloud.ddev.site/admin/settings/plugins/newism-imgix?site=default`

**Requirements:** R4

**Dependencies:** None

**Files:**
- Create: `docs/screenshots/settings-debugging.png`

**Approach:**
- Use Chrome DevTools MCP tools
- Resize viewport to 1440px wide (1440x810)
- Navigate to `https://craft-cloud.ddev.site/admin/settings/plugins/newism-imgix?site=default`
- Wait for content to load
- Take a full-page screenshot (not viewport-only) — the settings page has Settings, Volume Overrides, and Volume Debug sections that extend below the fold
- Save to `docs/screenshots/settings-debugging.png`

**Patterns to follow:**
- Screenshot convention from not-found-redirects CLAUDE.md: viewport 1440x810, Chrome DevTools MCP

**Test scenarios:**
- Test expectation: none — screenshot capture

**Verification:**
- `docs/screenshots/settings-debugging.png` exists and shows the complete settings page including the Volume Debug section with sample assets

---

- [ ] **Unit 4: Add screenshot to Debugging Configuration section**

**Goal:** Reference the new screenshot in the configuration docs

**Requirements:** R5

**Dependencies:** Unit 3

**Files:**
- Modify: `docs/configuration.md`

**Approach:**
- Add the screenshot image after the "Debugging Configuration" heading introductory text, before the "Configuration summary" subheading
- Use the same markdown image pattern as the existing `volume-debug-s3.png` reference

**Patterns to follow:**
- Line 263: `![Volume debug showing filesystem, volume, and Imgix settings with sample assets](./screenshots/volume-debug-s3.png)` — same image markdown pattern

**Test scenarios:**
- Test expectation: none — documentation addition

**Verification:**
- The screenshot renders in the Debugging Configuration section when viewing the docs

---

- [ ] **Unit 6: Fix README documentation link**

**Goal:** Add the actual documentation URL to the README

**Requirements:** R6

**Dependencies:** None

**Files:**
- Modify: `README.md`

**Approach:**
- The current text reads: "Visit the Imgix Asset Transformer page for all documentation, guides, pricing and developer resources." — no link
- Add the docs site URL: `https://plugins.newism.com.au/imgix-asset-transformer/` (derived from the VitePress `base: '/imgix-asset-transformer/'` config and the plugins.newism.com.au domain from the memory context)

**Patterns to follow:**
- The index.md already links to the plugin store; the README should link to the docs site

**Test scenarios:**
- Test expectation: none — README text fix

**Verification:**
- README contains a clickable link to the documentation site

## System-Wide Impact

- **No code changes** — all modifications are documentation and screenshot files
- **VitePress build** — changes to `.md` files will be picked up on next `vitepress build`/`vitepress dev`
- **Screenshot file size** — full-page screenshot will be larger than viewport-only; existing `settings-full.png` is 857KB for reference

## Risks & Dependencies

| Risk | Mitigation |
|------|------------|
| DDEV site not running for screenshot | Verify site is accessible before attempting capture |
| Settings page has no data (empty volumes) | The craft-cloud DDEV project should have volumes configured; verify during capture |
| Docs site base URL assumption wrong | Derived from VitePress config `base: '/imgix-asset-transformer/'` + memory context for `plugins.newism.com.au` domain |

## Sources & References

- Screenshot convention: `craft-not-found-redirects/CLAUDE.md` lines 631-672
- VitePress config: `docs/.vitepress/config.ts`
- Plugin settings model: `src/models/Settings.php`
- Volume settings model: `src/models/VolumeSettings.php`
- Settings template: `src/templates/settings/_index.twig`
