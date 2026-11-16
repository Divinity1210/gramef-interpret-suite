{{ Form::open(['method' => 'GET', 'class' => 'filter', 'id' => 'filterForm']) }}
<div class="section__left" style="display: flex;
    flex-wrap: wrap; 
    gap: 5px;">

    {{ Form::select('language_id', $languages, request('language_id'), [
    'class' => 'input input--select mb-3 mb-lg-0 section__input',
    'placeholder' => 'Filter By Language...',
]) }}

    {{-- {{ Form::select('bulk_id', $bulk_ids, request('bulk_id'), [
    'class' => 'input input--select mb-3 mb-lg-0 section__input',
    'placeholder' => 'Filter by Bulk ID...',
    ]) }} --}}
    @role('admin')
    {{ Form::select('client', $clients, request('client'), [
    'class' => 'input input--select mb-3 mb-lg-0 section__input',
    'placeholder' => 'Filter By Client...'
]) }}

    {{ Form::select('company', $companies, request('company'), [
    'class' => 'input input--select mb-3 mb-lg-0 section__input',
    'placeholder' => 'Filter By Company...'
]) }}
    {{ Form::select('require_qualified', $require_qualified, request('require_qualified'), [
    'class' => 'input input--select mb-3 mb-lg-0 section__input',
    'placeholder' => 'Filter By Agent Type...'
]) }}
    {{ Form::select('agents', $agents, request('agents'), [
    'class' => 'input input--select mb-3 mb-lg-0 section__input',
    'placeholder' => 'Filter By Agent...'
]) }}

    {{-- Timesheet: Verified (paper workflow) filter for finance/admin --}}
    {{ Form::select(
    'timesheet_verified',
    [
        '' => 'Timesheet: Verified (paper)...',
        '1' => 'Verified',
        '0' => 'Not Verified',
    ],
    request('timesheet_verified'),
    [
        'class' => 'input input--select mb-3 mb-lg-0 section__input',
    ]
) }}
    @endrole

    {{ Form::select('status', $statuses, request('status'), [
    'class' => 'input input--select mb-3 mb-lg-0 section__input section__input--middle',
    'placeholder' => 'Filter By Status...'
]) }}

    {{ Form::select('sort', [
    'appointment_date_asc' => 'Sort: Upcoming First',
    'appointment_date_desc' => 'Sort: Latest Appointments',
    'created_at_desc' => 'Sort: Newest Created',
    'created_at_asc' => 'Sort: Oldest Created',
], request('sort', isset($current_sort) ? $current_sort : null), [
    'class' => 'input input--select mb-3 mb-lg-0 section__input',
]) }}

    @role('admin')
    {{ Form::select(
    'dna',
    [
        '' => 'Filter By Job DNA...',
        '1' => 'DNA', // Jobs marked as DNA
        '0' => 'Not DNA', // Jobs not marked as DNA
    ],
    request('dna'),
    [
        'class' => 'input input--select mb-3 mb-lg-0 section__input'
    ]
) }}
    {{ Form::select(
    'retrn',
    [
        '' => 'Filter By Job Return...',
        '1' => 'Return', // Jobs marked as DNA
        '0' => 'Not Return', // Jobs not marked as DNA
    ],
    request('retrn'),
    [
        'class' => 'input input--select mb-3 mb-lg-0 section__input'
    ]
) }}
    @endrole
    @include('partials.filters.date-range', [
        'mode' => 'single',
        'formId' => 'filterForm',
        'inputId' => 'dateFilter',
        'paramName' => 'date',
        'placeholder' => 'Filter By Date...',
        'submitWhen' => 'change'
    ])
</div>

{{ Form::close() }}


@push('scripts')
    <!-- Date range picker is now initialized via shared component -->
@endpush
