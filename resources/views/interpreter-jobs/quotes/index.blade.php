@extends('layouts.app')

@section('content')
    <main class="section">
        @include('partials.job-header', ['job' => $interpreterJob, 'type' => 'interpreter-jobs'])
        @role('admin')
            <span class="col-12 alert alert-danger">Kindly ensure that Agent has sent quote for this job before sending your
                quote</span><br><br>
        @endrole
        <div class="row">
            @unlessrole('client')
                <div class="col-lg-4">
                    <h2 class="job__sub-heading">Send quote to @role('admin')
                            client
                        @else
                            A to Z admin
                        @endrole
                    </h2>

                    {{ Form::open(['route' => ['interpreter-jobs.quotes.store', $interpreterJob->id], 'class' => 'form', 'id' => 'quoteForm']) }}
                    <quote-form :interpreter-job-hours="{{ $interpreterJob->duration_hours }}"
                        :interpreter-job-minutes="{{ $interpreterJob->duration_minutes }}"
                        :job-can-be-quoted="{{ json_encode($interpreterJob->canBeQuotedByUser(auth()->user())) }}">
                    </quote-form>
                    {{ Form::close() }}

                </div>
            @endunlessrole

            <div class="col-lg-12 col-xl-12 @unlessrole('client') offset-xl-1 @endunlessrole">
                <h2 class="job__sub-heading">
                    Quotes @role('client')
                        recieved
                    @else
                        sent
                    @endrole
                </h2>
                <table class="table">
                    <thead class="table__header">
                        <tr>
                            <th class="table__heading">Date Sent</th>
                            <th class="table__heading">Total Amount</th>
                            <th class="table__heading">Status</th>
                            <th class="table__heading"></th>
                        </tr>
                    </thead>
                    <tbody>

                        @forelse ($quotes as $quote)
                            <tr class="table__row">
                                <td class="table__data table__data--grey">{{ $quote->created_at->format('d/m/Y') }}</td>
                                <td class="table__data table__data--grey">
                                    {{ $quote->totalAmount }}
                                    <button class="btn btn--grey btn--small table__btn" data-toggle="modal"
                                        data-target="#quoteModal" data-quote="{{ $quote->toJson() }}">
                                        View Quote
                                    </button>
                                </td>
                                <td class="table__data status status--{{ \Illuminate\Support\Str::kebab($quote->status) }}">
                                    {{ $quote->getStatusForUser() }}
                                </td>
                                <td class="table__data table__data--actions">

                                    @role('client')
                                        @if ($quote->status == 'quote sent')
                                            {{ Form::open(['route' => ['admin-quotes.update', $quote->id], 'method' => 'PUT', 'class' => 'd-inline-flex mb-0']) }}
                                            {{ Form::submit('Accept', ['class' => 'btn btn--primary table__btn']) }}
                                            {{ Form::close() }}

                                            {{ Form::open(['route' => ['admin-quotes.destroy', $quote->id], 'method' => 'DELETE', 'class' => 'd-inline-flex mb-0']) }}
                                            {{ Form::submit('Reject', ['class' => 'btn btn--reject table__btn']) }}
                                            {{ Form::close() }}
                                        @endif
                                    @endrole

                                    @role('admin')
                                        @if ($quote->status == 'quote sent')
                                            <button class="btn btn--secondary btn--small table__btn" data-toggle="collapse" data-target="#edit-quote-{{ $quote->id }}" aria-expanded="false" aria-controls="edit-quote-{{ $quote->id }}">Edit</button>
                                        @endif
                                    @endrole

                                    @role('agent')
                                        @if ($quote->status == 'quote sent')
                                            <button class="btn btn--secondary btn--small table__btn" data-toggle="collapse" data-target="#edit-quote-{{ $quote->id }}" aria-expanded="false" aria-controls="edit-quote-{{ $quote->id }}">Edit</button>
                                        @endif
                                    @endrole
                                </td>
                            </tr>
                            @if ($quote->status == 'quote sent')
                                <tr class="table__row">
                                    <td class="table__data text-left" colspan="4">
                                        <div id="edit-quote-{{ $quote->id }}" class="collapse">
                                            {{ Form::open(['route' => ['interpreter-jobs.quotes.update', $interpreterJob->id, $quote->id], 'method' => 'PUT', 'class' => 'form']) }}
                                                <fieldset class="form__field">
                                                    <div class="row g-2">
                                                        <div class="col-md-3 col-lg-3">
                                                            {{ Form::label('interpreting_cost', 'Cost per hour', [ 'class' => 'label form__label' ]) }}
                                                            {{ Form::number('interpreting_cost', $quote->interpreting_cost, ['class' => 'input form__input', 'min' => 0, 'max' => 9999.99, 'step' => '0.01', 'required' => true]) }}
                                                        </div>
                                                        <div class="col-md-3 col-lg-3">
                                                            {{ Form::label('travel_time', 'Travel time (hours)', [ 'class' => 'label form__label' ]) }}
                                                            {{ Form::number('travel_time', $quote->travel_time, ['class' => 'input form__input', 'min' => 0, 'max' => 9999.99, 'step' => '0.01', 'required' => true]) }}
                                                        </div>
                                                        <div class="col-md-3 col-lg-3">
                                                            {{ Form::label('travel_cost', 'Travel cost per hour', [ 'class' => 'label form__label' ]) }}
                                                            {{ Form::number('travel_cost', $quote->travel_cost, ['class' => 'input form__input', 'min' => 0, 'max' => 9999.99, 'step' => '0.01', 'required' => true]) }}
                                                        </div>
                                                        <div class="col-md-3 col-lg-3">
                                                            {{ Form::label('mileage_miles', 'Mileage (miles)', [ 'class' => 'label form__label' ]) }}
                                                            {{ Form::number('mileage_miles', $quote->mileage_miles, ['class' => 'input form__input', 'min' => 0, 'max' => 9999.99, 'step' => '0.01', 'required' => true]) }}
                                                        </div>
                                                    </div>
                                                    <div class="row g-2 mt-2">
                                                        <div class="col-md-3 col-lg-3">
                                                            {{ Form::label('mileage_cost', 'Mileage cost per mile', [ 'class' => 'label form__label' ]) }}
                                                            {{ Form::number('mileage_cost', $quote->mileage_cost, ['class' => 'input form__input', 'min' => 0, 'max' => 9999.99, 'step' => '0.01', 'required' => true]) }}
                                                        </div>
                                                        <div class="col-md-3 col-lg-3">
                                                            {{ Form::label('cost', 'Other cost', [ 'class' => 'label form__label' ]) }}
                                                            {{ Form::number('cost', $quote->cost, ['class' => 'input form__input', 'min' => 0, 'max' => 9999.99, 'step' => '0.01', 'required' => true]) }}
                                                        </div>
                                                        <div class="col-md-6 col-lg-6">
                                                            {{ Form::label('cost_description', 'Cost description', [ 'class' => 'label form__label' ]) }}
                                                            {{ Form::text('cost_description', $quote->cost_description, ['class' => 'input form__input', 'required' => true]) }}
                                                        </div>
                                                    </div>
                                                </fieldset>
                                                {{ Form::submit('Save Changes', [ 'class' => 'btn btn--secondary mt-2' ]) }}
                                            {{ Form::close() }}
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr class="table__row">
                            <tr class="table__row">
                                <td class="table__data table__data--grey" colspan="4">
                                    @role('client')
                                        No quotes have been recieved for this job yet.
                                    @else
                                        You have not sent any quotes for this job yet.
                                    @endrole
                                </td>
                            </tr>
                        @endforelse

                    </tbody>
                </table>
            </div>
        </div>
    </main>

@endsection

@include('partials.quote-modal')
