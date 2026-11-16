@extends('layouts.app')

@section('content')

    <main class="section">

        @include('partials.job-header', [ 'job' => $translatorJob, 'type' => 'translator-jobs'  ])

        <div class="row">

            @unlessrole('client')
                <div class="col-lg-4 col-xl-3">
                    {{ Form::open([ 'route' => ['translator-jobs.quotes.store', $translatorJob->id], 'class' => 'form' ]) }}

                        <h2 class="job__sub-heading">
                            Send quote to @role('admin') client @else A to Z admin @endrole
                        </h2>

                        <fieldset class="form__field">

                            {{ Form::label('cost', 'Translator cost:', [ 'class' => 'label form__label' ]) }}
                            <div class="form__cost">
                                {{ Form::number('cost', null, [
                                        'class' => 'input form__input form__input--cost mb-3',
                                        'min' => 0,
                                        'max' => 9999.99,
                                        'step' => '0.01',
                                        'required' => 'required'
                                    ])
                                }}
                            </div>

                            {{ Form::label('cost_description', 'Translator cost description:', [ 'class' => 'label form__label' ]) }}
                            {{ Form::text('cost_description', null, [ 'class' => 'input form__input mb-3', 'required' => 'required' ]) }}

                        </fieldset>

                        @if ($translatorJob->canBeQuotedByUser(auth()->user()))
                            {{ Form::submit('Send Quote', [ 'class' => 'btn btn--secondary' ]) }}
                        @endif

                    {{ Form::close() }}
                </div>
            @endunlessrole

            <div class="col-lg-8 col-xl-8 @unlessrole('client') offset-xl-1 @endunlessrole">
                <h2 class="job__sub-heading">
                    Quotes @role('client') recieved @else sent @endrole
                </h2>
                <table class="table">
                    <thead class="table__header">
                        <tr>
                            <th class="table__heading">Amount</th>
                            <th class="table__heading">Description</th>
                            <th class="table__heading">Date Sent</th>
                            <th class="table__heading">Status</th>
                            <th class="table__heading"></th>
                        </tr>
                    </thead>
                    <tbody>

                        @forelse ($quotes as $quote)
                            <tr class="table__row">
                                <td class="table__data table__data--grey">
                                    <div>£{{ $quote->cost }}</div>
                                </td>
                                <td class="table__data table__data--grey">
                                    <div>{{ $quote->cost_description }}</div>
                                </td>
                                <td class="table__data table__data--grey">{{ $quote->created_at->format('d/m/Y') }}</td>

                                @unlessrole('client')
                                    <td class="table__data status status--{{ \Illuminate\Support\Str::kebab($quote->status) }}">
                                        {{ $quote->status }}
                                    </td>
                                @else
                                    <td class="table__data {{ $quote->status == 'quote sent' ? 'table__data--actions' : '' }} status status--{{ \Illuminate\Support\Str::kebab($quote->status) }}">

                                        @if ($quote->status == 'quote sent')
                                            {{ Form::open([
                                                    'route' => ['admin-quotes.update', $quote->id],
                                                    'method' => 'PUT',
                                                    'class' => 'd-inline-block',
                                                ])
                                            }}
                                                <button type="submit" class="btn btn--primary table__btn">Accept</button>
                                            {{ Form::close() }}

                                            {{ Form::open([
                                                    'route' => ['admin-quotes.destroy', $quote->id],
                                                    'method' => 'DELETE',
                                                    'class' => 'd-inline-block',
                                                ])
                                            }}
                                                <button type="submit" class="btn btn--reject table__btn">Reject</button>
                                            {{ Form::close() }}
                                        @else
                                            {{ $quote->status }}
                                        @endif

                                    </td>
                                @endunlessrole

                                @role('admin')
                                    <td class="table__data table__data--actions">
                                        @if ($quote->status == 'quote sent')
                                            <button class="btn btn--secondary btn--small table__btn" data-toggle="collapse" data-target="#edit-quote-{{ $quote->id }}" aria-expanded="false" aria-controls="edit-quote-{{ $quote->id }}">Edit</button>
                                        @endif
                                    </td>
                                @endrole

                                @role('agent')
                                    <td class="table__data table__data--actions">
                                        @if ($quote->status == 'quote sent')
                                            <button class="btn btn--secondary btn--small table__btn" data-toggle="collapse" data-target="#edit-quote-{{ $quote->id }}" aria-expanded="false" aria-controls="edit-quote-{{ $quote->id }}">Edit</button>
                                        @endif
                                    </td>
                                @endrole

                            </tr>
                            @if ($quote->status == 'quote sent')
                                <tr class="table__row">
                                    <td class="table__data text-left" colspan="5">
                                        <div id="edit-quote-{{ $quote->id }}" class="collapse">
                                            {{ Form::open([ 'route' => ['translator-jobs.quotes.update', $translatorJob->id, $quote->id], 'method' => 'PUT', 'class' => 'form' ]) }}
                                                <fieldset class="form__field">
                                                    {{ Form::label('cost', 'Translator cost:', [ 'class' => 'label form__label' ]) }}
                                                    <div class="form__cost">
                                                        {{ Form::number('cost', $quote->cost, [ 'class' => 'input form__input form__input--cost mb-3', 'min' => 0, 'max' => 9999.99, 'step' => '0.01', 'required' => true ]) }}
                                                    </div>
                                                    {{ Form::label('cost_description', 'Translator cost description:', [ 'class' => 'label form__label' ]) }}
                                                    {{ Form::text('cost_description', $quote->cost_description, [ 'class' => 'input form__input mb-3', 'required' => true ]) }}
                                                </fieldset>
                                                {{ Form::submit('Save Changes', [ 'class' => 'btn btn--secondary' ]) }}
                                            {{ Form::close() }}
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr class="table__row">
                                <td class="table__data table__data--grey" colspan="5">
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
