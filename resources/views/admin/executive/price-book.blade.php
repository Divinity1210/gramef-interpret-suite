@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4" style="gap:12px;">
        <div>
            <div style="font-family:var(--font-mono);font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#2563EB;">
                Deliverable 02A · Built-In Price Book &amp; OOH Splitter
            </div>
            <h1 class="page-title mb-1">Automatic Rate Cards (Clients &amp; Interpreters)</h1>
            <p class="page-subtitle">Daytime (08:00–18:00), Out-of-Hours, Minimum Hours, Travel &amp; Mileage auto-calculate once a timesheet is signed. Override any figure anytime.</p>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-5 mb-4">
            <div class="ms-card p-4 h-100">
                <h5 class="font-weight-bold mb-3"><i class="fas fa-calculator text-primary mr-2"></i>Live Daytime / Out-of-Hours Split Simulator</h5>
                <form method="GET" action="{{ route('admin.price-book.index') }}">
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="small font-weight-bold">Service Type</label>
                            <select name="calc_service" class="form-control form-control-sm">
                                @foreach(['Face to Face', 'BSL', 'Video Interpreting', 'Telephone Interpreting'] as $st)
                                    <option value="{{ $st }}" {{ $skillName === $st ? 'selected' : '' }}>{{ $st }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="small font-weight-bold">Date</label>
                            <input type="date" name="calc_date" class="form-control form-control-sm" value="{{ request('calc_date', now('Europe/London')->toDateString()) }}">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="small font-weight-bold">Start Time</label>
                            <input type="time" name="calc_start" class="form-control form-control-sm" value="{{ request('calc_start', '17:00') }}">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="small font-weight-bold">Hours</label>
                            <input type="number" name="calc_hours" class="form-control form-control-sm" value="{{ request('calc_hours', 2) }}" min="0" max="12">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="small font-weight-bold">Parking (£)</label>
                            <input type="number" step="0.50" name="calc_parking" class="form-control form-control-sm" value="{{ request('calc_parking', 4.50) }}">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm btn-block" style="border-radius:999px;">Recalculate Split &amp; Margin</button>
                </form>

                <div class="mt-4 p-3 rounded" style="background:#F8FAFC;border:1px solid #E2E8F0;">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Daytime (08:00–18:00):</span>
                        <strong class="tabular-nums">{{ $calcPreview['daytime_minutes'] }} mins</strong>
                    </div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Out-of-Hours / Weekend:</span>
                        <strong class="tabular-nums">{{ $calcPreview['ooh_minutes'] }} mins</strong>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Client Invoice Total:</span>
                        <strong class="tabular-nums text-primary">£{{ number_format($calcPreview['client_charge'], 2) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Interpreter Payout Total:</span>
                        <strong class="tabular-nums text-dark">£{{ number_format($calcPreview['interpreter_pay'], 2) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between font-weight-bold mt-2 pt-2 border-top">
                        <span>Agency Margin:</span>
                        <span class="tabular-nums text-success">£{{ number_format($calcPreview['margin'], 2) }} ({{ $calcPreview['margin_pct'] }}%)</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-4">
            <div class="ms-card p-4 h-100">
                <h5 class="font-weight-bold mb-3"><i class="fas fa-plus-circle text-success mr-2"></i>Add Custom Organisation / Interpreter Rate Card</h5>
                <form method="POST" action="{{ route('admin.price-book.store') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="small font-weight-bold">Rate Card Name</label>
                            <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Crown Court — Enhanced" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="small font-weight-bold">Applies To</label>
                            <select name="role_type" class="form-control form-control-sm">
                                <option value="client">Client Billing</option>
                                <option value="interpreter">Interpreter Pay</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="small font-weight-bold">Service Type</label>
                            <select name="service_type" class="form-control form-control-sm">
                                <option>Face to Face</option>
                                <option>BSL</option>
                                <option>Video Interpreting</option>
                                <option>Telephone Interpreting</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="small font-weight-bold">Qualification</label>
                            <input type="text" name="qualification_level" class="form-control form-control-sm" value="All Levels" required>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="small font-weight-bold">Day (£/hr)</label>
                            <input type="number" step="0.01" name="daytime_rate" class="form-control form-control-sm" value="52.00" required>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="small font-weight-bold">OOH (£/hr)</label>
                            <input type="number" step="0.01" name="ooh_rate" class="form-control form-control-sm" value="72.00" required>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="small font-weight-bold">Min Hrs</label>
                            <input type="number" step="0.25" name="minimum_hours" class="form-control form-control-sm" value="1.00" required>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="small font-weight-bold">Travel/hr</label>
                            <input type="number" step="0.01" name="travel_rate_hourly" class="form-control form-control-sm" value="22.00" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="small font-weight-bold">Mileage (£/mi)</label>
                            <input type="number" step="0.01" name="mileage_rate" class="form-control form-control-sm" value="0.45" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="small font-weight-bold">Cancel Min Hrs</label>
                            <input type="number" step="0.25" name="cancellation_min_hours" class="form-control form-control-sm" value="2.00" required>
                        </div>
                        <div class="col-md-6 d-flex align-items-end mb-2">
                            <button type="submit" class="btn btn-dark btn-sm px-4" style="border-radius:999px;">Save Rate Card</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="ms-card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:13px;">
                <thead style="background:#F8FAFC;">
                    <tr>
                        <th>Rate Card</th>
                        <th>Role</th>
                        <th>Service Type</th>
                        <th>Daytime (08–18)</th>
                        <th>Out-of-Hours</th>
                        <th>Min Hrs</th>
                        <th>Travel (£/hr)</th>
                        <th>Mileage (£/mi)</th>
                        <th class="text-right">Quick Update</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rateCards as $card)
                        <tr>
                            <form method="POST" action="{{ route('admin.price-book.update', $card->id) }}">
                                @csrf
                                @method('PUT')
                                <td class="font-weight-bold">{{ $card->name }}</td>
                                <td>
                                    <span class="badge {{ $card->role_type === 'client' ? 'badge-primary' : 'badge-success' }}">
                                        {{ ucfirst($card->role_type) }}
                                    </span>
                                </td>
                                <td>{{ $card->service_type }}</td>
                                <td><input type="number" step="0.01" name="daytime_rate" value="{{ $card->daytime_rate }}" class="form-control form-control-sm tabular-nums" style="width:90px;"></td>
                                <td><input type="number" step="0.01" name="ooh_rate" value="{{ $card->ooh_rate }}" class="form-control form-control-sm tabular-nums" style="width:90px;"></td>
                                <td><input type="number" step="0.25" name="minimum_hours" value="{{ $card->minimum_hours }}" class="form-control form-control-sm tabular-nums" style="width:75px;"></td>
                                <td><input type="number" step="0.01" name="travel_rate_hourly" value="{{ $card->travel_rate_hourly }}" class="form-control form-control-sm tabular-nums" style="width:85px;"></td>
                                <td><input type="number" step="0.01" name="mileage_rate" value="{{ $card->mileage_rate }}" class="form-control form-control-sm tabular-nums" style="width:80px;"></td>
                                <td class="text-right"><button type="submit" class="btn btn-sm btn-outline-primary">Save</button></td>
                            </form>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
