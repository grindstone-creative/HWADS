# HWADS 0.14.5 — template uploader layout

The template uploader now uses the same panel and drop-area styles as the playlist Add Placement controls. Owner and orientation have explicit labels above their selects, with responsive stacking on narrow screens. The native template list renders the uploader above its views/search/filter controls, rather than inside the floated filter toolbar. Existing upload IDs, accepted file formats, permissions and upload requests remain intact.

Validation: all 27 plugin PHP files passed parsing. A disposable WordPress site in Chrome confirmed a single uploader outside the filter toolbar, a full-width desktop drop area, accessible owner/orientation labels, keyboard file selection, stacked mobile fields without horizontal overflow and no page errors. Desktop and mobile screenshots are in docs/screenshots/template-upload-*.png. This is a plugin-only update; no Pi package is needed. Not deployed to the live site.
