<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $tenantBrand->brand_name ?? 'GRAMEF InterpretFlow' }} — Sign In</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="/css/gramef-motionsites.css?v=20261116" rel="stylesheet">
    <style>
        html, body {
            background: #07090E;
            color: #F8FAFC;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            min-height: 100dvh;
        }
        .lg-wrap {
            position: relative;
            min-height: 100vh;
            min-height: 100dvh;
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(0, 0.92fr);
            overflow-x: hidden;
        }
        .lg-wrap > * {
            min-width: 0;
        }
        .lg-stage {
            position: relative;
            padding: 36px 48px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            background: #07090E;
        }
        .lg-stage video {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
            opacity: 0.9;
        }
        .lg-stage::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 1;
            background: linear-gradient(180deg, rgba(7,9,14,0.72) 0%, rgba(7,9,14,0.34) 48%, rgba(7,9,14,0.94) 100%);
        }
        .lg-stage-inner {
            position: relative;
            z-index: 10;
        }
        .lg-panel {
            background: #FFFFFF;
            color: #0F172A;
            padding: 40px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            z-index: 20;
        }
        .lg-form-box {
            max-width: 420px;
            width: 100%;
            margin: 0 auto;
        }
        .lg-input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #CBD5E1;
            border-radius: 10px;
            font-size: 14.5px;
            font-family: var(--font-sans);
            color: #0F172A;
            background: #F8FAFC;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }
        .lg-input:focus {
            background: #FFFFFF;
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.14);
        }
        .lg-submit {
            width: 100%;
            padding: 13px 20px;
            border-radius: 999px;
            border: 0;
            background: #0F172A;
            color: #FFFFFF;
            font-weight: 600;
            font-size: 14.5px;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }
        .lg-submit:hover {
            background: #1E293B;
            transform: translateY(-1px);
        }
        .lg-demo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 10px;
        }
        .lg-demo-btn {
            display: flex;
            flex-direction: column;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #E2E8F0;
            background: #F8FAFC;
            color: #0F172A;
            text-decoration: none;
            font-size: 12.5px;
            transition: border-color 0.18s, background 0.18s, transform 0.18s;
        }
        .lg-demo-btn:hover {
            border-color: var(--brand-primary);
            background: #EFF6FF;
            transform: translateY(-1px);
        }
        @media (max-width: 960px) {
            .lg-wrap {
                grid-template-columns: minmax(0, 1fr);
            }
            .lg-stage {
                min-height: 320px;
                padding: 22px 18px;
            }
            .lg-panel {
                padding: 28px 18px;
            }
        }
        @media (max-width: 480px) {
            .lg-demo-grid {
                grid-template-columns: minmax(0, 1fr);
            }
            .lg-stage-badge {
                flex-direction: column;
                align-items: flex-start !important;
            }
        }
    </style>
</head>
<body>
    <div class="ms-grain" aria-hidden="true"></div>
    <div class="lg-wrap">
        <aside class="lg-stage">
            <video autoplay muted loop playsinline preload="auto"
                src="https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260508_064122_c4750c0e-7476-4b44-94a2-a85a65c63bf2.mp4"></video>

            <div class="lg-stage-inner" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <a href="{{ route('showcase') }}" style="display:flex;align-items:center;gap:12px;text-decoration:none;color:#fff;">
                    <div class="ms-brand-mark">{{ $tenantBrand->logo_text ?? 'GRAMEF' }}</div>
                    <div>
                        <div style="font-weight:700;font-size:15px;">{{ $tenantBrand->brand_name ?? 'GRAMEF InterpretFlow' }}</div>
                        <div style="font-family:var(--font-mono);font-size:10px;color:#94A3B8;letter-spacing:0.12em;text-transform:uppercase;">Language Operations OS</div>
                    </div>
                </a>
                <a href="{{ route('showcase') }}" class="ms-btn-glass" style="padding:7px 14px;font-size:12px;">
                    Product Tour →
                </a>
            </div>

            <div class="lg-stage-inner" style="max-width:520px;margin:48px 0 18px;">
                <div style="font-family:var(--font-mono);font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#93C5FD;margin-bottom:12px;">
                    A PRODUCT OF GRAMEF DIGITAL
                </div>
                <h1 style="font-size:clamp(2rem,3.6vw,3.2rem);line-height:1.05;letter-spacing:-0.035em;font-weight:500;margin:0 0 16px;">
                    Same familiar system.<br>
                    <em style="font-family:var(--font-serif);font-style:italic;color:#93C5FD;">Built for zero bottlenecks.</em>
                </h1>
                <p style="font-size:14.5px;line-height:1.6;color:rgba(226,232,240,0.8);margin:0;">
                    Instant no-password client signatures, automatic rate cards, Traffic-Light payroll checks, smart remittance matching, and live appointment tracking.
                </p>
            </div>

            <div class="lg-stage-inner liquid-glass lg-stage-badge" style="border-radius:16px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;max-width:520px;">
                <div>
                    <div style="font-family:var(--font-mono);font-size:10px;color:#34D399;letter-spacing:0.12em;text-transform:uppercase;">Executive Upgrade Ready</div>
                    <div style="font-size:13px;color:#E2E8F0;margin-top:2px;">~45 admin hours saved monthly • Isolated Demo Environment</div>
                </div>
                <a href="{{ route('showcase') }}" style="color:#fff;font-size:12.5px;font-weight:600;text-decoration:none;white-space:nowrap;">Explore ROI →</a>
            </div>
        </aside>

        <main class="lg-panel">
            <div class="lg-form-box">
                @include('layouts.flash')
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
