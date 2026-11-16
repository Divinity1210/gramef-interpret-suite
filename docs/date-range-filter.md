Unified Date Range Filter (Blade Partial)

Overview
- This project uses a single, reusable Blade partial to render a date-range picker consistently across pages.
- The partial lives at resources/views/partials/filters/date-range.blade.php and is powered by flatpickr (bundled in public/js/app.js).
- It supports two modes:
  - single: one visible input that submits a single query param like date="YYYY-MM-DD to YYYY-MM-DD" (used on Job Board and Reports)
  - split: one visible picker + two hidden inputs start_date and end_date (used on Timesheet)

Quick Start
// Single-parameter range (auto-submit on change)
@include('partials.filters.date-range', [
  'mode' => 'single',            // or 'split'
  'formId' => 'filterForm',      // id of the surrounding <form>
  'inputId' => 'dateFilter',     // id of the visible picker input
  'paramName' => 'date',         // for mode='single': query param name
  'placeholder' => 'Filter By Date...',
  'submitWhen' => 'change'       // 'change' or 'close'
])

// Split start/end hidden fields (auto-submit on close once both dates are chosen)
@include('partials.filters.date-range', [
  'mode' => 'split',
  'formId' => 'timesheet-filter-form',
  'inputId' => 'date_range',
  'startName' => 'start_date',   // name attribute for hidden start field
  'endName'   => 'end_date',     // name attribute for hidden end field
  'startId'   => 'start_date',   // element id for hidden start (optional)
  'endId'     => 'end_date',     // element id for hidden end (optional)
  'placeholder' => 'YYYY-MM-DD to YYYY-MM-DD',
  'submitWhen' => 'close'
])

Props (with defaults)
- mode: 'single' | 'split' (default 'single')
- formId: 'filterForm' — the form to auto-submit
- inputId: 'dateFilter' — id for the visible input
- placeholder: 'Filter By Date...'
- submitWhen: 'change' | 'close' (default 'change')
- paramName: 'date' — only used in single mode
- startName: 'start_date' — only used in split mode
- endName: 'end_date' — only used in split mode
- startId: inputId + '__start' — override to match legacy selectors (e.g. 'start_date')
- endId: inputId + '__end' — override to match legacy selectors (e.g. 'end_date')
- defaultStart, defaultEnd: optional preselected values (YYYY-MM-DD). If omitted, the partial derives values from the current request.

Behavior
- Flatpickr is initialized in range mode with dateFormat 'Y-m-d' and altInput 'd/m/Y'.
- In single mode, the form field is named paramName (default 'date') and its value is 'YYYY-MM-DD to YYYY-MM-DD'.
- In split mode, the visible input has no name; the two hidden inputs carry the submitted values.
- Auto-submit behavior:
  - submitWhen='change': submit as soon as dates change (used by Job Board and Reports).
  - submitWhen='close': submit once the calendar closes and a full range is selected (used by Timesheet to avoid partial state submissions).

Visuals / Styles
- The visible input uses classes 'input input--date-filter'.
- Brand tokens are inherited from base input styles (see public/css/app.css). The date filter applies a min/max width for alignment in filters.
- Flatpickr assets are bundled into public/js/app.js; no page-specific imports are required.

Usage Patterns by Page
- Interpreter Jobs and Translator Jobs: mode='single', submitWhen='change', paramName='date'.
- Reports: same as Job Board (single param, auto-submit on change).
- Timesheet: mode='split', start/end names and IDs set to start_date/end_date, submitWhen='close'.

Controller Tips
- For single mode, parse request('date') when present. Format is 'YYYY-MM-DD to YYYY-MM-DD'. Example:
  $range = request('date');
  if (is_string($range) && strpos($range, ' to ') !== false) {
      [$start, $end] = explode(' to ', $range);
  } else {
      $start = $range; $end = $range; // single date selection
  }

- For split mode, read request('start_date') and request('end_date') directly.

QA Checklist
- Prefill: when returning to a page, existing query params repopulate the picker.
- Single mode: selecting a range updates the 'date' field and submits the form according to submitWhen.
- Split mode (Timesheet): hidden inputs update on change; the form submits on close; Clear button resets inputs and submits; Create Invoice button becomes visible when both start and end are set.

Known Caveats
- Ensure flatpickr is available globally (window.flatpickr). It is packaged in public/js/app.js by default.
- If you need custom IDs (for legacy JS selectors), pass startId/endId explicitly as shown in the Timesheet example.