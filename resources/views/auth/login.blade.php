@extends('layouts.login')

@section('content')
    <div style="margin-bottom:24px;">
        <div style="font-family:var(--font-mono);font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#64748B;margin-bottom:6px;">
            Secure Workspace Access
        </div>
        <h2 style="font-size:28px;font-weight:700;letter-spacing:-0.03em;margin:0;color:#0F172A;">
            Sign in to {{ $tenantBrand->logo_text ?? 'GRAMEF' }}
        </h2>
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div style="margin-bottom:14px;">
            <label for="email" style="display:block;font-size:12.5px;font-weight:600;color:#334155;margin-bottom:6px;">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                class="lg-input" placeholder="admin@gramef.com">
            @if ($errors->has('email'))
                <div style="color:#DC2626;font-size:12px;margin-top:5px;">{{ $errors->first('email') }}</div>
            @endif
        </div>

        <div style="margin-bottom:14px;">
            <label for="password" style="display:block;font-size:12.5px;font-weight:600;color:#334155;margin-bottom:6px;">Password</label>
            <input id="password" type="password" name="password" required
                class="lg-input" placeholder="••••••••">
            @if ($errors->has('password'))
                <div style="color:#DC2626;font-size:12px;margin-top:5px;">{{ $errors->first('password') }}</div>
            @endif
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;font-size:13px;">
            <label style="display:inline-flex;align-items:center;gap:6px;color:#475569;cursor:pointer;">
                <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                Remember me
            </label>
            <a href="{{ route('password.request') }}" style="color:#2563EB;text-decoration:none;font-weight:500;">Forgot password?</a>
        </div>

        <button type="submit" class="lg-submit">Sign In</button>
    </form>

    <div style="margin-top:28px;padding-top:22px;border-top:1px solid #E2E8F0;">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <span style="font-family:var(--font-mono);font-size:10.5px;letter-spacing:0.12em;text-transform:uppercase;color:#64748B;">1-Click Demo Role Access</span>
            <span style="font-size:11px;color:#10B981;font-weight:600;">Password: Gramef2026!</span>
        </div>

        <div class="lg-demo-grid">
            <a href="{{ route('demo.quick-login.get', 'super-admin') }}" class="lg-demo-btn">
                <strong style="color:#0F172A;">Super-Admin</strong>
                <span style="color:#64748B;font-size:11px;">White-Label &amp; All Tools</span>
            </a>
            <a href="{{ route('demo.quick-login.get', 'admin') }}" class="lg-demo-btn">
                <strong style="color:#0F172A;">Admin Coordinator</strong>
                <span style="color:#64748B;font-size:11px;">Traffic-Light &amp; Chaser</span>
            </a>
            <a href="{{ route('demo.quick-login.get', 'agent') }}" class="lg-demo-btn">
                <strong style="color:#0F172A;">Interpreter / Agent</strong>
                <span style="color:#64748B;font-size:11px;">QR Sign &amp; Payslips</span>
            </a>
            <a href="{{ route('demo.quick-login.get', 'client') }}" class="lg-demo-btn">
                <strong style="color:#0F172A;">NHS / Council Client</strong>
                <span style="color:#64748B;font-size:11px;">Live Tracking &amp; Follow-Up</span>
            </a>
        </div>
    </div>
@endsection
