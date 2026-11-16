<form class="filter" id="agentFilterForm">
    <div class="d-flex">
        {{ Form::text('name', request('name'), [
            'class' => 'input filter__input',
            'placeholder' => 'Filter By Name...',
        ]) }}
        {{ Form::text('email', request('email'), [
            'class' => 'input filter__input ml-2',
            'placeholder' => 'Filter By Email...',
        ]) }}
        {{ Form::select('language', $languages, request('language'), [
            'class' => 'input input--select filter__input ml-2',
            'placeholder' => 'Filter By Language...',
        ]) }}
        <input type="submit" hidden>
    </div>
    {{ Form::select('agent_type', App\Agent::$agentTypes, request('agent_type'), [
        'class' => 'input input--select filter__input',
        'placeholder' => 'Filter By Agent Type...',
    ]) }}
</form>

@push('scripts')
    <script>
        window.addEventListener('load', function() {
            $('.filter select').change(function() {
                console.log('select changed')
                $('#agentFilterForm').submit()
            })
        })
    </script>
@endpush
