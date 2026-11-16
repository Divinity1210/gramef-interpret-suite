@extends('layouts.app')

@section('content')
    <main class="section">
        <div class="section__top d-flex flex-wrap justify-content-between align-items-center mb-3" style="gap:10px;">
            <div>
                <div class="section__heading mb-0">Post an Interpreter Job</div>
                <div class="small text-muted">Use the Paste-an-Email Smart Helper below to fill fields in 30 seconds, or fill manually as normal.</div>
            </div>
        </div>

        <!-- Deliverable 5A: Paste-an-Email / Referral Sheet Quick Booking -->
        <div class="ms-card p-3 mb-4" style="border:1px solid #BFDBFE;background:#EFF6FF;">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2" style="gap:8px;">
                <strong style="color:#1E3A8A;"><i class="fas fa-magic mr-1"></i> 5A. “Paste-an-Email” Quick Booking Helper (Auto-Fills Form Below)</strong>
                <div class="d-flex flex-wrap" style="gap:6px;">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadSampleBooking('nhs')">Sample: NHS Urgent Referral</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadSampleBooking('court')">Sample: Magistrates Court</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadSampleBooking('council')">Sample: Council Home Visit</button>
                </div>
            </div>
            <textarea id="quickPasteText" rows="3" class="form-control mb-2" style="font-size:13px;"
                placeholder="Paste any hospital, solicitor, or council booking email here (Language, Date, Time, Postcode, Gender, DBS, Reference)..."></textarea>
            <div class="d-flex justify-content-between align-items-center">
                <span id="quickPasteStatus" class="small text-primary font-weight-bold"></span>
                <button type="button" class="btn btn-primary btn-sm px-4 font-weight-bold" style="border-radius:999px;" onclick="parseQuickBooking()">
                    <i class="fas fa-bolt mr-1"></i> Auto-Fill Booking Form
                </button>
            </div>
        </div>

        {{ Form::open(['route' => 'interpreter-jobs.store', 'class' => 'form']) }}

        @include('interpreter-jobs.form')

        {{ Form::close() }}
    </main>
@endsection

@push('scripts')
<script>
    function loadSampleBooking(kind) {
        var samples = {
            nhs: "URGENT NHS REFERRAL\nLanguage: Arabic\nDate: {{ now('Europe/London')->addDay()->format('d/m/Y') }}\nTime: 10:30 - 12:30\nDepartment: Maternity Assessment Unit, Birmingham Women's Hospital\nAddress: Mindelsohn Way, Edgbaston\nPostcode: B15 2TG\nRequirement: Female interpreter, Enhanced DBS\nPatient Name: Fatima Al-Mansoor\nNHS PID: 482910441\nReference: NHS-QE-88412",
            court: "HMCTS COURT BOOKING\nLanguage: Polish\nDate: {{ now('Europe/London')->addDays(2)->format('d/m/Y') }}\nTime: 09:30 - 13:00\nLocation: Courtroom 4, Victoria Law Courts\nPostcode: B4 6QA\nSecurity: Enhanced DBS\nCase Reference: HMCTS-2026-9910",
            council: "MANCHESTER CITY COUNCIL SOCIAL CARE\nLanguage: Urdu\nDate: {{ now('Europe/London')->addDays(3)->format('d/m/Y') }}\nTime: 14:00 - 15:30\nDepartment: Children & Family Safeguarding\nAddress: 42 Oxford Road, Manchester\nPostcode: M1 5QA\nSecurity: Enhanced DBS\nClient Reference: MCC-FAM-7721"
        };
        document.getElementById('quickPasteText').value = samples[kind] || '';
        parseQuickBooking();
    }

    function parseQuickBooking() {
        var raw = document.getElementById('quickPasteText').value;
        var statusEl = document.getElementById('quickPasteStatus');
        if (!raw.trim()) return;
        statusEl.textContent = 'Parsing email fields...';

        fetch("{{ route('smart-booking.parse') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ raw_text: raw })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res || !res.fields) return;
            var f = res.fields;
            var setVal = function (selector, val) {
                if (val === null || val === undefined || val === '') return;
                var el = document.querySelector(selector);
                if (!el) return;
                el.value = val;
                el.dispatchEvent(new Event('change', { bubbles: true }));
            };

            if (f.language_id) setVal('select[name="to_language_id"]', f.language_id);
            if (f.security_type_id) setVal('select[name="security_type_id"]', f.security_type_id);
            if (f.gender !== null && f.gender !== undefined) setVal('select[name="gender"]', f.gender);
            if (f.appointment_date) setVal('input[name="appointment_date"]', f.appointment_date);
            if (f.start_time) setVal('input[name="start_time"]', f.start_time);
            setVal('input[name="duration_hours"]', f.duration_hours);
            setVal('input[name="duration_minutes"]', f.duration_minutes);
            setVal('input[name="address_line_1"]', f.address_line_1);
            setVal('input[name="postcode"]', f.postcode);
            setVal('input[name="department"]', f.department);
            setVal('input[name="user_first_name"]', f.user_first_name);
            setVal('input[name="user_last_name"]', f.user_last_name);
            setVal('input[name="personal_identity_number"]', f.personal_identity_number);
            setVal('input[name="client_reference"]', f.client_reference);

            statusEl.textContent = 'Auto-filled ' + (f.language || 'booking') + ' (' + (f.postcode || 'verified') + ') — review & click Create!';
        })
        .catch(function () {
            statusEl.textContent = 'Could not parse automatically — please review fields below.';
        });
    }
</script>
@endpush
