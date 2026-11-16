{{ Form::open(['method' => 'GET', 'class' => 'filter', 'id' => 'filterForm']) }}
    <div class="section__left">

        {{ Form::select('language_id', $languages, request('language_id'), [ 
                'class' => 'input input--select mb-3 mb-lg-0 section__input', 
                'placeholder' => 'Filter By Language...' 
            ]) 
        }}

        @role('admin')
            {{ Form::select('client', $clients, request('client'), [ 
                    'class' => 'input input--select mb-3 mb-lg-0 section__input', 
                    'placeholder' => 'Filter By Client...' 
                ])
            }}

            {{ Form::select('company', $companies, request('company'), [ 
                    'class' => 'input input--select mb-3 mb-lg-0 section__input', 
                    'placeholder' => 'Filter By Company...' 
                ])
            }}
             
        @endrole

        {{ Form::select('status', $statuses, request('status'), [ 
                'class' => 'input input--select mb-3 mb-lg-0 section__input section__input--middle', 
                'placeholder' => 'Filter By Status...' 
            ])
        }}

    </div>

        @include('partials.filters.date-range', [
            'mode' => 'single',
            'formId' => 'filterForm',
            'inputId' => 'dateFilter',
            'paramName' => 'date',
            'placeholder' => 'Filter By Date...',
            'submitWhen' => 'change'
        ])
    
{{ Form::close() }}


@push('scripts')
    <!-- Date range picker is now initialized via shared component -->
@endpush