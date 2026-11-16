@php
    // Props
    $mode = $mode ?? 'single'; // currently only 'single' (flatpickr range on one input)
    $formId = $formId ?? 'filters-form';
    $inputId = $inputId ?? 'dateFilter';
    $paramName = $paramName ?? 'date';
    $startName = $startName ?? 'date_from';
    $endName = $endName ?? 'date_to';
    $startId = $startId ?? $startName;
    $endId = $endId ?? $endName;
    $mirrorHidden = isset($mirrorHidden) ? (bool)$mirrorHidden : true;
    $placeholder = $placeholder ?? 'Filter By Date...';
    $submitWhen = $submitWhen ?? null; // 'change' to auto-submit

    $reqStart = request($startName);
    $reqEnd = request($endName);
    $initialRangeText = ($reqStart && $reqEnd) ? ($reqStart . ' to ' . $reqEnd) : '';
@endphp

<label for="{{ $inputId }}" class="form-label visually-hidden">Date Range</label>
<input
    type="text"
    id="{{ $inputId }}"
    name="{{ $paramName }}"
    class="form-control section__input input input--text input--date-filter"
    placeholder="{{ $placeholder }}"
    value="{{ $initialRangeText }}"
    autocomplete="off"
    data-start-id="{{ $startId }}"
    data-end-id="{{ $endId }}"
    data-form-id="{{ $formId }}"
    data-submit-when="{{ $submitWhen }}"
/>

@if($mirrorHidden)
    <input type="hidden" id="{{ $startId }}" name="{{ $startName }}" value="{{ $reqStart }}" />
    <input type="hidden" id="{{ $endId }}" name="{{ $endName }}" value="{{ $reqEnd }}" />
@endif

@push('scripts')
<script>
    // Initialize a single-input flatpickr range for the date filter.
    (function(){
        function init(){
            var el = document.getElementById('{{ $inputId }}');
            if (!el || typeof window.flatpickr !== 'function') return;
            if (el.getAttribute('data-fp-initialized') === '1') return;
            el.setAttribute('data-fp-initialized','1');

            // Read IDs and config from the element's data attributes
            var startId = el.getAttribute('data-start-id');
            var endId = el.getAttribute('data-end-id');
            var formId = el.getAttribute('data-form-id');
            var submitWhen = el.getAttribute('data-submit-when') || '';

            var startHidden = startId ? document.getElementById(startId) : null;
            var endHidden = endId ? document.getElementById(endId) : null;
            var defaults = [];
            if (startHidden && startHidden.value && endHidden && endHidden.value) {
                defaults = [startHidden.value, endHidden.value];
            }

            window.flatpickr(el, {
                mode: 'range',
                dateFormat: 'Y-m-d',
                allowInput: true,
                conjunction: ' to ',
                defaultDate: defaults,
                onChange: function(selectedDates, dateStr, instance){
                    var start = '', end = '';
                    if (selectedDates.length >= 1) start = instance.formatDate(selectedDates[0], 'Y-m-d');
                    if (selectedDates.length >= 2) end = instance.formatDate(selectedDates[1], 'Y-m-d');
                    if (startHidden) startHidden.value = start;
                    if (endHidden) endHidden.value = end;

                    if (submitWhen === 'change' && selectedDates.length === 2 && formId) {
                        var form = document.getElementById(formId);
                        if (form) { form.requestSubmit ? form.requestSubmit() : form.submit(); }
                    }
                }
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else { init(); }
    })();
</script>
@endpush