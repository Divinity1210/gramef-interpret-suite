<div class="row">
    <div class="col-lg-4" id="userFields">
        <fieldset class="form__field">

            {{ Form::label('title', 'Profile information', ['class' => 'label form__label required']) }}

            <div class="form__inputs">

                {{ Form::select('title', config('enums.titles'), isset($agent) ? $agent->user->title : old('title'), ['class' => 'input input--select form__input form__input--title', 'required' => 'required', 'placeholder' => 'Title']) }}

                @role('new-agent')
                    {{ Form::text('first_name', auth()->user()->first_name ?? null, ['class' => 'input form__input', 'placeholder' => 'First name', 'required' => 'required']) }}
                @else
                    {{ Form::text('first_name', isset($agent) ? $agent->user->first_name : old('first_name'), ['class' => 'input form__input', 'placeholder' => 'First name', 'required' => 'required']) }}
                @endrole

            </div>

            @role('new-agent')
                {{ Form::text('last_name', auth()->user()->last_name ?? null, ['class' => 'input form__input', 'placeholder' => 'Last name', 'required' => 'required']) }}
                {{ Form::email('email', auth()->user()->email ?? null, ['class' => 'input form__input', 'placeholder' => 'Email address', 'required' => 'required']) }}
            @else
                {{ Form::text('last_name', isset($agent) ? $agent->user->last_name : old('last_name'), ['class' => 'input form__input', 'placeholder' => 'Last name', 'required' => 'required']) }}
                {{ Form::email('email', isset($agent) ? $agent->user->email : old('email'), ['class' => 'input form__input', 'placeholder' => 'Email address', 'required' => 'required']) }}
            @endrole

            @hasanyrole('new-agent|agent')
                {{ Form::password('password', ['class' => 'input form__input', 'placeholder' => 'Password']) }}
            @endhasanyrole

            {{ Form::text('contact_number', null, ['class' => 'input form__input', 'placeholder' => 'Contact number', 'required' => 'required']) }}

        </fieldset>
        <fieldset class="form__field">

            {{ Form::label('gender', 'Gender', ['class' => 'label form__label required']) }}

            <ul class="choices">
                <li class="choices__choice">
                    {{ Form::radio('gender', 0, null, ['class' => 'choices__radio', 'required' => 'required']) }}
                    {{ Form::label('gender', 'Male', ['class' => 'label choices__label']) }}
                </li>
                <li class="choices__choice choices__choice--last">
                    {{ Form::radio('gender', 1, null, ['class' => 'choices__radio', 'required' => 'required']) }}
                    {{ Form::label('gender', 'Female', ['class' => 'label choices__label']) }}
                </li>
            </ul>
        </fieldset>
        </fieldset>
        <fieldset class="form__field">

            {{ Form::label('address_line_1', 'Address', ['class' => 'label form__label required']) }}

            {{ Form::text('address_line_1', old('address_line_1'), ['class' => 'input form__input', 'placeholder' => 'Address line 1', 'required' => 'required']) }}

            {{ Form::text('address_line_2', old('address_line_2'), ['class' => 'input form__input', 'placeholder' => 'Address line 2']) }}

            {{ Form::text('county', old('county'), ['class' => 'input form__input', 'placeholder' => 'County', 'required' => 'required']) }}

            {{ Form::text('postcode', old('postcode'), ['class' => 'input form__input form__input--small', 'placeholder' => 'Postcode', 'required' => 'required']) }}

        </fieldset>
        <fieldset class="form__field mb-lg-0" id="affidavitAndAffirmation">
            <div class="form__long-field">
                {{ Form::label('can_provide_affidavit', 'Can you provide a sworn affidavit?', ['class' => 'label form__label form__label--long required']) }}
                <ul class="choices">
                    <li class="choices__choice">
                        {{ Form::radio('can_provide_affidavit', 0, null, ['class' => 'choices__radio']) }}
                        {{ Form::label('can_provide_affidavit', 'No', ['class' => 'label choices__label']) }}
                    </li>
                    <li class="choices__choice">
                        {{ Form::radio('can_provide_affidavit', 1, null, ['class' => 'choices__radio']) }}
                        {{ Form::label('can_provide_affidavit', 'Yes', ['class' => 'label choices__label']) }}
                    </li>
                </ul>
            </div>
            <div class="form__long-field">
                {{ Form::label('can_provide_affirmation', 'Can you provide a sworn affirmation?', ['class' => 'label form__label form__label--long required']) }}
                <ul class="choices">
                    <li class="choices__choice">
                        {{ Form::radio('can_provide_affirmation', 0, null, [ 'class' => 'choices__radio' ]) }}
                        {{ Form::label('can_provide_affirmation', 'No', ['class' => 'label choices__label']) }}
                    </li>
                    <li class="choices__choice">
                        {{ Form::radio('can_provide_affirmation', 1, null, [ 'class' => 'choices__radio' ]) }}
                        {{ Form::label('can_provide_affirmation', 'Yes', [ 'class' => 'label choices__label' ]) }}
                    </li>
                </ul>
            </div>
        </fieldset>
    </div>
    <div class="col-lg-4 agent-fields">
        <fieldset class="form__field">

            {{ Form::label('interpreter_types[]', 'Interpreter Type:', ['class' => 'label form__label required']) }}

            @foreach (App\Agent::$agentTypes as $key => $agentType)
                <div class="form__item form__item--method">

                    {{ Form::checkbox('interpreter_types[]', $key, isset($agent) ? $agent->user->getRoleNames()->contains($key) : null, [
                            'class' => 'form__radio interpreter-type'
                        ])
                    }}
                    {{ Form::label('interpreter_types', $agentType, ['class' => 'label form__label form__label--radio']) }}

                </div>
            @endforeach

        </fieldset>
        <fieldset class="form__field skills">

            {{ Form::label('skills[]', 'Skills / Services', ['class' => 'label form__label required']) }}

            <div class="row">

                @foreach ($skills as $skill)
                    <div class="form__item form__item--method col-lg-6 skill" data-type="{{ $skill->getSkillType() }}">
                        {{ Form::checkbox('skills[]', $skill->id, isset($agent) ? $agent->skills->contains($skill) : null, [
                                'class' => 'form__radio',
                            ])
                        }}
                        {{ Form::label('skills', $skill->skill, ['class' => 'label form__label form__label--radio']) }}
                    </div>
                @endforeach

            </div>
        </fieldset>
        <fieldset class="form__field">

            {{ Form::label('languages[]', 'Please add your languages', ['class' => 'label form__label required']) }}
            <p>If you don't see your language you can type the name of the language and press enter to add it</p>
            {{ Form::select('languages[]', $languages, isset($agent) ? $agent->languages : null, [
                    'class' => 'input input--select form__input',
                    'required' => 'required',
                    'id' => 'languagesSelect',
                    'multiple' => 'multiple'
                ])
            }}

        </fieldset>
        <fieldset class="form__field">

            {{ Form::label('skype_details', 'Skype details', [ 'class' => 'label form__label' ]) }}
            {{ Form::textarea('skype_details', null, [ 'class' => 'input input--textarea form__input', 'placeholder' => 'Skype details' ]) }}

        </fieldset>
    </div>
    <div class="col-lg-4 agent-fields">

        <fieldset class="form__field">

            @if (isset($agent))
                <img class="img-fluid" src="{{ $agent->getProfilePicture() }}" alt="Profile Picture">
            @endif

        </fieldset>
        <fieldset class="form__field">

            {{ Form::label('documents', 'Please upload a profile picture', ['class' => 'label form__label required']) }}

            @if (isset($agent))
                <document-dropzone url="/api/documents" input-name="profile_picture" profile-picture="{{ $agent->profile_picture }}"></document-dropzone>
            @else
                <document-dropzone url="/api/documents" input-name="profile_picture"></document-dropzone>
            @endif

        </fieldset>

        <fieldset class="form__field">

            {{ Form::label('contact_method', 'Please select how you would like to be notified', ['class' => 'label form__label']) }}

            @foreach ($contactMethods as $key => $contactMethod)
                <div class="form__item form__item--method">
                    {{ Form::checkbox('contact_method[]', $contactMethod->id, isset($agent) ? $agent->contactMethods->contains($contactMethod) : $key == 0, ['class' => 'form__radio']) }}
                    {{ Form::label('contact_method', $contactMethod->contact_method, ['class' => 'label form__label form__label--radio']) }}
                </div>
            @endforeach

        </fieldset>

        @role('admin')
            <fieldset class="form__field">

                {{ Form::label('restrict_job_notifications', 'Notifications:', ['class' => 'label form__label']) }}

                <div class="form__item form__item--method">
                    {{ Form::hidden('restrict_job_notifications', 0) }}
                    {{ Form::checkbox('restrict_job_notifications', 1, null, ['class' => 'form__radio']) }}
                    {{ Form::label('restrict_job_notifications', 'Restrict job notifications', ['class' => 'label form__label form__label--radio']) }}
                </div>
            </fieldset>
        @endrole

</div>
</div>

{{-- Banking Information Section --}}
<div class="row">
    <div class="col-lg-12">
        <fieldset class="form__field">
            <div class="d-flex justify-content-between align-items-center">
                {{ Form::label('banking', 'Banking Information', ['class' => 'label form__label']) }}
                <small class="text-muted">Used for payments. Stored securely with encryption.</small>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <label for="bank_name" class="form-label">Bank Name <span class="text-danger">*</span></label>
                    <input type="text" id="bank_name" class="form-control" placeholder="e.g. Barclays" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label for="account_holder" class="form-label">Account Holder</label>
                    <input type="text" id="account_holder" class="form-control" value="{{ old('account_holder', data_get($agent ?? null, 'user.full_name', '')) }}" autocomplete="name">
                </div>
                <div class="col-md-3">
                    <label for="account_number" class="form-label">Account Number <span class="text-danger">*</span></label>
                    <input type="text" id="account_number" class="form-control" placeholder="Your account number" autocomplete="off" inputmode="numeric">
                </div>
                <div class="col-md-3">
                    <label for="sort_code" class="form-label">Sort Code</label>
                    <input type="text" id="sort_code" class="form-control" placeholder="e.g. 12-34-56" autocomplete="off" inputmode="numeric">
                </div>
            </div>

            <div class="mt-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="consent" value="1">
                    <label class="form-check-label" for="consent">
                        I consent to AtoZ securely storing my bank details for the purpose of processing payments. I can revoke this at any time.
                    </label>
                </div>
            </div>

            <div class="mt-3 d-flex">
                <button type="button" id="saveBankDetailsBtn" class="btn btn--primary">Save Banking Details</button>
                <button type="button" id="deleteBankDetailsBtn" class="btn btn--danger ms-2">Delete Banking Details</button>
            </div>

            <div class="mt-2">
                <small id="bankingStatus" class="text-muted"></small>
            </div>
        </fieldset>
    </div>
</div>

<!-- Context values for JS without Blade directives inside the script block -->
<input type="hidden" id="isAgentValue" value="{{ auth()->user()->hasRole('agent') ? '1' : '0' }}">
<input type="hidden" id="isAdminValue" value="{{ auth()->user()->hasRole('admin') ? '1' : '0' }}">
<input type="hidden" id="viewedAgentId" value="{{ isset($agent) ? $agent->id : '' }}">

@push('scripts')
    <script>
        window.addEventListener('load', function () {

            var selectedTypes = $('.interpreter-type:checked').map(function(index, element) {
                return $(element).val().indexOf('interpreter') > -1 ? 'interpreter' : $(element).val()
            }).toArray()

            showSkills(selectedTypes)

            $('#affidavitAndAffirmation').toggleClass('d-none', selectedTypes.indexOf('translator') === -1 && selectedTypes.length > 0)

            $('.interpreter-type').change(function() {

                if ($(this).prop('checked')) {
                    selectedTypes.push(
                        $(this).val().indexOf('interpreter') > -1 ? 'interpreter' : $(this).val()
                    )
                } else {
                    selectedTypes.splice(selectedTypes.indexOf($(this).val()), 1)
                }

                $('#affidavitAndAffirmation').toggleClass('d-none', selectedTypes.indexOf('translator') === -1 && selectedTypes.length > 0)

                showSkills(selectedTypes)
            })

            function showSkills(selectedTypes) {
                if (selectedTypes.length === 0) {
                    $('.skill').removeClass('d-none')
                    return false
                }

                $('.skill').each(function(index, element) {
                    var skillWasNotSelected = !(selectedTypes.indexOf($(element).data('type')) > -1)
                    $(element).toggleClass('d-none', skillWasNotSelected)
                })
            }

            // Read context from hidden input to avoid Blade directives in JS
            const isAgent = (document.getElementById('isAgentValue')?.value === '1');
            if (isAgent) {
                $('.agent-fields input, .agent-fields select, .agent-fields textarea, .dz-hidden-input, #can_provide_affidavit, #can_provide_affirmation, .choices input')
                    .attr('disabled', true);
            }

            // Banking details API integration
            const bankingStatusEl = document.getElementById('bankingStatus');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            function setStatus(msg, type = 'info') {
                bankingStatusEl.textContent = msg;
                bankingStatusEl.classList.remove('text-danger', 'text-success');
                bankingStatusEl.classList.add(type === 'error' ? 'text-danger' : (type === 'success' ? 'text-success' : 'text-muted'));
            }

            function loadBankDetails() {
                const isAdmin = (document.getElementById('isAdminValue')?.value === '1');
                const viewedAgentId = document.getElementById('viewedAgentId')?.value;
                let endpoint = '/api/bank-account';
                if (isAdmin && viewedAgentId) {
                    endpoint = `/api/admin/agents/${viewedAgentId}/bank-account`;
                    document.getElementById('saveBankDetailsBtn').disabled = true;
                    document.getElementById('deleteBankDetailsBtn').disabled = true;
                    document.getElementById('bank_name').disabled = true;
                    document.getElementById('account_holder').disabled = true;
                    document.getElementById('account_number').disabled = true;
                    document.getElementById('sort_code').disabled = true;
                    document.getElementById('consent').disabled = true;
                } else {
                    document.getElementById('saveBankDetailsBtn').disabled = false;
                    document.getElementById('deleteBankDetailsBtn').disabled = false;
                    document.getElementById('bank_name').disabled = false;
                    document.getElementById('account_holder').disabled = false;
                    document.getElementById('account_number').disabled = false;
                    document.getElementById('sort_code').disabled = false;
                    document.getElementById('consent').disabled = false;
                }
                fetch(endpoint, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(res => res.json())
                    .then(data => {
                        if (data.exists) {
                            document.getElementById('bank_name').value = data.bank_name || '';
                            document.getElementById('account_holder').value = data.account_holder || document.getElementById('account_holder').value;
                            document.getElementById('account_number').value = data.account_number || '';
                            document.getElementById('sort_code').value = data.sort_code || '';
                            document.getElementById('consent').checked = !!data.consent;
                            setStatus('Loaded saved banking details.');
                        } else {
                            setStatus('No banking details saved yet.');
                        }
                    })
                    .catch(() => setStatus('Failed to load banking details.', 'error'));
            }

            function saveBankDetails() {
                const payload = {
                    bank_name: document.getElementById('bank_name').value,
                    account_holder: document.getElementById('account_holder').value,
                    account_number: document.getElementById('account_number').value,
                    sort_code: document.getElementById('sort_code').value,
                    consent: document.getElementById('consent').checked ? true : false,
                };
                fetch('/api/bank-account', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.errors) {
                        setStatus('Validation failed. Please check required fields.', 'error');
                    } else {
                        setStatus('Banking details saved.', 'success');
                    }
                })
                .catch(() => setStatus('Failed to save banking details.', 'error'));
            }

            function deleteBankDetails() {
                if (!confirm('This will permanently delete your banking details. Continue?')) return;
                fetch('/api/bank-account', {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || document.querySelector('meta[name=\"csrf-token\"]').getAttribute('content'),
                    },
                    credentials: 'same-origin'
                })
                .then(res => res.json())
                .then(() => setStatus('Banking details deleted.', 'success'))
                .catch(() => setStatus('Failed to delete banking details.', 'error'));
            }

            document.getElementById('saveBankDetailsBtn').addEventListener('click', saveBankDetails);
            document.getElementById('deleteBankDetailsBtn').addEventListener('click', deleteBankDetails);
            loadBankDetails();
        });
    </script>
@endpush
