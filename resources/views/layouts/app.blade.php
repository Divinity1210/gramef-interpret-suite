<!DOCTYPE html>
<html class="html" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $tenantBrand->brand_name ?? 'GRAMEF InterpretFlow' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link href="/css/core.clean.css?v=20251110" rel="stylesheet">
    <link href="/css/actions.css?v=20251110" rel="stylesheet">
    <link href="/css/gramef-motionsites.css?v=20261116" rel="stylesheet">
    <link href="/favicon.ico" rel="shortcut icon" />

    <style>
        :root {
            --brand-primary: {{ $tenantBrand->primary_color ?? '#2563EB' }};
            --brand-accent: {{ $tenantBrand->accent_color ?? '#10B981' }};
        }
        #signature-pad {
            border: 1px solid #CBD5E1;
            border-radius: 10px;
            width: 100%;
            max-width: 420px;
            height: 200px;
            background: #FFFFFF;
        }
        .responsive-scroll-x {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        /* Keep legacy filter bars aligned nicely inside upgraded shell */
        #filterForm, #filters-form, form.filter, form.row.g-3 {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 12px;
            align-items: stretch;
        }
        #filterForm .section__input, #filters-form .section__input {
            min-width: 190px;
            flex: 1 1 210px;
            max-width: 100%;
        }
        /* Smooth out legacy Bootstrap 4 container sizing inside .ms-content */
        .ms-content .section,
        .ms-content .job {
            background: transparent;
            padding: 0;
            margin: 0;
            width: 100%;
        }
        .ms-content .page-header {
            margin-bottom: 20px;
        }
        .ms-content .page-title {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: #0F172A;
            margin: 0 0 4px;
        }
        .ms-content .page-subtitle {
            font-size: 14px;
            color: #64748B;
            margin: 0;
        }
    </style>
    @stack('styles')
</head>

<body class="body ms-app-body">
    <div id="app" class="ms-shell">
        <aside class="ms-sidebar" id="msSidebar">
            <div class="ms-sidebar__brand">
                <a href="{{ route('showcase') }}" style="display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit;">
                    <div class="ms-brand-mark">{{ $tenantBrand->logo_text ?? 'GRAMEF' }}</div>
                    <div style="min-width:0;">
                        <div style="font-weight:700;font-size:14px;color:#fff;letter-spacing:-0.02em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            {{ $tenantBrand->brand_name ?? 'GRAMEF InterpretFlow' }}
                        </div>
                        <div style="font-family:var(--font-mono);font-size:10px;color:#94A3B8;letter-spacing:0.1em;text-transform:uppercase;">
                            Stage {{ $tenantBrand->active_delivery_stage ?? 4 }} • {{ $tenantBrand->currency_symbol ?? '£' }} GBP
                        </div>
                    </div>
                </a>
            </div>

            <nav class="ms-sidebar__nav">
                @auth
                    @role('super-admin|admin')
                        <div class="ms-nav-group">
                            <div class="ms-nav-group__label">Executive Smart Helpers</div>
                            <a href="{{ route('admin.dashboard') }}" class="ms-nav-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">
                                <i class="fas fa-tachometer-alt"></i> Command Dashboard
                            </a>
                            <a href="{{ route('admin.traffic-light.index') }}" class="ms-nav-link {{ request()->routeIs('admin.traffic-light.*') ? 'is-active' : '' }}">
                                <i class="fas fa-traffic-light"></i> Traffic-Light Checker
                                <span class="ms-nav-badge">LIVE</span>
                            </a>
                            <a href="{{ route('admin.price-book.index') }}" class="ms-nav-link {{ request()->routeIs('admin.price-book.*') ? 'is-active' : '' }}">
                                <i class="fas fa-calculator"></i> Price Book &amp; Rates
                            </a>
                            <a href="{{ route('admin.credit-control.index') }}" class="ms-nav-link {{ request()->routeIs('admin.credit-control.*') ? 'is-active' : '' }}">
                                <i class="fas fa-hand-holding-usd"></i> Credit Control Chaser
                            </a>
                            <a href="{{ route('admin.reconciliation.index') }}" class="ms-nav-link {{ request()->routeIs('admin.reconciliation.*') ? 'is-active' : '' }}">
                                <i class="fas fa-file-invoice-dollar"></i> Bulk Reconcile &amp; BACS
                            </a>
                            <a href="{{ route('admin.compliance.index') }}" class="ms-nav-link {{ request()->routeIs('admin.compliance.*') ? 'is-active' : '' }}">
                                <i class="fas fa-shield-alt"></i> DBS Expiry Guard
                            </a>
                            <a href="{{ route('admin.white-label.index') }}" class="ms-nav-link {{ request()->routeIs('admin.white-label.*') ? 'is-active' : '' }}">
                                <i class="fas fa-palette"></i> White-Label Studio
                            </a>
                        </div>
                    @endrole

                    <div class="ms-nav-group">
                        <div class="ms-nav-group__label">Core Operations</div>
                        @unlessrole('new-agent')
                            <a href="{{ route('interpreter-jobs.index') }}" class="ms-nav-link {{ request()->is('*interpreter-jobs*') || request()->is('/') ? 'is-active' : '' }}">
                                <i class="fas fa-calendar-check"></i> Job Board
                            </a>
                        @endunlessrole

                        @role('admin|client')
                            <a href="{{ route('interpreter-jobs.create') }}" class="ms-nav-link {{ request()->routeIs('interpreter-jobs.create') ? 'is-active' : '' }}">
                                <i class="fas fa-magic"></i> Quick Paste Booking
                            </a>
                        @endrole

                        @role('agent')
                            <a href="{{ route('agent.dashboard') }}" class="ms-nav-link {{ request()->routeIs('agent.dashboard') ? 'is-active' : '' }}">
                                <i class="fas fa-tachometer-alt"></i> Interpreter Dashboard
                            </a>
                            <a href="{{ route('agent.job-entry.pending') }}" class="ms-nav-link {{ request()->is('*job-entry*') ? 'is-active' : '' }}">
                                <i class="fas fa-clipboard-list"></i> Job Entries
                            </a>
                            <a href="{{ route('invoices.agent.index') }}" class="ms-nav-link {{ request()->is('*invoices/agent*') ? 'is-active' : '' }}">
                                <i class="fas fa-file-invoice"></i> My Invoices
                            </a>
                            <a href="{{ route('payslips.index') }}" class="ms-nav-link {{ request()->is('*payslips*') ? 'is-active' : '' }}">
                                <i class="fas fa-pound-sign"></i> My Payslips
                            </a>
                        @endrole

                        @role('client')
                            <a href="{{ route('client.dashboard') }}" class="ms-nav-link {{ request()->routeIs('client.dashboard') ? 'is-active' : '' }}">
                                <i class="fas fa-hospital-user"></i> Client Dashboard
                            </a>
                            <a href="{{ route('invoices.client.index') }}" class="ms-nav-link {{ request()->is('*invoices/client*') ? 'is-active' : '' }}">
                                <i class="fas fa-file-invoice-dollar"></i> Invoices
                            </a>
                            <a href="{{ route('receipts.client.index') }}" class="ms-nav-link {{ request()->is('*receipts/client*') ? 'is-active' : '' }}">
                                <i class="fas fa-receipt"></i> Receipts
                            </a>
                        @endrole

                        @unlessrole('admin')
                            @php
                                $hasAgentRole = optional(auth()->user())->hasAnyRole('new-agent|agent');
                                $myAccountUrl = $hasAgentRole
                                    ? route('agents.profile.edit')
                                    : route('clients.profile.edit', ['organisation' => optional(optional(auth()->user())->client)->organisation ?? 0]);
                            @endphp
                            <a href="{{ $myAccountUrl }}" class="ms-nav-link {{ request()->is('*profile*') ? 'is-active' : '' }}">
                                <i class="fas fa-user-circle"></i> My Account
                            </a>
                        @endunlessrole

                        <a href="{{ route('timesheet.index') }}" class="ms-nav-link {{ request()->is('*timesheet*') ? 'is-active' : '' }}">
                            <i class="fas fa-signature"></i> Timesheets
                        </a>
                        <a href="{{ route('feedback.index') }}" class="ms-nav-link {{ request()->is('*feedback*') ? 'is-active' : '' }}">
                            <i class="fas fa-comment-alt"></i> Feedback
                        </a>
                    </div>

                    @role('super-admin|admin')
                        <div class="ms-nav-group">
                            <div class="ms-nav-group__label">Directory &amp; Finance</div>
                            <a href="{{ route('clients.index') }}" class="ms-nav-link {{ request()->is('clients*') ? 'is-active' : '' }}">
                                <i class="fas fa-building"></i> Clients
                            </a>
                            <a href="{{ route('agents.index') }}" class="ms-nav-link {{ request()->is('agents*') ? 'is-active' : '' }}">
                                <i class="fas fa-users"></i> Interpreters
                            </a>
                            <a href="{{ route('companies.index') }}" class="ms-nav-link {{ request()->is('companies*') ? 'is-active' : '' }}">
                                <i class="fas fa-landmark"></i> Companies
                            </a>
                            <a href="{{ route('invoices.admin.index') }}" class="ms-nav-link {{ request()->is('*invoices/admin*') ? 'is-active' : '' }}">
                                <i class="fas fa-file-invoice"></i> Invoices
                            </a>
                            <a href="{{ route('admin.payslips.index') }}" class="ms-nav-link {{ request()->is('*payslips*') ? 'is-active' : '' }}">
                                <i class="fas fa-money-check-alt"></i> Payslips
                            </a>
                            <a href="{{ route('admin.job-payments.index') }}" class="ms-nav-link {{ request()->is('*job-payments*') ? 'is-active' : '' }}">
                                <i class="fas fa-chart-bar"></i> Accounting Reports
                            </a>
                            <a href="{{ route('admin.receipts.index') }}" class="ms-nav-link {{ request()->is('*admin/receipts*') ? 'is-active' : '' }}">
                                <i class="fas fa-receipt"></i> Receipts
                            </a>
                            <a href="{{ route('admin.remittances.index') }}" class="ms-nav-link {{ request()->is('*remittances*') ? 'is-active' : '' }}">
                                <i class="fas fa-upload"></i> Remittances
                            </a>
                            <a href="{{ route('admin.chat.index') }}" class="ms-nav-link {{ request()->is('*admin/chat*') ? 'is-active' : '' }}">
                                <i class="fas fa-comments"></i> Messages
                            </a>
                            <a href="{{ route('report.index', request()->query()) }}" class="ms-nav-link {{ request()->is('report*') ? 'is-active' : '' }}">
                                <i class="fas fa-chart-pie"></i> Reports
                            </a>
                            <a href="{{ route('exports.index') }}" class="ms-nav-link {{ request()->is('exports*') ? 'is-active' : '' }}">
                                <i class="fas fa-download"></i> Exports
                            </a>
                            <a href="{{ route('languages.index') }}" class="ms-nav-link {{ request()->is('languages*') ? 'is-active' : '' }}">
                                <i class="fas fa-language"></i> Languages
                            </a>
                            @role('super-admin')
                                <a href="{{ route('admins.index') }}" class="ms-nav-link {{ request()->is('admins*') ? 'is-active' : '' }}">
                                    <i class="fas fa-user-shield"></i> Admins
                                </a>
                            @endrole
                        </div>
                    @endrole
                @endauth
            </nav>
        </aside>

        <div class="ms-main">
            <header class="ms-topbar">
                <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;">
                    <button type="button" class="ms-mobile-toggle" id="msMobileMenuBtn" aria-label="Toggle Menu">
                        <i class="fas fa-bars"></i>
                    </button>

                    @if (!request()->routeIs('*.create', '*.create_bulk', '*.edit'))
                        @php
                            $headerSearchFormOptions = ['method' => 'GET', 'class' => 'ms-topbar__search'];
                            if (request()->routeIs('interpreter-jobs.*') && !request()->routeIs('interpreter-jobs.index')) {
                                $headerSearchFormOptions['url'] = route('interpreter-jobs.index');
                            } elseif (request()->routeIs('translator-jobs.*') && !request()->routeIs('translator-jobs.index')) {
                                $headerSearchFormOptions['url'] = route('translator-jobs.index');
                            }
                        @endphp
                        {{ Form::open($headerSearchFormOptions) }}
                            <i class="fas fa-search" style="color:#94A3B8;font-size:12px;"></i>
                            {{ Form::text('search', request('search'), ['placeholder' => 'Search job ref, client or invoice...']) }}
                            <button type="submit">Go</button>
                        {{ Form::close() }}
                    @endif
                </div>

                @auth
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div class="ms-role-switcher" title="Switch Demo Role View in 1 Click">
                            <a href="{{ route('demo.quick-login.get', 'super-admin') }}" class="ms-role-pill {{ auth()->user()->hasRole('super-admin') ? 'is-current' : '' }}">Super-Admin</a>
                            <a href="{{ route('demo.quick-login.get', 'admin') }}" class="ms-role-pill {{ auth()->user()->hasRole('admin') && !auth()->user()->hasRole('super-admin') ? 'is-current' : '' }}">Admin</a>
                            <a href="{{ route('demo.quick-login.get', 'agent') }}" class="ms-role-pill {{ auth()->user()->hasRole('agent') ? 'is-current' : '' }}">Interpreter</a>
                            <a href="{{ route('demo.quick-login.get', 'client') }}" class="ms-role-pill {{ auth()->user()->hasRole('client') ? 'is-current' : '' }}">Client</a>
                        </div>

                        <a href="{{ route('showcase') }}" class="btn btn-sm btn-outline-secondary d-none d-md-inline-flex" style="border-radius:999px;font-weight:600;font-size:12px;">
                            <i class="fas fa-sparkles mr-1"></i> Showcase
                        </a>

                        @role('super-admin|admin')
                            <a href="{{ route('admin.chat.index') }}" id="msgNotifIcon" title="Messages" style="position: relative; display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; background: #F1F5F9; text-decoration: none; color: #334155;">
                                <i class="fas fa-comment-dots" style="font-size: 16px;"></i>
                                <span id="msgBadge" style="display: none; position: absolute; top: -2px; right: -2px; background: #e53935; color: #fff; font-size: 10px; font-weight: 700; min-width: 18px; height: 18px; border-radius: 9px; padding: 0 5px; line-height: 18px; text-align: center;">0</span>
                            </a>
                        @endrole

                        <div class="d-none d-sm-flex flex-column text-right" style="line-height:1.2;">
                            <span style="font-size:13px;font-weight:600;color:#0F172A;">{{ auth()->user()->getFullName() }}</span>
                            <span style="font-family:var(--font-mono);font-size:10px;color:#64748B;">{{ auth()->user()->getUserLevel() }}</span>
                        </div>

                        @if (session()->has('impersonate'))
                            <a class="btn btn-sm btn-warning" href="{{ route('unimpersonate') }}">Stop Impersonate</a>
                        @endif

                        <a class="btn btn-sm btn-dark" style="border-radius:999px;padding:6px 14px;font-weight:600;font-size:12px;" href="{{ route('logout') }}"
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            Logout
                        </a>
                        {{ Form::open(['route' => 'logout', 'class' => 'd-none', 'id' => 'logout-form']) }}{{ Form::close() }}
                    </div>
                @endauth
            </header>

            <main class="ms-content">
                @include('layouts.flash')
                <div class="container content">
                    @yield('content')
                </div>
            </main>
        </div>

        {{-- Global modals (cancel/message/agent card) and shared modal stack --}}
        @include('partials.global-modals')
        @stack('modals')
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.min.js"></script>
    @php $mixManifest = public_path('mix-manifest.json'); @endphp
    @if (file_exists($mixManifest))
        <script src="{{ mix('js/app.js') }}"></script>
    @else
        <script src="{{ asset('js/app.js') }}"></script>
    @endif
    <script>
        (function () {
            var m = document.querySelector('meta[name="csrf-token"]');
            if (window.axios) {
                var t = m && m.getAttribute('content');
                if (t && !window.axios.defaults.headers.common['X-CSRF-TOKEN']) {
                    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = t;
                }
                window.axios.defaults.withCredentials = true;
            }
        })();
    </script>
    <script>
        (function () {
            function loadScript(src, cb) {
                var s = document.createElement('script');
                s.src = src;
                s.async = true;
                s.onload = cb || function () { };
                document.head.appendChild(s);
            }
            if (typeof window.flatpickr !== 'function') {
                loadScript('https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js', function () {
                    if (typeof window.flatpickr !== 'function' && typeof flatpickr === 'function') {
                        window.flatpickr = flatpickr;
                    }
                });
            }
        })();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/signature_pad@2.3.2/dist/signature_pad.min.js"></script>
    <script>
        var canvas = document.getElementById('signature-pad');
        if (canvas) {
            var signaturePad = new SignaturePad(canvas);

            var clearButton = document.getElementById('clear');
            if (clearButton) {
                clearButton.addEventListener('click', function (e) {
                    e.preventDefault();
                    signaturePad.clear();
                });
            }

            var saveButton = document.getElementById('save');
            if (saveButton) {
                saveButton.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (signaturePad.isEmpty()) {
                        alert('Please provide a signature first.');
                    } else {
                        var dataURL = signaturePad.toDataURL('image/png');
                        var signatureInput = document.getElementById('signature');
                        var signatureForm = document.getElementById('signature-form');
                        var agentPreview = document.getElementById('signature-preview');
                        var clientPreview = document.getElementById('client-signature-preview');
                        if (agentPreview) agentPreview.src = dataURL;
                        if (clientPreview) clientPreview.src = dataURL;
                        if (signatureInput) signatureInput.value = dataURL;
                        if (signatureForm) signatureForm.submit();
                    }
                });
            }
        }
    </script>

    <script src="{{ asset('js/confirm.js') }}"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var mobileBtn = document.getElementById('msMobileMenuBtn');
            var sidebar = document.getElementById('msSidebar');
            if (mobileBtn && sidebar) {
                mobileBtn.addEventListener('click', function () {
                    sidebar.classList.toggle('is-open');
                });
                document.addEventListener('click', function (e) {
                    if (window.innerWidth <= 1024 && sidebar.classList.contains('is-open')) {
                        if (!sidebar.contains(e.target) && !mobileBtn.contains(e.target)) {
                            sidebar.classList.remove('is-open');
                        }
                    }
                });
            }

            window.confirmCompletion = function (form) {
                var date = form.getAttribute('data-appointment-date') || '';
                var time = form.getAttribute('data-appointment-time') || '';
                var jobType = form.getAttribute('data-job-type') || (form.action.indexOf('translator-jobs') !== -1 ? 'translation' : 'interpreter');
                var preAppointment = false;
                var appointmentText = '';
                try {
                    if (date) {
                        var dtIso = (date + ' ' + (time || '00:00:00')).replace(' ', 'T');
                        var appointmentDate = new Date(dtIso);
                        var now = new Date();
                        if (!isNaN(appointmentDate.getTime())) {
                            preAppointment = now.getTime() < appointmentDate.getTime();
                            appointmentText = appointmentDate.toLocaleString();
                        }
                    }
                } catch (e) { }
                var baseMsg = 'Are you sure you want to complete this job?';
                var warnMsg = 'This ' + jobType + ' job is scheduled for ' + (appointmentText || (date + ' ' + time)).trim() + '. Completing before the appointment may impact cancellations and billing. Do you want to proceed?';
                var message = preAppointment ? warnMsg : baseMsg;
                var ok = window.confirm(message);
                if (!ok) return false;
                if (preAppointment) {
                    var hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'confirm';
                    hidden.value = 'yes';
                    form.appendChild(hidden);
                }
                return true;
            };
        });
    </script>

    @auth
        <script>
            (function () {
                var url = "{{ route('session.keep-alive') }}";
                var intervalMs = 5 * 60 * 1000;
                function ping() {
                    if (!navigator.onLine) return;
                    fetch(url, { credentials: 'same-origin' }).catch(function () { });
                }
                ping();
                setInterval(ping, intervalMs);
            })();
        </script>
    @endauth

    @stack('scripts')

    @auth
        @role('super-admin|admin')
        <div id="msgToast" style="display:none; position:fixed; top:16px; right:16px; z-index:10000; background:#fff; border-radius:12px; box-shadow:0 4px 24px rgba(0,0,0,0.15); padding:14px 18px; max-width:360px; min-width:280px; cursor:pointer; border-left:4px solid #4A90D9; transform:translateX(120%); transition:transform 0.4s ease; font-family:inherit;">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:36px;height:36px;border-radius:50%;background:#4A90D9;color:#fff; display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;">
                    <i class="fas fa-comment-dots"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <div id="msgToastTitle" style="font-weight:700;font-size:13px;color:#333;margin-bottom:2px;">New message</div>
                    <div id="msgToastBody" style="font-size:12px;color:#777;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">...</div>
                </div>
                <div id="msgToastClose" style="color:#bbb;font-size:16px;cursor:pointer;padding:4px;" title="Dismiss">&times;</div>
            </div>
        </div>
        <script>
            (function () {
                var badge = document.getElementById('msgBadge');
                var icon = document.getElementById('msgNotifIcon');
                var toast = document.getElementById('msgToast');
                var toastTitle = document.getElementById('msgToastTitle');
                var toastBody = document.getElementById('msgToastBody');
                var toastClose = document.getElementById('msgToastClose');
                if (!badge || !icon) return;

                var lastKnownCount = 0;
                var toastTimer = null;

                function showToast(sender, body, conversationId) {
                    if (!toast) return;
                    toastTitle.textContent = 'New message from ' + sender;
                    toastBody.textContent = body;
                    toast.style.display = 'block';
                    toast.onclick = function (e) {
                        if (e.target === toastClose || e.target.parentNode === toastClose) {
                            hideToast();
                        } else {
                            window.location.href = '/admin/chat/' + conversationId;
                        }
                    };
                    setTimeout(function () { toast.style.transform = 'translateX(0)'; }, 50);
                    clearTimeout(toastTimer);
                    toastTimer = setTimeout(hideToast, 8000);
                }

                function hideToast() {
                    if (!toast) return;
                    toast.style.transform = 'translateX(120%)';
                    setTimeout(function () { toast.style.display = 'none'; }, 400);
                }

                if (toastClose) toastClose.onclick = function (e) { e.stopPropagation(); hideToast(); };

                function pollUnread() {
                    fetch('/admin/chat-unread-count', {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            var count = data.count || 0;
                            if (count > 0) {
                                badge.textContent = count > 99 ? '99+' : count;
                                badge.style.display = 'inline-block';
                            } else {
                                badge.style.display = 'none';
                            }
                            if (count > lastKnownCount && data.latest) {
                                showToast(data.latest.sender, data.latest.body, data.latest.conversation_id);
                            }
                            lastKnownCount = count;
                        })
                        .catch(function () { });
                }

                pollUnread();
                setInterval(pollUnread, 30000);
            })();
        </script>
        @endrole
    @endauth
    <script>
        (function () {
            document.addEventListener('click', function (e) {
                var target = e.target.closest('[data-bs-dismiss="modal"],[data-dismiss="modal"],.modal .btn--cancel,.modal .close,.modal .btn-close');
                if (!target) return;
                var modalEl = target.closest('.modal');
                if (!modalEl) return;
                e.preventDefault();
                try {
                    if (window.bootstrap && window.bootstrap.Modal) {
                        var inst = window.bootstrap.Modal.getOrCreateInstance(modalEl);
                        inst.hide();
                        return;
                    }
                } catch (err) { }
                try {
                    if (window.$ && typeof window.$(modalEl).modal === 'function') {
                        window.$(modalEl).modal('hide');
                    }
                } catch (err2) { }
            });
        })();
    </script>
</body>
</html>
