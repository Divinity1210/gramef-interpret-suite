<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Instant Client Sign-Off — {{ $tenantBrand->brand_name ?? 'GRAMEF InterpretFlow' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="/css/gramef-motionsites.css?v=20261116" rel="stylesheet">
    <style>
        html, body {
            background: #07090E;
            color: #0F172A;
            margin: 0;
            padding: 12px;
            min-height: 100vh;
            font-family: var(--font-sans);
        }
        .ms-magic-card {
            max-width: 560px;
            width: 100%;
            margin: 12px auto;
            background: #FFFFFF;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.45);
        }
        .ms-magic-head {
            background: #0B0F19;
            color: #FFFFFF;
            padding: 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .ms-sig-grid-2 {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 12px;
            margin-bottom: 12px;
        }
        .ms-sig-grid-3 {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }
        @media (max-width: 520px) {
            .ms-sig-grid-2, .ms-sig-grid-3 {
                grid-template-columns: minmax(0, 1fr);
            }
        }
        .ms-sig-canvas {
            width: 100%;
            height: 180px;
            border: 2px dashed #CBD5E1;
            border-radius: 14px;
            background: #F8FAFC;
            touch-action: none;
            cursor: crosshair;
            display: block;
        }
        .ms-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #CBD5E1;
            border-radius: 10px;
            font-size: 14px;
            font-family: var(--font-sans);
        }
    </style>
</head>
<body>
    <div class="ms-magic-card">
        <div class="ms-magic-head">
            <div style="display:flex;align-items:center;gap:12px;">
                <div class="ms-brand-mark">{{ $tenantBrand->logo_text ?? 'GRAMEF' }}</div>
                <div>
                    <div style="font-weight:700;font-size:15px;">10-Second Client Sign-Off</div>
                    <div style="font-family:var(--font-mono);font-size:10.5px;color:#34D399;letter-spacing:0.1em;text-transform:uppercase;">No Password Required</div>
                </div>
            </div>
            <span style="font-family:var(--font-mono);font-size:11px;padding:4px 10px;border-radius:999px;background:rgba(255,255,255,0.12);">
                {{ optional($job)->client_reference ?? ('TS-' . $timesheet->id) }}
            </span>
        </div>

        <div style="padding:24px;">
            @if (session('success'))
                <div style="background:#ECFDF5;border:1px solid #A7F3D0;color:#065F46;padding:14px 16px;border-radius:12px;margin-bottom:18px;font-size:13.5px;font-weight:600;">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div style="background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;padding:12px 14px;border-radius:12px;margin-bottom:16px;font-size:13px;">
                    {{ $errors->first() }}
                </div>
            @endif

            <div style="display:flex;align-items:center;justify-content:space-between;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:14px;padding:14px 16px;margin-bottom:18px;">
                <div>
                    <div style="font-size:11px;font-family:var(--font-mono);color:#64748B;text-transform:uppercase;">Verified Interpreter</div>
                    <div style="font-size:16px;font-weight:700;color:#0F172A;margin-top:2px;">
                        {{ optional(optional($timesheet->agent)->user)->getFullName() ?? 'Assigned Interpreter' }}
                    </div>
                    <div style="font-size:12.5px;color:#475569;margin-top:2px;">
                        {{ optional(optional($job)->toLanguage)->name ?? 'Interpreting' }} •
                        {{ optional(optional($job)->appointment_date)->format('d M Y') ?? $timesheet->appointment_date }} •
                        Start {{ $timesheet->start_time }}
                    </div>
                </div>
                <div style="text-align:right;">
                    <span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#DBEAFE;color:#1E40AF;font-size:11px;font-weight:700;">DBS Checked</span>
                </div>
            </div>

            <form method="POST" action="{{ route('magic.timesheet.store', $token) }}" id="magicSignForm">
                @csrf
                <div class="ms-sig-grid-2">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Your Name *</label>
                        <input type="text" name="client_name" class="ms-input" required
                            value="{{ old('client_name', $timesheet->client_name ?? 'Dr. Sarah Jenkins') }}" placeholder="e.g. Dr. Sarah Jenkins">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Role / Designation *</label>
                        <input type="text" name="client_designation" class="ms-input" required
                            value="{{ old('client_designation', $timesheet->client_designation ?? 'Clinician / Officer') }}" placeholder="Clinician / Social Worker">
                    </div>
                </div>

                <div class="ms-sig-grid-3">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Hours *</label>
                        <input type="number" name="actual_hours" min="0" max="24" class="ms-input tabular-nums" required
                            value="{{ old('actual_hours', $timesheet->client_duration_hours ?: ($timesheet->duration_hours ?? 1)) }}">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Minutes *</label>
                        <input type="number" name="actual_minutes" min="0" max="59" class="ms-input tabular-nums" required
                            value="{{ old('actual_minutes', $timesheet->client_duration_minutes ?: ($timesheet->duration_minutes ?? 30)) }}">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Dept Phone</label>
                        <input type="text" name="client_phone" class="ms-input" value="{{ old('client_phone', $timesheet->client_phone) }}" placeholder="Optional">
                    </div>
                </div>

                <div style="margin-bottom:14px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <label style="font-size:12px;font-weight:600;">Sign With Your Finger or Mouse *</label>
                        <button type="button" id="clearSigBtn" style="border:0;background:transparent;color:#2563EB;font-size:12px;font-weight:600;cursor:pointer;">Clear Pad</button>
                    </div>
                    <canvas id="magicSigCanvas" class="ms-sig-canvas"></canvas>
                    <input type="hidden" name="client_signature" id="magicSigInput">
                </div>

                <button type="submit" style="width:100%;padding:14px 20px;border-radius:999px;border:0;background:#0F172A;color:#FFFFFF;font-size:15px;font-weight:700;cursor:pointer;">
                    Confirm Appointment &amp; Sign Timesheet
                </button>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var canvas = document.getElementById('magicSigCanvas');
            var input = document.getElementById('magicSigInput');
            var form = document.getElementById('magicSignForm');
            var clearBtn = document.getElementById('clearSigBtn');
            if (!canvas) return;

            var ctx = canvas.getContext('2d');
            var drawing = false;
            var hasStroke = false;

            function resize() {
                var rect = canvas.getBoundingClientRect();
                canvas.width = rect.width * (window.devicePixelRatio || 1);
                canvas.height = rect.height * (window.devicePixelRatio || 1);
                ctx.setTransform(1, 0, 0, 1, 0, 0);
                ctx.scale(window.devicePixelRatio || 1, window.devicePixelRatio || 1);
                ctx.strokeStyle = '#0F172A';
                ctx.lineWidth = 2.4;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
            }
            resize();

            function pos(e) {
                var rect = canvas.getBoundingClientRect();
                var clientX = e.touches ? e.touches[0].clientX : e.clientX;
                var clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return { x: clientX - rect.left, y: clientY - rect.top };
            }

            function start(e) {
                drawing = true;
                hasStroke = true;
                var p = pos(e);
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
            }
            function move(e) {
                if (!drawing) return;
                e.preventDefault();
                var p = pos(e);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
            }
            function end() {
                drawing = false;
            }

            canvas.addEventListener('mousedown', start);
            canvas.addEventListener('mousemove', move);
            window.addEventListener('mouseup', end);
            canvas.addEventListener('touchstart', start, { passive: true });
            canvas.addEventListener('touchmove', move, { passive: false });
            canvas.addEventListener('touchend', end);

            clearBtn.addEventListener('click', function () {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                hasStroke = false;
                input.value = '';
            });

            form.addEventListener('submit', function (e) {
                if (!hasStroke) {
                    e.preventDefault();
                    alert('Please sign inside the box before confirming.');
                    return false;
                }
                input.value = canvas.toDataURL('image/png');
            });
        })();
    </script>
</body>
</html>
