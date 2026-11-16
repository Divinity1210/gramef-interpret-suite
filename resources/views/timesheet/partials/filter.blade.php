<section class="section">
    <div class="section__filter">
        <form action="{{ route('timesheet.index') }}" method="GET" id="timesheet-filter-form" class="form ts-filter">
            <div class="flex-container ts-filter__row">
                <div class="form__group ts-filter__group">
                    <input type="text" placeholder="Search by Client Name" class="form__input ts-filter__input" id="client_name"
                        name="client_name" value="{{ request('client_name') }}" aria-label="Search by Client Name">
                </div>
                <div class="form__group ts-filter__group">
                    <input type="text" placeholder="Search by Agent Name" class="form__input ts-filter__input" id="agent_name" name="agent_name"
                        value="{{ request('agent_name') }}" aria-label="Search by Agent Name">
                </div>
                <div class="form__group ts-filter__group">
                    <input type="text" placeholder="Search by Job ID (Ref)" class="form__input ts-filter__input" id="ref" name="ref"
                        value="{{ request('ref') }}" aria-label="Search by Job ID (Ref)">
                </div>

                <div class="form__group ts-filter__group" style="position:relative;">
                    <input type="hidden" name="company_id" id="company_id" value="{{ request('company_id') }}">
                    <input type="text" placeholder="Search by Company" class="form__input ts-filter__input" id="company_search" autocomplete="off" aria-label="Search by Company">
                    <div id="company-suggestions" class="list-group" style="display:none; position:absolute; top:calc(100% + 4px); left:0; width:100%; max-height:280px; overflow-y:auto; box-shadow:0 10px 20px rgba(0,0,0,0.15); border:1px solid #e5e7eb; border-radius:6px; background:#fff; z-index:1035;"></div>
                    <div id="selected-company-chip" class="selected-client-chip" style="display:none; position:absolute; right:8px; top:-10px;">
                        <span id="selected-company-label"></span>
                        <button type="button" id="selected-company-clear" class="chip- btn-close close" aria-label="Clear" aria-label="Close">&times;</button>
                    </div>
                </div>

                <!-- Unified Date range filter (shared with Job Board) -->
                <div class="form__group ts-filter__group ts-filter__group--date">
                    @include('partials.filters.date-range', [
                        'mode' => 'split',
                        'formId' => 'timesheet-filter-form',
                        'inputId' => 'date_range',
                        'startName' => 'start_date',
                        'endName' => 'end_date',
                        'startId' => 'start_date',
                        'endId' => 'end_date',
                        'placeholder' => 'YYYY-MM-DD to YYYY-MM-DD',
                        // Auto-submit the form when user selects/changes a range
                        'submitWhen' => 'change'
                    ])
                </div>

                <div class="form__group ts-filter__actions">
                    <button type="submit" class="btn btn--secondary ts-filter__btn" id="ts-search-btn">Search</button>
                    @role('admin')
                    <button type="button" class="btn btn--primary ts-filter__btn ts-filter__btn--invoice" id="ts-generate-invoice-btn" aria-hidden="true">Create Invoice</button>
                    @endrole
                    <button type="button" class="btn btn--delete ts-filter__btn ts-filter__btn--clear" id="ts-clear-btn" aria-hidden="true">Clear Search</button>
                </div>
            </div>
        </form>
    </div>
</section>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Date picker is now provided by shared component. Buttons visibility handled on load and Clear button.

        const generateBtn = document.getElementById('ts-generate-invoice-btn');
        const clearBtn = document.getElementById('ts-clear-btn');
        const filterForm = document.getElementById('timesheet-filter-form');
        const dateInput = document.getElementById('date_range');
        const startInput = document.getElementById('start_date');
        const endInput = document.getElementById('end_date');

        // Initial visibility
        if (startInput.value && endInput.value) {
            if (generateBtn) { generateBtn.classList.add('ts-visible'); generateBtn.setAttribute('aria-hidden','false'); }
            if (clearBtn) { clearBtn.classList.add('ts-visible'); clearBtn.setAttribute('aria-hidden','false'); }
        }

        // Toggle visibility based on selection
        function toggleActionButtons(selectedDates) {
            if (selectedDates && selectedDates.length) {
                if (generateBtn) { generateBtn.classList.add('ts-visible'); generateBtn.setAttribute('aria-hidden','false'); }
                if (clearBtn) { clearBtn.classList.add('ts-visible'); clearBtn.setAttribute('aria-hidden','false'); }
            } else {
                if (generateBtn) { generateBtn.classList.remove('ts-visible'); generateBtn.setAttribute('aria-hidden','true'); }
                if (clearBtn) { clearBtn.classList.remove('ts-visible'); clearBtn.setAttribute('aria-hidden','true'); }
            }
        }

        // Helper to format date to YYYY-MM-DD
        function toStr(d){
            if(!d) return '';
            const y = d.getFullYear();
            const m = String(d.getMonth()+1).padStart(2,'0');
            const day = String(d.getDate()).padStart(2,'0');
            return `${y}-${m}-${day}`;
        }

        function handleDateSelection(selectedDates) {
            if (!selectedDates || selectedDates.length < 2) { return; }
            const [start, end] = selectedDates;
            startInput.value = toStr(start);
            endInput.value = toStr(end);
            filterForm.submit();
        }

        // Clear search behavior + animation out
        clearBtn && clearBtn.addEventListener('click', function() {
            startInput.value = '';
            endInput.value = '';
            dateInput.value = '';
            if (generateBtn) { generateBtn.classList.remove('ts-visible'); generateBtn.setAttribute('aria-hidden','true'); }
            if (clearBtn) { clearBtn.classList.remove('ts-visible'); clearBtn.setAttribute('aria-hidden','true'); }
            setTimeout(() => filterForm.submit(), 300); // match animation duration
        });

        // Hook Create Invoice -> navigate to collective builder with current filters
        generateBtn && generateBtn.addEventListener('click', function() {
            const params = new URLSearchParams();
            const addParam = (key, val) => { if (val) params.append(key, val); };
            addParam('client_name', filterForm.client_name.value);
            addParam('agent_name', filterForm.agent_name.value);
            addParam('ref', filterForm.ref.value);
            addParam('start_date', filterForm.start_date.value);
            addParam('end_date', filterForm.end_date.value);
            addParam('company_id', document.getElementById('company_id').value);
            const target = `${"{{ route('timesheet.collective.create') }}"}?${params.toString()}`;
            window.location.href = target;
        });

        // Intercept any element with js-confirm class for single actions
        document.addEventListener('click', async function(e){
            const el = e.target;
            if (el && el.classList.contains('js-confirm')) {
                e.preventDefault();
                const msg = el.getAttribute('data-confirm-message') || 'Are you sure?';
                const form = el.closest('form');
                if (window.azConfirm) {
                    const ok = await window.azConfirm(msg);
                    if (ok && form) form.submit();
                } else {
                    if (confirm(msg) && form) form.submit();
                }
            }
        });

        const companyInput = document.getElementById('company_search');
        const companyIdHidden = document.getElementById('company_id');
        const companySuggestions = document.getElementById('company-suggestions');
        const companyChip = document.getElementById('selected-company-chip');
        const companyChipLabel = document.getElementById('selected-company-label');
        const companyChipClear = document.getElementById('selected-company-clear');
        function showCompanyChip(id, label){ companyIdHidden.value = id; companyChipLabel.textContent = label || ('Company #' + id); companyChip.style.display = 'inline-flex'; }
        function hideCompanyChip(){ companyIdHidden.value = ''; companyChip.style.display = 'none'; companyChipLabel.textContent = ''; }
        const companyByIdUrlTemplate = "{{ route('api.companies.show.get', ['company' => '*']) }}";
        async function fetchCompanyNameById(id){
            try {
                const url = companyByIdUrlTemplate.replace('*', encodeURIComponent(id));
                const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!resp.ok) return null;
                const data = await resp.json();
                return (data && data.name) ? data.name : null;
            } catch (e) { return null; }
        }
        async function fetchCompanies(q){
            const url = `${"{{ route('admin.job-payments.companies') }}"}?q=${encodeURIComponent(q)}&limit=20`;
            const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!resp.ok) return [];
            const data = await resp.json();
            return (data.results || []);
        }
        function renderCompanySuggestions(items){
            companySuggestions.innerHTML = '';
            if (!items.length) { companySuggestions.style.display = 'none'; return; }
            items.forEach(it => {
                const a = document.createElement('a');
                a.href = '#';
                a.className = 'list-group-item list-group-item-action';
                a.textContent = it.label || it.name || ('#'+it.id);
                a.addEventListener('mousedown', function(ev){ ev.preventDefault(); showCompanyChip(it.id, it.label || it.name); companyInput.value = ''; companySuggestions.style.display = 'none'; filterForm.submit(); });
                companySuggestions.appendChild(a);
            });
            companySuggestions.style.display = 'block';
        }
        let companyFetchTimer = null;
        companyInput && companyInput.addEventListener('input', function(){
            const q = companyInput.value.trim();
            clearTimeout(companyFetchTimer);
            companyFetchTimer = setTimeout(async function(){ const items = await fetchCompanies(q); renderCompanySuggestions(items); }, 200);
        });
        companyInput && companyInput.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); filterForm.submit(); } });
        companyInput && companyInput.addEventListener('blur', function(){ setTimeout(function(){ companySuggestions.style.display = 'none'; }, 150); });
        companyChipClear && companyChipClear.addEventListener('click', function(){ hideCompanyChip(); companyInput.value = ''; companySuggestions.style.display = 'none'; filterForm.submit(); });
        if (companyIdHidden.value) {
            showCompanyChip(companyIdHidden.value);
            fetchCompanyNameById(companyIdHidden.value).then(name => { if (name) { showCompanyChip(companyIdHidden.value, name); } });
        }

        // Ensure Clear Search also clears company
        clearBtn && clearBtn.addEventListener('click', function(){ hideCompanyChip(); companyInput.value=''; });
    });
</script>
@endpush
