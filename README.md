# CoolerDepot Sales Post Tracker — v0.2.41

Website Library layout and responsive cleanup. This release consolidates the stacked v0.2.38/v0.2.39/v0.2.40 Website Library CSS overrides into one coherent layout system.

- Desktop keeps Website Scan / URL CSV / Page-Sitemap Import in one equal three-column row.
- Expanded panels use readable form/control sizes and aligned grids.
- Saved website rows use identity / stats / actions columns and wrap cleanly on narrower screens.
- History tables scroll inside their own wrapper instead of widening the page.
- Tablet and phone breakpoints stack controls and actions without page-level horizontal overflow.
- Frameless chevrons are preserved.

## v0.2.47 — Sales locations and directory filters

- Admin Settings can create and remove Sales locations; each location shows its active Sales count.
- Admin can assign a location from each Sales card Settings dialog without changing the existing daily-target workflow.
- Admin Sales Dashboard adds Sales Search plus a button-based, multi-select Location filter. Every Location button includes the current number of active Sales, including Unassigned.
- Search and Location filters combine and remain responsive on tablet/mobile layouts.
- Existing Sales users remain Unassigned after migration until Admin assigns them.
