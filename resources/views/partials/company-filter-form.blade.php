{{ Form::open(['method' => 'GET', 'class' => 'filter', 'id' => 'filterForm']) }}
    <div class="section__left">

        {{ Form::text('name', request('name'), [
                'class' => 'input filter__input',
                'placeholder' => 'Filter By Name...'
            ])
        }}

    </div>

{{ Form::close() }}
