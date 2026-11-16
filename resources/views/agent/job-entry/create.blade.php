@extends('layouts.app')

@section('content')
    <main class="section">
        <div class="section__top d-block d-lg-flex">
            <div class="section__heading">
                Job Entry - {{ ucfirst($jobType) }} Job #{{ $job->id }}
            </div>
            <div class="d-flex">
                <a href="{{ route('agent.job-entry.pending') }}" class="btn btn--cancel az-btn az-btn-secondary" title="Back to Pending Entries">
                    Back to Pending Jobs
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <!-- Job Details Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Job Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Job ID:</strong> {{ $job->id }}/atoz</p>
                                <p><strong>Type:</strong> {{ ucfirst($jobType) }}</p>
                                @if($job->client)
                                    <p><strong>Client:</strong> {{ $job->client->user->getFullName() }}</p>
                                @endif
                            </div>
                            <div class="col-md-6">
                                @if($job->date_required)
                                    <p><strong>Date Required:</strong> {{ \Carbon\Carbon::parse($job->date_required)->format('d/m/Y') }}</p>
                                @endif
                                @if($job->time_required)
                                    <p><strong>Time Required:</strong> {{ $job->time_required }}</p>
                                @endif
                                @if($job->toLanguage)
                                    <p><strong>Language:</strong> {{ $job->toLanguage->name }}</p>
                                @endif
                            </div>
                        </div>
                        @if($job->description)
                            <div class="mt-3">
                                <p><strong>Description:</strong></p>
                                <p class="text-muted">{{ $job->description }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Job Entry Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            @if($existingEntry)
                                Update Job Entry
                            @else
                                Enter Job Amounts
                            @endif
                        </h5>
                    </div>
                    <div class="card-body">
                        @if($existingEntry)
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                You have already entered amounts for this job. You can update them below.
                            </div>
                        @endif

                        {{ Form::open([
                            'route' => ['agent.job-entry.store', $jobType, $job->id],
                            'class' => 'form',
                            'method' => 'POST'
                        ]) }}

                        <div class="row">
                            <div class="col-md-6">
                                <fieldset class="form__field">
                                    {{ Form::label('base_amount', 'Base Amount (£)', ['class' => 'label form__label required']) }}
                                    {{ Form::number('base_amount', $existingEntry->base_amount ?? old('base_amount'), [
                                        'class' => 'input form__input',
                                        'placeholder' => '0.00',
                                        'step' => '0.01',
                                        'min' => '0',
                                        'required' => 'required',
                                        'id' => 'base_amount'
                                    ]) }}
                                    @error('base_amount')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </fieldset>
                            </div>

                            <div class="col-md-6">
                                <fieldset class="form__field">
                                    <label class="label form__label">Expenses</label>
                                    <div id="expenses_list">
                                        <!-- Dynamic expense rows will be appended here -->
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary mt-2 az-btn az-btn-outline" id="add_expense_btn" title="Add another expense row">Add Expense</button>
                                    <!-- Hidden total field submitted to backend -->
                                    <input type="hidden" name="expenses_amount" id="expenses_amount" value="{{ $existingEntry->expenses ?? 0 }}">
                                    @error('expenses_amount')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </fieldset>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <fieldset class="form__field">
                                    <label class="label form__label">Surcharge (£)</label>
                                    <div class="text-muted small">Surcharges are added by admin during invoice review.</div>
                                    <input type="hidden" name="surcharge" id="surcharge" value="0">
                                </fieldset>
                            </div>

                            <div class="col-md-6">
                                <fieldset class="form__field">
                                    {{ Form::label('other_charges', 'Other Charges (£)', ['class' => 'label form__label']) }}
                                    {{ Form::number('other_charges', $existingEntry->other_charges ?? old('other_charges'), [
                                        'class' => 'input form__input',
                                        'placeholder' => '0.00',
                                        'step' => '0.01',
                                        'min' => '0',
                                        'id' => 'other_charges'
                                    ]) }}
                                    @error('other_charges')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </fieldset>
                            </div>
                        </div>

                        <fieldset class="form__field">
                            {{ Form::label('notes', 'Notes (Optional)', ['class' => 'label form__label']) }}
                            {{ Form::textarea('notes', $existingEntry->notes ?? old('notes'), [
                                'class' => 'input form__input',
                                'placeholder' => 'Add any additional notes or description for this job entry...',
                                'rows' => 3,
                                'maxlength' => 500
                            ]) }}
                            @error('notes')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </fieldset>

                        <!-- Total Amount Display -->
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="mb-0">Total Amount:</h6>
                                    </div>
                                    <div class="col-auto">
                                        <h4 class="mb-0 text-primary" id="total_amount">£0.00</h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form__actions mt-4">
                            <button type="submit" class="btn btn--primary az-btn az-btn-primary" title="Save entry">Save</button>
                            <a href="{{ route('agent.job-entry.pending') }}" class="btn btn--cancel ml-2 az-btn az-btn-secondary" title="Cancel and return">Cancel</a>
                        </div>

                        {{ Form::close() }}
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Instructions</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-info-circle text-info mr-2"></i>
                                Enter the base amount you wish to charge for this job.
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-receipt text-info mr-2"></i>
                                Add any expenses incurred (travel, materials, etc.).
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-plus text-info mr-2"></i>
                                Include surcharges or other additional charges if applicable.
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-save text-success mr-2"></i>
                                Your entry will be saved to your pending jobs list.
                            </li>
                            <li>
                                <i class="fas fa-file-invoice text-warning mr-2"></i>
                                Pending jobs will be included in your next monthly invoice.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script type="application/json" id="existingItemsData">
        {!! json_encode(
            $existingEntry
                ? $existingEntry->expensesItems()->get(['type','amount'])->map(function($i){
                    return ['type' => $i->type, 'value' => $i->amount];
                })->toArray()
                : []
        ) !!}
    </script>
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const baseAmount = document.getElementById('base_amount');
            const expensesAmountHidden = document.getElementById('expenses_amount');
            const surcharge = document.getElementById('surcharge');
            const otherCharges = document.getElementById('other_charges');
            const totalAmount = document.getElementById('total_amount');
            const expensesList = document.getElementById('expenses_list');
            const addExpenseBtn = document.getElementById('add_expense_btn');

            function addExpenseRow(type = '', value = '') {
                const row = document.createElement('div');
                row.className = 'd-flex align-items-center mb-2';
                row.innerHTML = `
                    <input type="text" name="expenses_details[][type]" class="input form__input mr-2" placeholder="Expense Type (e.g., Parking, Travel)" value="${type}">
                    <input type="number" name="expenses_details[][value]" class="input form__input mr-2 expense-value" placeholder="0.00" step="0.01" min="0" value="${value}">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-expense az-btn az-btn-danger" title="Remove this expense row">Remove</button>
                `;
                expensesList.appendChild(row);

                row.querySelector('.expense-value').addEventListener('input', calculateTotal);
                row.querySelector('.remove-expense').addEventListener('click', function() {
                    row.remove();
                    calculateTotal();
                });
            }

            addExpenseBtn.addEventListener('click', function() {
                addExpenseRow();
            });

            function calculateTotal() {
                const base = parseFloat(baseAmount.value) || 0;
                const expenses = Array.from(document.querySelectorAll('.expense-value'))
                    .reduce((sum, input) => sum + (parseFloat(input.value) || 0), 0);
                expensesAmountHidden.value = expenses.toFixed(2);
                const sur = parseFloat(surcharge.value) || 0;
                const other = parseFloat(otherCharges.value) || 0;
                
                const total = base + expenses + sur + other;
                totalAmount.textContent = '£' + total.toFixed(2);
            }

            // Calculate total on input change
            [baseAmount, otherCharges].forEach(input => {
                input.addEventListener('input', calculateTotal);
            });

            // Pre-populate rows from stored expense breakdown; fallback to one blank row
            const existingItemsEl = document.getElementById('existingItemsData');
            const existingItems = existingItemsEl ? JSON.parse(existingItemsEl.textContent || '[]') : [];
            if (Array.isArray(existingItems) && existingItems.length > 0) {
                existingItems.forEach(function(item){
                    addExpenseRow(item.type || '', item.value || '');
                });
            } else {
                addExpenseRow();
            }
            // Calculate initial total
            calculateTotal();
        });
    </script>
    @endpush
@endsection