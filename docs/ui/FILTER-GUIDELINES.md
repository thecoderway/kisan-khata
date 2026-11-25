# Listing Filter Guidelines

## Purpose and scope

These rules apply to search and filter controls on application listing pages, including Customer Directory and future modules. Filtering remains server-authoritative; the browser improves interaction but does not replace query validation, authorization or tenant scoping.

## Automatic filtering

- Listing filters SHOULD apply automatically when the user changes a filter; a separate Search/Apply button SHOULD NOT be required for routine filters.
- Text search MUST use a short debounce (default 350–400 ms) to avoid a request on every keystroke.
- Select, checkbox, date and other discrete filters SHOULD apply on their change event when the value is valid and complete.
- Keep an explicit submit/apply control only when the filter workflow genuinely requires multiple fields to be staged together, expensive queries need deliberate execution, or accessibility/usability testing supports that choice. Document the reason.
- Provide a clear way to reset all filters and return to the unfiltered listing.

## Request lifecycle

- Keep filtering server-side through the module's existing authorized query/service boundary.
- Cancel or ignore stale in-flight requests so a slower earlier response cannot overwrite newer filter results.
- Prevent unnecessary duplicate requests when the normalized filter state has not changed.
- Show a visible pending/loading state while results are being refreshed.
- On failure, keep the current results visible, show a useful error and allow retry. Do not replace valid results with an empty table merely because a request failed.
- When filters change, reset pagination to the first page unless the user explicitly changes the page.
- Preserve the current sort order when compatible with the new filter state.
- Update the URL query string with the active filters and page state where practical, so refresh, back/forward navigation and shareable links behave predictably.
- Do not update browser history for every keystroke. Use a deliberate replace/push strategy that avoids polluting the history stack.

## Input and accessibility behavior

- Give each filter a persistent visible label.
- Expose loading and result-count changes through an appropriate accessible status/live region without making every keystroke excessively chatty.
- Keep keyboard operation and visible focus intact.
- Support Hindi and English input, including composed characters; debounce logic must not lose text or interrupt input composition.
- Avoid making the entire page flash or jump when only the results region changes.

## Performance and correctness

- Choose debounce and request behavior appropriate to the data size and query cost; 350–400 ms is the default for text search, not an unchangeable constant.
- Apply server-side validation and safe query construction. Never interpolate raw user filter input into SQL.
- Keep tenant isolation and role-based access in the query path for every asynchronous request.
- Preserve the same filtering semantics between normal page loads and asynchronous updates.
- Ensure pagination links, filter changes and browser navigation do not create contradictory states.

## Acceptance checklist

- [ ] Text search applies automatically after the debounce interval.
- [ ] Discrete filters apply automatically when changed.
- [ ] Stale requests cannot overwrite newer results.
- [ ] Pagination resets appropriately after filter changes.
- [ ] Loading, empty and error states are distinct.
- [ ] URL state is updated where practical and back/forward behavior is verified.
- [ ] Hindi/English input and keyboard accessibility work.
- [ ] Server-side validation, authorization and tenant isolation remain authoritative.
