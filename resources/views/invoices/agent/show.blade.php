@extends('layouts.app')

@section('content')
    <div class="container-fluid px-2">
        <div class="row justify-content-center">
            <div class="col-12" style="max-width: 1600px;">
                <div class="card">
                    <div
                        class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                        <h4 class="mb-2 mb-md-0">Invoice Details #{{ $invoice->invoice_number }}</h4>
                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <a href="{{ route('invoices.agent.index') }}" class="btn btn-secondary">Back to Invoices</a>
                            <a href="{{ route('invoices.agent.pdf', $invoice->id) }}" class="btn btn-outline-primary"
                                target="_blank">Print PDF</a>
                        </div>
                    </div>
                    <div class="card-body p-2 p-md-3">
                        <!-- Invoice Summary -->
                        <div class="row mb-3">
                            <div class="col-lg-12">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body p-3">
                                        <h5 class="mb-3 text-primary fw-bold">Invoice Information</h5>
                                        <div class="table-responsive">
                                            <table class="table table-borderless table-sm mb-0">
                                                <tr>
                                                    <td class="fw-semibold text-muted" style="width: 20%;">Invoice Number:
                                                    </td>
                                                    <td class="fw-bold fs-6">{{ $invoice->invoice_number }}</td>
                                                    <td class="fw-semibold text-muted" style="width: 20%;">Invoice Date:
                                                    </td>
                                                    <td class="fw-bold fs-6">
                                                        {{ optional($invoice->invoice_date)->format('Y-m-d') }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-semibold text-muted">Period:</td>
                                                    <td class="fw-bold fs-6">
                                                        {{ optional($invoice->date_from)->format('Y-m-d') }} -
                                                        {{ optional($invoice->date_to)->format('Y-m-d') }}</td>
                                                    <td class="fw-semibold text-muted">Status:</td>
                                                    <td>
                                                        @php
                                                            $statusLabel = strtolower($invoice->status_label ?? 'draft');
                                                            $statusClass = match ($statusLabel) {
                                                                'paid' => 'bg-success',
                                                                'sent', 'submitted' => 'bg-info',
                                                                'draft' => 'bg-secondary',
                                                                'cancelled' => 'bg-dark',
                                                                default => 'bg-secondary'
                                                            };
                                                        @endphp
                                                        <span
                                                            class="badge {{ $statusClass }} fs-6 px-3 py-2">{{ ucfirst($statusLabel) }}</span>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- Job Details -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0 text-primary">Job Details</h5>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0 align-middle">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="text-nowrap px-3 py-3">Date</th>
                                                        <th class="text-nowrap px-3 py-3">Job ID</th>
                                                        <th class="text-nowrap px-3 py-3">Language</th>
                                                        <th class="text-nowrap px-3 py-3">Type</th>
                                                        <th class="px-3 py-3">Description</th>
                                                        <th class="text-nowrap px-3 py-3">Duration</th>
                                                        <th class="text-nowrap text-end px-3 py-3">Base</th>
                                                        <th class="text-nowrap text-end px-3 py-3">Expenses</th>
                                                        <th class="text-nowrap text-end px-3 py-3">Parking/Travel</th>
                                                        <th class="text-nowrap text-end px-3 py-3">Total</th>
                                                        <th class="text-center px-3 py-3">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($invoice->items as $item)
                                                        @php
                                                            $computedRow = isset($computed) ? ($computed[$item->id] ?? null) : null;
                                                            $job = $item->job ?? null;
                                                            $jtMeta = $item->metadata['job_type'] ?? '';
                                                            $jt = $item->job_type ?? $jtMeta;
                                                            $dateStr = $computedRow['date'] ?? null;
                                                            if (!$dateStr) {
                                                                $date = $job ? ($job->appointment_date ?? $job->target_date) : null;
                                                                $dateStr = $date ? \Carbon\Carbon::parse($date)->format('Y-m-d') : 'N/A';
                                                            }
                                                            $language = $computedRow['language'] ?? null;
                                                            if (!$language) {
                                                                $language = 'N/A';
                                                                if ($job) {
                                                                    if (str_contains((string) $jt, 'TranslatorJob')) {
                                                                        $language = trim(((string) optional(optional($job)->fromLanguage)->name) . ' → ' . ((string) optional(optional($job)->toLanguage)->name));
                                                                    } else {
                                                                        $language = optional(optional($job)->toLanguage)->name ?? 'N/A';
                                                                    }
                                                                }
                                                            }
                                                            $duration = $computedRow['duration'] ?? null;
                                                            if (!$duration) {
                                                                $duration = '-';
                                                                if ($job && str_contains((string) $jt, 'InterpreterJob')) {
                                                                    $duration = (($job->duration_hours ?? 0) . 'h ' . ($job->duration_minutes ?? 0) . 'm');
                                                                }
                                                            }
                                                        @endphp
                                                        <tr>
                                                            <td class="align-middle px-3 py-3">{{ $dateStr }}</td>
                                                            <td class="align-middle px-3 py-3">
                                                                <strong>{{ $item->job_id }}</strong>
                                                                @if($item->job)
                                                                    <br><small
                                                                        class="text-muted">{{ optional($item->job)->reference }}</small>
                                                                @endif
                                                            </td>
                                                            <td class="align-middle px-3 py-3">{{ $language }}</td>
                                                            <td class="align-middle px-3 py-3">
                                                                @php
                                                                    $jobType = $item->metadata['job_type'] ?? 'Unknown';
                                                                    $badgeClass = str_contains($jobType, 'Interpreter') ? 'bg-success' : 'bg-info';
                                                                    $typeLabel = str_contains($jobType, 'Interpreter') ? 'INT' : 'TRA';
                                                                    $skillName = $job ? optional($job->skill)->skill : null;
                                                                @endphp
                                                                <span class="badge {{ $badgeClass }}">{{ $typeLabel }}</span>
                                                                @if($skillName)
                                                                    <br><small class="text-muted">{{ $skillName }}</small>
                                                                @endif
                                                            </td>
                                                            <td class="align-middle px-3 py-3">
                                                                <div class="text-truncate" style="max-width: 200px;"
                                                                    title="{{ $item->description }}">
                                                                    {{ $item->description }}
                                                                </div>
                                                                <small class="text-muted d-block">
                                                                    {{ optional(optional($item->job)->client->user)->getFullName() ?? 'N/A' }}
                                                                    - {{ $dateStr }}
                                                                </small>
                                                            </td>
                                                            <td class="align-middle px-3 py-3">{{ $duration }}</td>
                                                            <td class="align-middle text-end px-3 py-3">
                                                                £{{ number_format($item->base_amount, 2) }}</td>
                                                            <td class="align-middle text-end px-3 py-3">
                                                                £{{ number_format($item->expenses_amount, 2) }}</td>
                                                            <td class="align-middle text-end px-3 py-3">
                                                                £{{ number_format($item->surcharge, 2) }}</td>
                                                            <td class="align-middle text-end px-3 py-3">
                                                                <strong>£{{ number_format($item->total_amount, 2) }}</strong>
                                                            </td>
                                                            <td class="align-middle text-center px-3 py-3">
                                                                @php
                                                                    $jobType = $item->metadata['job_type'] ?? '';
                                                                    $jobId = $item->job_id;

                                                                    if ($jobType === 'App\\InterpreterJob') {
                                                                        $routeName = 'interpreter-jobs.show';
                                                                    } elseif ($jobType === 'App\\TranslatorJob') {
                                                                        $routeName = 'translator-jobs.show';
                                                                    } else {
                                                                        $routeName = null;
                                                                    }
                                                                @endphp

                                                                @if($routeName)
                                                                    <a href="{{ route($routeName, $jobId) }}"
                                                                        class="btn btn-sm btn-outline-primary"
                                                                        title="View Job Details">
                                                                        <i class="fas fa-eye"></i> <span
                                                                            class="d-none d-md-inline">View Job</span>
                                                                    </a>
                                                                @else
                                                                    <span class="text-muted small">N/A</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="10" class="text-center text-muted py-4">No job items
                                                                found for this invoice.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Invoice Totals -->
                            <div class="row mt-4">
                                <div class="col-12 col-lg-6">
                                    <div class="card h-100 border-0 bg-light">
                                        <div class="card-body p-3">
                                            <h5 class="mb-3 text-primary fw-bold">Agent Information</h5>
                                            <div class="table-responsive">
                                                <table class="table table-borderless table-sm mb-0">
                                                    <tr>
                                                        <td class="fw-semibold text-muted" style="width: 45%;">Agent Name:
                                                        </td>
                                                        <td class="fw-bold fs-6">
                                                            {{ optional($invoice->agent->user)->getFullName() ?? 'N/A' }}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-semibold text-muted">Agent Email:</td>
                                                        <td class="fw-bold fs-6 text-break">
                                                            {{ optional($invoice->agent->user)->email ?? 'N/A' }}</td>
                                                    </tr>
                                                    @if($invoice->agent && $invoice->agent->contact_number)
                                                        <tr>
                                                            <td class="fw-semibold text-muted">Phone:</td>
                                                            <td class="fw-bold fs-6">{{ $invoice->agent->contact_number }}</td>
                                                        </tr>
                                                    @endif
                                                    @if($invoice->agent && ($invoice->agent->address_line_1 || $invoice->agent->address_line_2))
                                                        <tr>
                                                            <td class="fw-semibold text-muted">Address:</td>
                                                            <td class="fw-bold fs-6">
                                                                {{ $invoice->agent->address_line_1 }}
                                                                @if($invoice->agent->address_line_2)
                                                                    <br>{{ $invoice->agent->address_line_2 }}
                                                                @endif
                                                                @if($invoice->agent->county)
                                                                    <br>{{ $invoice->agent->county }}
                                                                @endif
                                                                @if($invoice->agent->postcode)
                                                                    {{ $invoice->agent->postcode }}
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endif
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <div class="card shadow-sm border-0">
                                        <div class="card-body p-3">
                                            <h6 class="card-title mb-3 text-primary fw-bold">Invoice Summary</h6>
                                            <div class="table-responsive">
                                                <table class="table table-borderless table-sm mb-0 w-100">
                                                    @php
                                                        $baseSubtotal = $invoice->items->sum('base_amount');
                                                        $expensesTotal = $invoice->items->sum(function ($item) {
                                                            return $item->expenses_amount + $item->surcharge + $item->other_charges;
                                                        });
                                                        $totalAmount = $baseSubtotal + $expensesTotal;
                                                    @endphp
                                                    <tr>
                                                        <td class="fw-normal py-2" style="width: 60%;">Subtotal:</td>
                                                        <td class="text-end subtotal-amount fw-semibold py-2"
                                                            style="width: 40%;">£{{ number_format($baseSubtotal, 2) }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-normal py-2">Expenses:</td>
                                                        <td class="text-end fw-semibold py-2">
                                                            £{{ number_format($expensesTotal, 2) }}</td>
                                                    </tr>

                                                    <tr class="border-top">
                                                        <td class="fw-bold fs-6 py-3">Total:</td>
                                                        <td class="text-end total-amount fw-bold fs-6 text-success py-3">
                                                            £{{ number_format($totalAmount, 2) }}</td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if($invoice->notes)
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="card shadow-sm">
                                            <div class="card-body">
                                                <h6 class="card-title text-primary">Notes</h6>
                                                <p class="mb-0 text-muted">{{ $invoice->notes }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // VAT controls removed from agent invoice view
        </script>
@endsection