import Vue from 'vue'
import 'select2'
import './bootstrap'
import flatpickr from "flatpickr/dist/flatpickr"
// Expose flatpickr globally so Blade partials can initialize date range inputs
window.flatpickr = flatpickr

Vue.config.productionTip = false

Vue.component('translator-job-dropzone', require('./components/TranslatorJobDropzone.vue').default)
Vue.component('document-dropzone', require('./components/DocumentDropzone.vue').default)
Vue.component('translation-file-dropzone', require('./components/TranslationFileDropzone.vue').default)
Vue.component('quote-form', require('./components/QuoteForm.vue').default)

const app = new Vue({
    el: '#app',
})

$('#hamburger').on('click', function () {
    $(this).toggleClass('is-active')
    $('#headerCollapse').toggleClass('header__collapse--open')
    // Prevent body from scrolling when the mobile menu is open
    const isOpen = $('#headerCollapse').hasClass('header__collapse--open')
    $('body').toggleClass('no-scroll', isOpen)
});

$('#headerLogout').on('click', function () {
    $(this).toggleClass('header__logout--open')
});

$('#filterForm .section__input').not('#dateFilter').on('change', () => {
    $('#filterForm').submit()
});

$('.job__status-dropdown').on('change', function () {
    const msg = confirm('Are you sure you want to update this job\'s status?')

    if (msg) {
        $(this).parent('form').submit()
    } else {
        $(this).val($(this).data('status'))
    }
});

$('.input--select').each(function () {
    $(this)
        .toggleClass('input--not-chosen', !$(this).val())
        .change(() => $(this).toggleClass('input--not-chosen', !$(this).val()))
});

$('#radioButtons input[type="radio"]').on('change', function () {
    const link = $(this).data('link')
    if (link) {
        window.location = link
    }
})

$('.message__close').on('click', function () {
    $(this).parent().addClass('message--close')
});

$('#languagesSelect').select2({
    tags: true,
    placeholder: "Select a language",
    width: 'resolve'
});

$('[data-button="reject"').click(function() {
    const msg = confirm('Are you sure you want to reject this request?')
    if (!msg) return

    $('#rejectForm').submit()
});

$('[data-button="cancel"]').click(function() {
    if ($(this).data('is-within-24-hours')) {
        $('#24HourModal').modal('show', {
            route: $(this).data('route'),
        })
    } else {
        $('#cancelModal').modal('show', {
            route: $(this).data('route'),
        })
    }
});

$('#unmatchedAgentsSelect').select2()

$('#unmatchedAgentsSelect').change(function() {
    $(this).parents('form').submit()
});

// Initialize date range pickers for inputs with class .input--date-filter
// This replaces inline <script> usage in Blade partials to avoid Vue template errors
(function initDateFilters(){
    function initAllPickers(){
        const fp = (window.flatpickr) ? window.flatpickr : null;
        if (!fp) {
            window.addEventListener('load', initAllPickers, { once: true });
            return;
        }

        const inputs = document.querySelectorAll('.input--date-filter');
        if (!inputs || inputs.length === 0) return;

        inputs.forEach(function(el){
            // Prevent duplicate initialization
            if (el.getAttribute('data-fp-initialized') === '1') return;
            el.setAttribute('data-fp-initialized', '1');

            const startId = el.getAttribute('data-start-id');
            const endId = el.getAttribute('data-end-id');
            const formId = el.getAttribute('data-form-id');
            const submitWhen = el.getAttribute('data-submit-when');
            const startHidden = startId ? document.getElementById(startId) : null;
            const endHidden = endId ? document.getElementById(endId) : null;

            // Compute defaultDate from existing hidden inputs
            let defaultDates = [];
            try {
                const s = startHidden ? startHidden.value : '';
                const e = endHidden ? endHidden.value : '';
                if (s && e) defaultDates = [s, e];
            } catch (e) {}

            fp(el, {
                mode: 'range',
                dateFormat: 'Y-m-d',
                allowInput: true,
                defaultDate: defaultDates,
                conjunction: ' to ',
                onChange: function(selectedDates, dateStr, instance) {
                    try {
                        let start = '', end = '';
                        if (selectedDates.length >= 1) {
                            start = instance.formatDate(selectedDates[0], 'Y-m-d');
                        }
                        if (selectedDates.length >= 2) {
                            end = instance.formatDate(selectedDates[1], 'Y-m-d');
                        }
                        if (startHidden) startHidden.value = start;
                        if (endHidden) endHidden.value = end;
                    } catch (e) {}

                    // Only submit if we have both dates selected
                    if (submitWhen === 'change' && formId && selectedDates.length === 2) {
                        const form = document.getElementById(formId);
                        if (form) {
                            form.requestSubmit ? form.requestSubmit() : form.submit();
                        }
                    }
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllPickers);
    } else {
        initAllPickers();
    }
})();
