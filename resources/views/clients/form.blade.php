<div class="row">
    <?php $isOrg = isset($client) ? (request()->has('organisation') ? (bool) request('organisation') : !is_null($client->organisation)) : (bool) request('organisation'); ?>

{{--    @if (isset($client))--}}
{{--        {{ Form::hidden('is_organisation', $client->organisation ? 1 : 0) }}--}}
{{--    @else--}}
        <div class="col-12">
            <fieldset class="form__field mb-5">

                {{ Form::label('is_organisation', 'Type of client', ['class' => 'label form__label mb-3 required']) }}

                <ul class="list d-flex" id="radioButtons">
                    <li class="form__item mr-3">

                        @if(isset($client))
                            {{ Form::radio('is_organisation', 0, !$isOrg,
                            ['id' => 'isNotOrganisation', 'class' => 'form__radio', 'data-link' => route('clients.edit',['client' => $client->id, 'organisation' => false])]) }}
                        @else
                            {{ Form::radio('is_organisation', 0, !$isOrg,
                            ['id' => 'isNotOrganisation', 'class' => 'form__radio', 'data-link' => route('clients.create') ]) }}
                        @endif
                        {{ Form::label('isNotOrganisation', 'Individual', ['class' => 'label form__label form__label--radio text-gray']) }}

                    </li>
                    <li class="form__item">

                        @if(isset($client))
                            {{ Form::radio('is_organisation', 1, $isOrg, ['id' => 'isOrganisation', 'class' => 'form__radio', 'data-link' => route('clients.edit', ['client' => $client->id, 'organisation' => true])]) }}
                        @else
                            {{ Form::radio('is_organisation', 1, $isOrg, ['id' => 'isOrganisation', 'class' => 'form__radio', 'data-link' => route('clients.create', ['organisation' => true])]) }}
                        @endif
                        {{ Form::label('isOrganisation', 'Company', ['class' => 'label form__label form__label--radio text-gray']) }}

                    </li>
                </ul>
            </fieldset>
        </div>
{{--    @endif--}}

    <div class="col-lg-4">
        <fieldset class="form__field">

            {{ Form::label('title', 'Profile information', ['class' => 'label form__label required']) }}

            <div class="form__inputs">

                {{ Form::select('title', config('enums.titles'), isset($client) ? $client->user->title : null, [
                        'class' => 'input input--select form__input form__input--title',
                        'placeholder' => 'Title',
                        'required' => 'required'
                    ])
                }}

                {{ Form::text('first_name', isset($client) ? $client->user->first_name : null, [
                        'class' => 'input form__input',
                        'placeholder' => 'First name',
                        'required' => 'required'
                    ])
                }}

            </div>

            {{ Form::text('last_name', isset($client) ? $client->user->last_name : null, [
                    'class' => 'input form__input',
                    'placeholder' => 'Last name',
                    'required' => 'required'
                ])
            }}

            {{ Form::email('email', isset($client) ? $client->user->email : null, [
                    'class' => 'input form__input',
                    'placeholder' => 'Email address',
                    'required' => 'required'
                ])
            }}

            @role('client')
                {{ Form::password('password', ['class' => 'input form__input', 'placeholder' => 'Password']) }}
            @endrole

            {{ Form::text('contact_number', null, [
                    'class' => 'input form__input',
                    'placeholder' => 'Contact number',
                    'required' => 'required'
                ])
            }}
        </fieldset>
        <fieldset class="form__field" id="account_address_fieldset" style="display:block">
            {{ Form::label('client_address_line_1', 'Account address', ['class' => 'label form__label required']) }}

            {{ Form::text('client_address_line_1', null, [
                    'class' => 'input form__input',
                    'placeholder' => 'Address line 1',
                    'required' => 'required',
                    'id' => 'client_address_line_1'
                ])
            }}

            {{ Form::text('client_address_line_2', null, [
                    'class' => 'input form__input',
                    'placeholder' => 'Address line 2',
                    'id' => 'client_address_line_2'
                ])
            }}

            {{ Form::text('client_county', null, [
                    'class' => 'input form__input',
                    'placeholder' => 'County',
                    'required' => 'required',
                    'id' => 'client_county'
                ])
            }}

            {{ Form::text('client_postcode', null, [
                    'class' => 'input form__input w-50',
                    'placeholder' => 'Postcode',
                    'required' => 'required',
                    'id' => 'client_postcode'
                ])
            }}

        </fieldset>
    </div>
    <div class="col-lg-4">

        @if ($isOrg)

            <fieldset class="form__field">
                {{ Form::label('organisation_company', 'Company name', ['class' => 'label form__label required']) }}
                {{ Form::text('organisation_company', isset($client) && isset($client->organisation) ? $client->organisation->organisation_company : null, [
                        'class' => 'input form__input',
                        'placeholder' => 'Company name',
                        'required' => 'required',
                        'id' => 'organisation_company'
                    ])
                }}
            </fieldset>
            <fieldset class="form__field">
                {{ Form::label('vat_number', 'VAT number', ['class' => 'label form__label']) }}
                {{ Form::text('vat_number', isset($client) && isset($client->organisation) ? $client->organisation->vat_number : null, [
                        'class' => 'input form__input',
                        'placeholder' => 'VAT Number',
                        'id' => 'vat_number'
                    ])
                }}
            </fieldset>
            <fieldset class="form__field">
                {{ Form::label('company_number', 'Company number', ['class' => 'label form__label required']) }}
                {{ Form::text('company_number', isset($client) && isset($client->organisation) ? $client->organisation->company_number : null, [
                        'class' => 'input form__input',
                        'placeholder' => 'Company number',
                        'required' => 'required',
                        'id' => 'company_number'
                    ])
                }}
            </fieldset>

            @role('admin')
                <fieldset class="form__field">
                    {{ Form::label('company_id', 'Assign to a company group', ['class' => 'label form__label']) }}
                    {{ Form::select('company_id', $companies, isset($client) && isset($client->organisation) ? $client->organisation->company_id : null, [
                            'class' => 'input input--select form__input',
                            'placeholder' => 'Select Company group'
                        ])
                    }}
                </fieldset>
            @endrole

        @else
            <fieldset class="form__field">

                {{ Form::label('contact_method', 'Contact preferences', [
                        'class' => 'label form__label mb-3'
                    ])
                }}

                <ul class="list d-flex">
                    @foreach ($contactMethods as $key => $contactMethod)
                        <li class="form__item form__item--method {{ ($key + 1) != count($contactMethods) ? 'mr-3' : '' }}">

                            {{ Form::checkbox('contact_method[]', $contactMethod->id, isset($client) ? $client->contactMethods->contains($contactMethod) : null, ['class' => 'form__radio', 'id' => 'contact_method_' . $key]) }}

                            {{ Form::label('contact_method_' . $key, $contactMethod->contact_method, [
                                    'class' => 'label form__label form__label--radio'
                                ])
                            }}

                        </li>
                    @endforeach
                </ul>

            </fieldset>
            <fieldset class="form__field">
                {{ Form::label('always_requires_a_quote', 'Quote preference', ['class' => 'label mb-3 form__label']) }}
                <div class="d-flex align-items-center">
                    {{ Form::hidden('always_requires_a_quote', '0', ['id' => 'hidden_quote']) }}
                    {{ Form::checkbox('always_requires_a_quote', '1', null, [
                            'class' => 'form__radio',
                            'id' => 'always_requires_a_quote'
                        ])
                    }}
                    {{ Form::label('always_requires_a_quote', 'Always require a quote', ['class' => 'label m-0']) }}
                </div>
            </fieldset>
        @endif

    </div>

    @if ($isOrg)
        <div class="col-lg-4">
            <fieldset class="form__field" id="invoiceInputs">

                <div class="d-flex justify-content-between form__label">
                    {{ Form::label('sms_method', 'Invoice address', ['class' => 'label form__label mb-0 required']) }}
                    <div class="d-flex align-items-center">
                        {{ Form::hidden('invoice_details_same_as_account', 0, null, ['id' => 'hidden_invoice_details_same_as_account']) }}
                        {{ Form::label('invoice_details_same_as_account', 'Same as account', ['class' => 'label text-gray mb-0 mr-3 form__label']) }}
                        {{ Form::checkbox('invoice_details_same_as_account', 1, null) }}
                    </div>
                </div>

                {{ Form::text('organisation_address_line_1', isset($client) && isset($client->organisation) ? $client->organisation->organisation_address_line_1 : null, [
                        'class' => 'input form__input',
                        'placeholder' => 'Address line 1',
                        'required' => 'required',
                        'id' => 'organisation_address_line_1'
                    ])
                }}

                {{ Form::text('organisation_address_line_2', isset($client) && isset($client->organisation) ? $client->organisation->organisation_address_line_2 : null, [
                        'class' => 'input form__input',
                        'placeholder' => 'Address line 2',
                        'id' => 'organisation_address_line_2'
                    ])
                }}

                {{ Form::text('organisation_county', isset($client) && isset($client->organisation) ? $client->organisation->organisation_county : null, [
                        'class' => 'input form__input',
                        'placeholder' => 'County',
                        'required' => 'required',
                        'id' => 'organisation_county'
                    ])
                }}

                {{ Form::text('organisation_postcode', isset($client) && isset($client->organisation) ? $client->organisation->organisation_postcode : null, [
                        'class' => 'input form__input w-50',
                        'placeholder' => 'Postcode',
                        'required' => 'required',
                        'id' => 'organisation_postcode'
                    ])
                }}

            </fieldset>
            <fieldset class="form__field">
                <div class="d-flex justify-content-between form__label">
                    {{ Form::label('sms_method', 'Invoice email address', ['class' => 'label form__label mb-0 required']) }}
                    <div class="d-flex align-items-center">
                        {{ Form::hidden('invoice_email_same_as_account', 0, null, ['id' => 'hidden_invoice_email_same_as_account']) }}
                        {{ Form::label('invoice_email_same_as_account', 'Same as account', ['class' => 'label text-gray mb-0 mr-3 form__label']) }}
                        {{ Form::checkbox('invoice_email_same_as_account', 1, null) }}
                    </div>
                </div>

                {{ Form::email('organisation_email', isset($client) && isset($client->organisation) ? $client->organisation->organisation_email : null, [
                        'class' => 'input form__input',
                        'placeholder' => 'Invoice Email address',
                        'required' => 'required',
                        'id' => 'invoiceEmailAddress'
                    ])
                }}
            </fieldset>
            <fieldset class="form__field">

                {{ Form::label('contact_method', 'Contact preferences', [
                        'class' => 'label form__label mb-3'
                    ])
                }}

                <ul class="list d-flex">
                    @foreach ($contactMethods as $key => $contactMethod)
                        <li class="form__item form__item--method {{ ($key + 1) != count($contactMethods) ? 'mr-3' : '' }}">

                            {{ Form::checkbox('contact_method[]', $contactMethod->id, isset($client) && isset($client->organisation) ? $client->contactMethods->contains($contactMethod) : null, ['class' => 'form__radio', 'id' => 'contact_method_' . $key]) }}

                            {{ Form::label('contact_method_' . $key, $contactMethod->contact_method, [
                                    'class' => 'label form__label form__label--radio'
                                ])
                            }}

                        </li>
                    @endforeach
                </ul>

            </fieldset>
            <fieldset class="form__field">
                {{ Form::label('always_requires_a_quote', 'Quote preference', ['class' => 'label mb-3 form__label']) }}
                <div class="d-flex align-items-center">
                    {{ Form::hidden('always_requires_a_quote', '0', ['id' => 'hidden_quote']) }}
                    {{ Form::checkbox('always_requires_a_quote', '1', null, [
                            'class' => 'form__radio',
                            'id' => 'always_requires_a_quote'
                        ])
                    }}
                    {{ Form::label('always_requires_a_quote', 'Always require a quote', ['class' => 'label m-0']) }}
                </div>
            </fieldset>
        </div>
    @endif

</div>

@push('scripts')
    <script>
        ;(function initInvoiceToggles(){
            function run(){
                var $invoiceInputs = $('#invoiceInputs input');
                var $sameAsAccount = $('#invoice_details_same_as_account');
                var $invoiceEmail = $('#invoiceEmailAddress');
                var $emailSameAs = $('#invoice_email_same_as_account');
                if ($invoiceInputs.length && $sameAsAccount.length) {
                    $invoiceInputs.not($sameAsAccount).attr('disabled', $sameAsAccount.prop('checked'));
                    $sameAsAccount.off('change.toggleInv').on('change.toggleInv', function(){
                        $invoiceInputs.not($(this)).attr('disabled', $(this).prop('checked'));
                    });
                }
                if ($invoiceEmail.length && $emailSameAs.length) {
                    $invoiceEmail.attr('disabled', $emailSameAs.prop('checked'));
                    $emailSameAs.off('change.toggleEmail').on('change.toggleEmail', function(){
                        $invoiceEmail.attr('disabled', $(this).prop('checked'));
                    });
                }
            }
            try {
                if (typeof window.addEventListener === 'function') {
                    window.addEventListener('load', run, { once: true });
                } else {
                    // Fallback if addEventListener is overridden by extensions or not available
                    $(run);
                }
            } catch(e) {
                $(run);
            }
        })();

        (function(){
            var getUrl = "{{ route('api.companies.show.get', ['company' => '__ID__']) }}";
            var postUrl = "{{ route('api.companies.show', ['company' => '__ID__']) }}";
            function fill(data){
                function setVal(id, name, value){
                    var $el = $('#' + id);
                    if (!$el.length) { $el = $('[name="' + name + '"]'); }
                    if ($el && $el.length) { $el.val(value || ''); }
                }
                if (data.company_number) { setVal('company_number', 'company_number', data.company_number); }
                if (data.address_line_1) { setVal('organisation_address_line_1', 'organisation_address_line_1', data.address_line_1); }
                if (data.address_line_2) { setVal('organisation_address_line_2', 'organisation_address_line_2', data.address_line_2); }
                if (data.county) { setVal('organisation_county', 'organisation_county', data.county); }
                if (data.postcode) { setVal('organisation_postcode', 'organisation_postcode', data.postcode); }
                if (data.vat_number) { setVal('vat_number', 'vat_number', data.vat_number); }
                (function(){
                    if (!data.email) { return; }
                    var $invoiceEmail = $('#invoiceEmailAddress');
                    var $emailSameAs = $('#invoice_email_same_as_account');
                    var shouldSet = false;
                    if ($emailSameAs.length && $emailSameAs.prop('checked')) {
                        shouldSet = true;
                    } else if ($invoiceEmail.length) {
                        var current = ($invoiceEmail.val() || '').trim();
                        shouldSet = current === '';
                    }
                    if (shouldSet) {
                        setVal('invoiceEmailAddress', 'organisation_email', data.email);
                    }
                })();
                if (data.name) { setVal('organisation_company', 'organisation_company', data.name); }
                if (data.address_line_1) { setVal('client_address_line_1', 'client_address_line_1', data.address_line_1); }
                if (data.address_line_2) { setVal('client_address_line_2', 'client_address_line_2', data.address_line_2); }
                if (data.county) { setVal('client_county', 'client_county', data.county); }
                if (data.postcode) { setVal('client_postcode', 'client_postcode', data.postcode); }
            }
            function fetchCompany(companyId){
                if (!companyId) return;
                var url = getUrl.replace('__ID__', companyId);
                var done = false;
                if (window.fetch) {
                    fetch(url, { credentials: 'same-origin' })
                        .then(function(r){ if(!r.ok) throw new Error('e'); return r.json(); })
                        .then(function(data){ done = true; fill(data); })
                        .catch(function(){
                            var alt = postUrl.replace('__ID__', companyId);
                            fetch(alt, { method: 'POST', credentials: 'same-origin' })
                                .then(function(r){ if(!r.ok) throw new Error('e'); return r.json(); })
                                .then(function(data){ fill(data); })
                                .catch(function(){});
                        });
                } else if (window.$ && $.ajax) {
                    $.ajax({ method: "GET", url: url })
                        .done(function(data){ done = true; fill(data); })
                        .fail(function(){
                            $.ajax({ method: "POST", url: postUrl.replace('__ID__', companyId) })
                                .done(function(data){ fill(data); })
                                .fail(function(){});
                        });
                }
            }
            function onChange(){ fetchCompany($(this).val()); }
            var select = $('#company_id');
            if (select.length === 0) { select = $('[name="company_id"]'); }
            select.off('change.companyFill').on('change.companyFill', onChange);
            $('#organisation_company').off('blur.matchCompany').on('blur.matchCompany', function(){
                var typed = ($(this).val() || '').trim().toLowerCase();
                if (!typed) return;
                var matchVal = null;
                select.find('option').each(function(){
                    var text = ($(this).text() || '').trim().toLowerCase();
                    if (text === typed) { matchVal = $(this).val(); return false; }
                });
                if (matchVal) { select.val(matchVal).trigger('change'); }
            });
            var initialCompanyId = select.val();
            if (initialCompanyId) { fetchCompany(initialCompanyId); }
        })();
    </script>
@endpush
