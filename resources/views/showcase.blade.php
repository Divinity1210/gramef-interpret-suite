<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenantBrand->brand_name ?? 'GRAMEF InterpretFlow' }} — Intelligent Language Operations & Billing</title>
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
        }
        .sc-hero {
            position: relative;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            background: #07090E;
        }
        .sc-video {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            z-index: 0;
            opacity: 0.92;
        }
        .sc-scrim {
            position: absolute;
            inset: 0;
            z-index: 1;
            background: linear-gradient(180deg, rgba(7,9,14,0.74) 0%, rgba(7,9,14,0.32) 45%, rgba(7,9,14,0.96) 100%);
        }
        .sc-nav {
            position: relative;
            z-index: 20;
            max-width: 1320px;
            width: 100%;
            margin: 0 auto;
            padding: 20px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .sc-nav-pill {
            display: flex;
            align-items: center;
            gap: 24px;
            padding: 8px 20px;
            border-radius: 999px;
        }
        .sc-nav-pill a {
            color: rgba(248, 250, 252, 0.78);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: color 0.2s;
            white-space: nowrap;
        }
        .sc-nav-pill a:hover {
            color: #FFFFFF;
        }
        .sc-hero-body {
            position: relative;
            z-index: 10;
            max-width: 1320px;
            width: 100%;
            margin: 0 auto;
            padding: 36px 28px 56px;
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.85fr);
            gap: 40px;
            align-items: end;
        }
        .sc-hero-body > *, .sc-roi-box > *, .sc-bento > * {
            min-width: 0;
        }
        .sc-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 5px 14px 5px 5px;
            border-radius: 999px;
            margin-bottom: 20px;
            font-size: 12.5px;
            color: rgba(248, 250, 252, 0.9);
        }
        .sc-eyebrow-chip {
            background: #FFFFFF;
            color: #07090E;
            font-family: var(--font-mono);
            font-size: 10.5px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 999px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .sc-h1 {
            font-size: clamp(2.4rem, 5.2vw, 4.4rem);
            line-height: 1.02;
            letter-spacing: -0.035em;
            font-weight: 500;
            margin: 0 0 20px;
            color: #FFFFFF;
            text-shadow: 0 16px 38px rgba(0, 0, 0, 0.4);
        }
        .sc-h1 em {
            font-family: var(--font-serif);
            font-style: italic;
            font-weight: 400;
            color: #93C5FD;
        }
        .sc-sub {
            font-size: 16.5px;
            line-height: 1.6;
            color: rgba(226, 232, 240, 0.78);
            max-width: 36rem;
            margin: 0 0 28px;
        }
        .sc-cta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .sc-launchpad {
            border-radius: 24px;
            padding: 24px;
        }
        .sc-role-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 14px;
        }
        .sc-role-btn {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
            padding: 14px 16px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.14);
            color: #FFFFFF;
            text-decoration: none;
            transition: transform 0.2s var(--ease-out-quint), background 0.2s, border-color 0.2s;
            cursor: pointer;
            text-align: left;
        }
        .sc-role-btn:hover {
            transform: translateY(-2px);
            background: rgba(37, 99, 235, 0.24);
            border-color: rgba(96, 165, 250, 0.65);
        }
        .sc-role-tag {
            font-family: var(--font-mono);
            font-size: 10px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #60A5FA;
        }
        .sc-section {
            max-width: 1320px;
            margin: 0 auto;
            padding: 88px 28px;
        }
        .sc-bento {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 40px;
        }
        .sc-feature-card {
            background: #0F141F;
            border: 1px solid rgba(255, 255, 255, 0.09);
            border-radius: 20px;
            padding: 26px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 240px;
            transition: border-color 0.25s ease, transform 0.25s var(--ease-out-quint);
        }
        .sc-feature-card:hover {
            border-color: rgba(59, 130, 246, 0.45);
            transform: translateY(-3px);
        }
        .sc-roi-box {
            background: linear-gradient(135deg, #0F172A 0%, #090D16 100%);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 28px;
            padding: 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }
        .sc-stat-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-top: 32px;
            padding-top: 28px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        @media (max-width: 960px) {
            .sc-nav {
                padding: 16px 18px;
            }
            .sc-hero-body {
                grid-template-columns: minmax(0, 1fr);
                padding: 20px 18px 40px;
                gap: 28px;
            }
            .sc-roi-box {
                grid-template-columns: minmax(0, 1fr);
                padding: 24px 18px;
                gap: 24px;
            }
            .sc-bento {
                grid-template-columns: minmax(0, 1fr);
            }
            .sc-section {
                padding: 56px 18px;
            }
            .sc-stat-strip {
                grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            }
            .sc-nav-pill {
                display: none;
            }
            .sc-eyebrow {
                max-width: 100%;
                border-radius: 14px;
                padding: 8px 12px;
                flex-wrap: wrap;
            }
            .sc-h1 {
                font-size: clamp(2rem, 9vw, 3rem);
            }
        }
        @media (max-width: 560px) {
            .sc-role-grid {
                grid-template-columns: minmax(0, 1fr);
            }
            .sc-brand-sub {
                display: none;
            }
            .sc-cta-row a {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="ms-grain" aria-hidden="true"></div>

    <section class="sc-hero">
        <video class="sc-video" autoplay muted loop playsinline preload="auto"
            src="https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260508_064122_c4750c0e-7476-4b44-94a2-a85a65c63bf2.mp4"></video>
        <div class="sc-scrim"></div>

        <nav class="sc-nav">
            <a href="{{ route('showcase') }}" style="display:flex;align-items:center;gap:12px;text-decoration:none;color:#fff;">
                <div class="ms-brand-mark">{{ $tenantBrand->logo_text ?? 'GRAMEF' }}</div>
                <div>
                    <div style="font-weight:700;font-size:15px;letter-spacing:-0.02em;">{{ $tenantBrand->brand_name ?? 'GRAMEF InterpretFlow' }}</div>
                    <div style="font-family:var(--font-mono);font-size:10px;color:#94A3B8;letter-spacing:0.12em;text-transform:uppercase;" class="sc-brand-sub">White-Label Enterprise Suite</div>
                </div>
            </a>

            <div class="sc-nav-pill liquid-glass">
                <a href="#deliverables">6 Deliverables</a>
                <a href="#roi">ROI Calculator</a>
                <a href="#whitelabel">White-Label</a>
                @if($sampleMagicTimesheet && $sampleMagicTimesheet->magic_sign_token)
                    <a href="{{ route('magic.timesheet.show', $sampleMagicTimesheet->magic_sign_token) }}">10s Client Sign</a>
                @endif
            </div>

            <div style="display:flex;align-items:center;gap:10px;">
                <a href="{{ route('login') }}" class="ms-btn-halo" style="padding:10px 20px;font-size:13px;">
                    Portal Login
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            </div>
        </nav>

        <div class="sc-hero-body">
            <div>
                <div class="sc-eyebrow liquid-glass ms-reveal is-in">
                    <span class="sc-eyebrow-chip">GRAMEF DIGITAL</span>
                    <span>Same familiar routine. 540+ admin hours saved every year.</span>
                </div>

                <h1 class="sc-h1 ms-reveal is-in">
                    Every booking, timesheet &amp; payout.<br>
                    <em>Checked in one click.</em>
                </h1>

                <p class="sc-sub ms-reveal is-in">
                    Built for interpreting and translation agencies across NHS trusts, local councils, and courts.
                    No-password client signatures, automatic rate cards, Traffic-Light payroll safety checks,
                    and polite credit control — customizable for any organisation in minutes.
                </p>

                <div class="sc-cta-row ms-reveal is-in">
                    <a href="{{ route('demo.quick-login.get', 'admin') }}" class="ms-btn-halo">
                        Launch Admin Command Center
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                    @if($sampleMagicTimesheet && $sampleMagicTimesheet->magic_sign_token)
                        <a href="{{ route('magic.timesheet.show', $sampleMagicTimesheet->magic_sign_token) }}" class="ms-btn-glass">
                            Try 10-Sec Client Sign (No Login)
                        </a>
                    @endif
                </div>
            </div>

            <div class="sc-launchpad liquid-glass liquid-glass--strong ms-reveal is-in">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span style="font-family:var(--font-mono);font-size:11px;text-transform:uppercase;letter-spacing:0.14em;color:#93C5FD;">1-Click Role Launchpad</span>
                    <span style="font-family:var(--font-mono);font-size:11px;color:#34D399;">● ISOLATED DEMO DB</span>
                </div>
                <div style="font-size:13.5px;color:#CBD5E1;margin-top:6px;">
                    Step inside every user category immediately with realistic UK NHS, Council &amp; Court data:
                </div>

                <div class="sc-role-grid">
                    <a href="{{ route('demo.quick-login.get', 'super-admin') }}" class="sc-role-btn">
                        <span class="sc-role-tag">01 / Director</span>
                        <strong style="font-size:14.5px;">Super-Admin</strong>
                        <span style="font-size:12px;color:#94A3B8;">White-label, Traffic-Light &amp; Payouts</span>
                    </a>
                    <a href="{{ route('demo.quick-login.get', 'admin') }}" class="sc-role-btn">
                        <span class="sc-role-tag">02 / Operations</span>
                        <strong style="font-size:14.5px;">Admin Coordinator</strong>
                        <span style="font-size:12px;color:#94A3B8;">Paste-an-Email, Matching &amp; Chaser</span>
                    </a>
                    <a href="{{ route('demo.quick-login.get', 'agent') }}" class="sc-role-btn">
                        <span class="sc-role-tag">03 / Linguist</span>
                        <strong style="font-size:14.5px;">Interpreter Portal</strong>
                        <span style="font-size:12px;color:#94A3B8;">QR Magic Sign, GPS &amp; Payslips</span>
                    </a>
                    <a href="{{ route('demo.quick-login.get', 'client') }}" class="sc-role-btn">
                        <span class="sc-role-tag">04 / NHS / Council</span>
                        <strong style="font-size:14.5px;">Client Booking Desk</strong>
                        <span style="font-size:12px;color:#94A3B8;">5-Step Live Tracking &amp; 1-Click Follow-Up</span>
                    </a>
                </div>

                <div class="sc-stat-strip">
                    <div>
                        <div class="tabular-nums" style="font-size:22px;font-weight:600;color:#fff;">~45h</div>
                        <div style="font-size:11px;color:#94A3B8;">Saved / month</div>
                    </div>
                    <div>
                        <div class="tabular-nums" style="font-size:22px;font-weight:600;color:#34D399;">10s</div>
                        <div style="font-size:11px;color:#94A3B8;">Client signature</div>
                    </div>
                    <div>
                        <div class="tabular-nums" style="font-size:22px;font-weight:600;color:#60A5FA;">100%</div>
                        <div style="font-size:11px;color:#94A3B8;">Admin in control</div>
                    </div>
                    <div>
                        <div class="tabular-nums" style="font-size:22px;font-weight:600;color:#FBBF24;">£0</div>
                        <div style="font-size:11px;color:#94A3B8;">Live DB untouched</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="sc-section" id="deliverables">
        <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-end;gap:20px;">
            <div>
                <div style="font-family:var(--font-mono);font-size:11px;text-transform:uppercase;letter-spacing:0.16em;color:#60A5FA;margin-bottom:10px;">
                    Executive Proposal Deliverables 01 — 06
                </div>
                <h2 style="font-size:clamp(1.9rem,3.6vw,3rem);letter-spacing:-0.03em;font-weight:500;margin:0;line-height:1.1;">
                    Quiet Smart Helpers.<br><em style="font-family:var(--font-serif);font-style:italic;color:#93C5FD;">Your team stays 100% in control.</em>
                </h2>
            </div>
            <p style="max-width:26rem;color:#94A3B8;font-size:14.5px;line-height:1.6;margin:0;">
                No invoice is ever sent, no reminder is emailed, and no interpreter is paid without your approval — while every calculator and chasing chore runs silently in the background.
            </p>
        </div>

        <div class="sc-bento">
            <article class="sc-feature-card">
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
                        <span style="font-family:var(--font-mono);font-size:11px;color:#60A5FA;">DELIVERABLE 01</span>
                        <span style="font-size:11px;padding:3px 10px;border-radius:999px;background:rgba(16,185,129,0.14);color:#34D399;">Saves 6–8h / mo</span>
                    </div>
                    <h3 style="font-size:19px;font-weight:600;margin:0 0 10px;letter-spacing:-0.02em;">No-Password 10s Signature &amp; Smart Paper Reader</h3>
                    <p style="font-size:13.5px;line-height:1.6;color:#94A3B8;margin:0;">
                        Doctors, social workers, and court clerks sign with their finger via QR code or 1-click link in 10 seconds — no forgotten passwords. Paper uploads are OCR-checked in 1 click.
                    </p>
                </div>
                @if($sampleMagicTimesheet && $sampleMagicTimesheet->magic_sign_token)
                    <a href="{{ route('magic.timesheet.show', $sampleMagicTimesheet->magic_sign_token) }}" style="margin-top:18px;color:#93C5FD;font-size:13px;font-weight:600;text-decoration:none;">Open Live Client Sign Link →</a>
                @endif
            </article>

            <article class="sc-feature-card">
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
                        <span style="font-family:var(--font-mono);font-size:11px;color:#60A5FA;">DELIVERABLE 02</span>
                        <span style="font-size:11px;padding:3px 10px;border-radius:999px;background:rgba(16,185,129,0.14);color:#34D399;">Saves 10–12h / mo</span>
                    </div>
                    <h3 style="font-size:19px;font-weight:600;margin:0 0 10px;letter-spacing:-0.02em;">Built-in Price Book &amp; “Traffic-Light” Checker</h3>
                    <p style="font-size:13.5px;line-height:1.6;color:#94A3B8;margin:0;">
                        Auto-calculates Daytime (08:00–18:00), Out-of-Hours, mileage &amp; travel splits. Shows Booked | GPS | Timesheet | Margin on one row with Green/Amber safety flags.
                    </p>
                </div>
                <a href="{{ route('demo.quick-login.get', 'admin') }}" style="margin-top:18px;color:#93C5FD;font-size:13px;font-weight:600;text-decoration:none;">Inspect Traffic-Light Board →</a>
            </article>

            <article class="sc-feature-card">
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
                        <span style="font-family:var(--font-mono);font-size:11px;color:#60A5FA;">DELIVERABLE 03</span>
                        <span style="font-size:11px;padding:3px 10px;border-radius:999px;background:rgba(16,185,129,0.14);color:#34D399;">Saves 6–8h / mo</span>
                    </div>
                    <h3 style="font-size:19px;font-weight:600;margin:0 0 10px;letter-spacing:-0.02em;">Smart Remittance Matcher &amp; Bank Payout Sheet</h3>
                    <p style="font-size:13.5px;line-height:1.6;color:#94A3B8;margin:0;">
                        Drop NHS or council multi-invoice remittances to auto-tick matching CI-XXXXXX invoices, bulk mark paid, and export a BACS/Faster Payments CSV payout file in 1 click.
                    </p>
                </div>
                <a href="{{ route('demo.quick-login.get', 'super-admin') }}" style="margin-top:18px;color:#93C5FD;font-size:13px;font-weight:600;text-decoration:none;">Try Remittance Reconciliation →</a>
            </article>

            <article class="sc-feature-card">
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
                        <span style="font-family:var(--font-mono);font-size:11px;color:#60A5FA;">DELIVERABLE 04</span>
                        <span style="font-size:11px;padding:3px 10px;border-radius:999px;background:rgba(16,185,129,0.14);color:#34D399;">Saves 6–8h / mo</span>
                    </div>
                    <h3 style="font-size:19px;font-weight:600;margin:0 0 10px;letter-spacing:-0.02em;">Polite Invoice Chaser &amp; Never Chase Twice</h3>
                    <p style="font-size:13.5px;line-height:1.6;color:#94A3B8;margin:0;">
                        Colour-coded 7 / 14 / 30 / 60+ day aging board with “Review Before Sending” switch, PDF + Statement of Account + 1-Click Remittance Link, and instant auto-stop on payment.
                    </p>
                </div>
                @if($sampleMagicInvoice && $sampleMagicInvoice->remittance_magic_token)
                    <a href="{{ route('magic.remittance.show', $sampleMagicInvoice->remittance_magic_token) }}" style="margin-top:18px;color:#93C5FD;font-size:13px;font-weight:600;text-decoration:none;">Try 1-Click Client Remittance →</a>
                @endif
            </article>

            <article class="sc-feature-card">
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
                        <span style="font-family:var(--font-mono);font-size:11px;color:#60A5FA;">DELIVERABLE 05</span>
                        <span style="font-size:11px;padding:3px 10px;border-radius:999px;background:rgba(16,185,129,0.14);color:#34D399;">Saves 6–8h / mo</span>
                    </div>
                    <h3 style="font-size:19px;font-weight:600;margin:0 0 10px;letter-spacing:-0.02em;">Paste-an-Email Quick Booking &amp; Reliability Notes</h3>
                    <p style="font-size:13.5px;line-height:1.6;color:#94A3B8;margin:0;">
                        Paste any NHS referral email to auto-fill Language, Date, Time, Postcode, DBS &amp; Reference in 30 seconds. View non-blocking travel-time notes &amp; 1-click Urgent (&lt;24h) alerts.
                    </p>
                </div>
                <a href="{{ route('demo.quick-login.get', 'admin') }}" style="margin-top:18px;color:#93C5FD;font-size:13px;font-weight:600;text-decoration:none;">Test Smart Booking Parser →</a>
            </article>

            <article class="sc-feature-card">
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
                        <span style="font-family:var(--font-mono);font-size:11px;color:#60A5FA;">DELIVERABLE 06</span>
                        <span style="font-size:11px;padding:3px 10px;border-radius:999px;background:rgba(16,185,129,0.14);color:#34D399;">Saves 4–6h / mo</span>
                    </div>
                    <h3 style="font-size:19px;font-weight:600;margin:0 0 10px;letter-spacing:-0.02em;">Live 5-Step Client Tracker &amp; DBS Expiry Guard</h3>
                    <p style="font-size:13.5px;line-height:1.6;color:#94A3B8;margin:0;">
                        Clients see Booked → Assigned → Confirmed → Checked In On-Site → Completed in real time, re-book follow-ups with the same interpreter in 1 click, and automate 60/30-day DBS alerts.
                    </p>
                </div>
                <a href="{{ route('demo.quick-login.get', 'client') }}" style="margin-top:18px;color:#93C5FD;font-size:13px;font-weight:600;text-decoration:none;">Open Client Portal View →</a>
            </article>
        </div>
    </section>

    <section class="sc-section" id="roi" style="padding-top:0;">
        <div class="sc-roi-box">
            <div>
                <div style="font-family:var(--font-mono);font-size:11px;text-transform:uppercase;letter-spacing:0.16em;color:#34D399;margin-bottom:10px;">
                    Interactive Time &amp; Margin Calculator
                </div>
                <h2 style="font-size:clamp(1.8rem,3vw,2.6rem);letter-spacing:-0.03em;font-weight:500;margin:0 0 14px;line-height:1.12;">
                    Calculate what your agency saves every year.
                </h2>
                <p style="font-size:14.5px;color:#94A3B8;line-height:1.6;margin:0 0 28px;">
                    Adjust monthly bookings below to model admin hours reclaimed and leakage prevented across your team.
                </p>

                <label style="display:flex;justify-content:space-between;font-size:13.5px;margin-bottom:8px;">
                    <span>Monthly Interpreting Bookings</span>
                    <strong class="tabular-nums" id="roiBookingsLabel" style="color:#60A5FA;">450 jobs/mo</strong>
                </label>
                <input id="roiBookingsSlider" type="range" min="100" max="2000" step="50" value="450" style="width:100%;accent-color:#3B82F6;margin-bottom:22px;">

                <label style="display:flex;justify-content:space-between;font-size:13.5px;margin-bottom:8px;">
                    <span>Admin Hourly Cost (£)</span>
                    <strong class="tabular-nums" id="roiRateLabel" style="color:#60A5FA;">£22 / hr</strong>
                </label>
                <input id="roiRateSlider" type="range" min="14" max="45" step="1" value="22" style="width:100%;accent-color:#3B82F6;">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:18px;padding:22px;">
                    <div style="font-family:var(--font-mono);font-size:10.5px;color:#94A3B8;text-transform:uppercase;letter-spacing:0.1em;">Hours Reclaimed / Mo</div>
                    <div class="tabular-nums" id="roiMonthlyHours" style="font-size:34px;font-weight:600;color:#fff;margin-top:6px;">45 hrs</div>
                </div>
                <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:18px;padding:22px;">
                    <div style="font-family:var(--font-mono);font-size:10.5px;color:#94A3B8;text-transform:uppercase;letter-spacing:0.1em;">Annual Hours Saved</div>
                    <div class="tabular-nums" id="roiAnnualHours" style="font-size:34px;font-weight:600;color:#34D399;margin-top:6px;">540 hrs</div>
                </div>
                <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:18px;padding:22px;">
                    <div style="font-family:var(--font-mono);font-size:10.5px;color:#94A3B8;text-transform:uppercase;letter-spacing:0.1em;">Annual Admin Savings</div>
                    <div class="tabular-nums" id="roiAnnualCost" style="font-size:30px;font-weight:600;color:#60A5FA;margin-top:6px;">£11,880</div>
                </div>
                <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:18px;padding:22px;">
                    <div style="font-family:var(--font-mono);font-size:10.5px;color:#94A3B8;text-transform:uppercase;letter-spacing:0.1em;">Overpayment Guard</div>
                    <div class="tabular-nums" id="roiLeakage" style="font-size:30px;font-weight:600;color:#FBBF24;margin-top:6px;">£4,320/yr</div>
                </div>
            </div>
        </div>
    </section>

    <section class="sc-section" id="whitelabel" style="padding-top:0;">
        <div style="text-align:center;max-width:44rem;margin:0 auto 32px;">
            <div style="font-family:var(--font-mono);font-size:11px;text-transform:uppercase;letter-spacing:0.16em;color:#60A5FA;margin-bottom:10px;">
                A Product by GRAMEF Digital
            </div>
            <h2 style="font-size:clamp(1.8rem,3.2vw,2.6rem);letter-spacing:-0.03em;font-weight:500;margin:0 0 12px;">
                Ready to customise for any organisation.
            </h2>
            <p style="font-size:15px;color:#94A3B8;line-height:1.6;margin:0;">
                Switch logos, colours, currency, VAT rates, and 4-week rollout stages live in the White-Label Studio — whether deploying for A to Z Interpreting, an NHS Trust desk, or a national court supplier.
            </p>
            <div style="margin-top:24px;">
                <a href="{{ route('demo.quick-login.get', 'super-admin') }}" class="ms-btn-halo">
                    Open White-Label Studio
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>
    </section>

    <script>
        (function () {
            var bSlider = document.getElementById('roiBookingsSlider');
            var rSlider = document.getElementById('roiRateSlider');
            if (!bSlider || !rSlider) return;

            function recalc() {
                var jobs = parseInt(bSlider.value, 10) || 450;
                var rate = parseInt(rSlider.value, 10) || 22;
                var monthlyHours = Math.max(12, Math.round(jobs * 0.10));
                var annualHours = monthlyHours * 12;
                var annualCost = annualHours * rate;
                var leakage = Math.round(jobs * 0.8 * 12);

                document.getElementById('roiBookingsLabel').textContent = jobs.toLocaleString() + ' jobs/mo';
                document.getElementById('roiRateLabel').textContent = '£' + rate + ' / hr';
                document.getElementById('roiMonthlyHours').textContent = monthlyHours.toLocaleString() + ' hrs';
                document.getElementById('roiAnnualHours').textContent = annualHours.toLocaleString() + ' hrs';
                document.getElementById('roiAnnualCost').textContent = '£' + annualCost.toLocaleString();
                document.getElementById('roiLeakage').textContent = '£' + leakage.toLocaleString() + '/yr';
            }
            bSlider.addEventListener('input', recalc);
            rSlider.addEventListener('input', recalc);
            recalc();
        })();
    </script>
</body>
</html>
