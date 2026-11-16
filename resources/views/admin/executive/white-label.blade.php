@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4" style="gap:12px;">
        <div>
            <div style="font-family:var(--font-mono);font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#2563EB;">
                GRAMEF Digital SaaS Product Studio
            </div>
            <h1 class="page-title mb-1">White-Label Multi-Organisation Customiser</h1>
            <p class="page-subtitle">Deploy this platform for A to Z Interpreting, an NHS Trust Language Desk, Legal/Court suppliers, or any custom agency in one click.</p>
        </div>
    </div>

    <div class="row mb-4">
        @foreach ($presets as $preset)
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="ms-kpi-card h-100 d-flex flex-column justify-content-between" style="border:{{ $preset->is_active ? '2px solid ' . $preset->primary_color : '1px solid #E2E8F0' }};">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="ms-brand-mark" style="background:linear-gradient(135deg, {{ $preset->primary_color }}, {{ $preset->accent_color }});">{{ $preset->logo_text }}</div>
                            @if($preset->is_active)
                                <span class="badge badge-success">ACTIVE SKIN</span>
                            @endif
                        </div>
                        <h6 class="font-weight-bold mb-1">{{ $preset->brand_name }}</h6>
                        <p class="small text-muted mb-3">{{ $preset->brand_tagline }}</p>
                    </div>
                    <form method="POST" action="{{ route('admin.white-label.preset', $preset->slug) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-block {{ $preset->is_active ? 'btn-dark' : 'btn-outline-primary' }}" style="border-radius:999px;">
                            {{ $preset->is_active ? 'Currently Active' : 'Activate ' . $preset->logo_text }}
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>

    <div class="ms-card p-4">
        <h5 class="font-weight-bold mb-3"><i class="fas fa-sliders-h text-primary mr-2"></i>Customise Active Organisation Settings</h5>
        <form method="POST" action="{{ route('admin.white-label.update') }}">
            @csrf
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="small font-weight-bold">Brand / Agency Name</label>
                    <input type="text" name="brand_name" class="form-control" value="{{ $current->brand_name }}" required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="small font-weight-bold">Short Mark</label>
                    <input type="text" name="logo_text" class="form-control" value="{{ $current->logo_text }}" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="small font-weight-bold">Tagline</label>
                    <input type="text" name="brand_tagline" class="form-control" value="{{ $current->brand_tagline }}">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="small font-weight-bold">Primary Colour</label>
                    <input type="color" name="primary_color" class="form-control" value="{{ $current->primary_color }}">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="small font-weight-bold">Accent Colour</label>
                    <input type="color" name="accent_color" class="form-control" value="{{ $current->accent_color }}">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="small font-weight-bold">Currency</label>
                    <input type="text" name="currency_symbol" class="form-control" value="{{ $current->currency_symbol }}" required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="small font-weight-bold">Default VAT %</label>
                    <input type="number" step="0.01" name="default_vat_rate" class="form-control" value="{{ $current->default_vat_rate }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="small font-weight-bold">4-Week Calm Rollout Stage</label>
                    <select name="active_delivery_stage" class="form-control">
                        <option value="1" {{ ($current->active_delivery_stage ?? 4) == 1 ? 'selected' : '' }}>Stage 1: Signatures &amp; Chaser</option>
                        <option value="2" {{ ($current->active_delivery_stage ?? 4) == 2 ? 'selected' : '' }}>Stage 2: Price Book &amp; Bank Payout</option>
                        <option value="3" {{ ($current->active_delivery_stage ?? 4) == 3 ? 'selected' : '' }}>Stage 3: Smart OCR, Traffic-Light &amp; Quick Booking</option>
                        <option value="4" {{ ($current->active_delivery_stage ?? 4) == 4 ? 'selected' : '' }}>Stage 4: All Features Active (Full Suite)</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary px-4 font-weight-bold" style="border-radius:999px;">
                Apply White-Label Configuration Globally
            </button>
        </form>
    </div>
</div>
@endsection
