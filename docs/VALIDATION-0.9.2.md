# 0.9.2 validation

- JavaScript syntax check passed.
- Focused executable checks passed for Add Placement opening choices, media fallback with no templates, template-copy request source/destination/nonce, returned placement identity, and failed creation handling.
- Git diff whitespace checks passed; install ZIP built and ZIP integrity checked.
- No PHP executable or disposable WordPress installation is available in this workspace. PHP lint, database integration, full browser interaction and installation on the test site have not been performed. Existing tests/native.php and tests/native-browser.mjs require their disposable WordPress fixture.

## Test-site acceptance

1. Replace the installed plugin with dist/hwads-0.9.2.zip and reload editor tabs.
2. On a saved playlist, Add Placement should focus template selection rather than insert a card.
3. Choose a template and Add Selected Template: a new independent card appears; save/update the playlist.
4. Edit that placement and confirm its source template remains unchanged.
5. Upload or choose library media; verify new independent placements and reorder/save behavior.
6. Duplicate explicitly; remove and restore a saved placement without duplicating its ID.
7. Check restricted users still cannot use another team's templates; check published content does not change until playlist publication/update.

Owner/location restrictions remain enforced. Global administrators already see cross-venue templates in automatic approval mode; manual approval and house-playlist rules have not been bypassed.
