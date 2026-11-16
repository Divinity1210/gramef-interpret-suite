{{-- # --}}
@extends('layouts.app')

@section('content')

<main class="job">

    @include('partials.job-header', [ 'job' => $interpreterJob, 'type' => 'interpreter-jobs' ])

    <div class="row">
        <div class="col-xl-6 overflow-auto">
            <table class="table table--detail shadow-none w-100 mb-5 mb-xl-0">
                <tr class="table__row table__row--short">
                    <td class="p-0 h-100">
                        <div class="job__detail pl-4 d-flex align-items-center h-100">
                            Company name:
                        </div>
                    </td>
                    <td class="p-0 h-100">
                        <div class="text-black pl-4 d-flex align-items-center h-100">
                            {{ $interpreterJob->client->organisation->company->name ?? 'n/a' }}
                        </div>
                    </td>
                </tr>
                <tr class="table__row table__row--short">
                    <td class="p-0 h-100">
                        <div class="job__detail pl-4 d-flex align-items-center h-100">
                            Client name:
                        </div>
                    </td>
                    <td class="p-0 h-100">
                        <div class="text-black pl-4 d-flex align-items-center h-100">
                            {{ $interpreterJob->client->user->getFullNameWithTitle() }}
                        </div>
                    </td>
                </tr>
                <tr class="table__row table__row--short">
                    <td class="p-0 h-100">
                        <div class="job__detail pl-4 d-flex align-items-center h-100">
                            Language:
                        </div>
                    </td>
                    <td class="p-0 h-100">
                        <div class="text-black pl-4 d-flex align-items-center h-100">
                            {{ $interpreterJob->toLanguage->name }}
                        </div>
                    </td>
                </tr>
                <tr class="table__row table__row--short">
                    <td class="p-0 h-100">
                        <div class="job__detail pl-4 pt-3 d-flex align-items-start h-100">
                            Interpreter details:
                        </div>
                    </td>
                    <td class="text-black pl-4">
                        <div>{{ $interpreterJob->skill->skill }}</div>
                        <div>     @if ($interpreterJob->required_qualified == 1)
            "Court Qualified Interpreter"
        @elseif ($interpreterJob->required_qualified == 2)
                     "A Community Interpreter"
          
        @elseif ($interpreterJob->required_qualified == 3)
            "Level 2,3,4 Community Interpreter"
        @elseif ($interpreterJob->required_qualified == 4)
            "Qualified Translator"
        @endif</div>
        
        <div>@if ($interpreterJob->security_type_id== 1)
                "Enhanced Dbs"
        @elseif ($interpreterJob->require_security == 2)
            "Standard Dbs"
        @elseif ($interpreterJob->require_security == 3)
            "National Security Vettin"
        @elseif ($interpreterJob->require_security == 4)
            "Dbs Not required"
        @endif</div>
                        <div>{{ $interpreterJob->getGenderName() }}</div>
                    </td>
                </tr>
                <tr class="table__row table__row--short">
                    <td class="p-0 h-100">
                        <div class="job__detail pl-4 d-flex align-items-center h-100">
                            Appointment date and time:
                        </div>
                    </td>
                    <td class="p-0 h-100">
                        <div class="text-black pl-4 d-flex align-items-center h-100">
                            <span class="mr-4">{{ $interpreterJob->appointment_date->format('d/m/Y') }}</span>
                            {{ $interpreterJob->start_time }}
                        </div>
                    </td>
                </tr>
                <tr class="table__row table__row--short">
                    <td class="p-0 h-100">
                        <div class="job__detail pl-4 d-flex align-items-center h-100">
                            Appointment duration:
                        </div>
                    </td>
                    <td class="p-0 h-100">
                        <div class="text-black pl-4 d-flex align-items-center h-100">
                            {{ $interpreterJob->formattedDuration }}
                        </div>
                    </td>
                </tr>
                <tr class="table__row table__row--short">
                    <td class="p-0 h-100">
                        <div class="job__detail pl-4 d-flex align-items-center h-100">
                            Service user details:
                        </div>
                    </td>
                    <td class="p-0 h-100">
                        <div class="text-black pl-4 d-flex align-items-center h-100">
                            Not required
                        </div>
                    </td>
                </tr>
                <tr class="table__row table__row--short">
                    <td class="p-0 h-100">
                        <div class="job__detail pl-4 d-flex align-items-center h-100">
                            Special instructions:
                        </div>
                    </td>
                    <td class="p-0 h-100">
                        <div class="text-black pl-4 d-flex align-items-center h-100">
                            {{ $interpreterJob->special_requirements ?? 'None' }}
                        </div>
                    </td>
                </tr>
                <tr class="table__row table__row--short">
                    <td class="p-0 h-100">
                        <div class="job__detail pl-4 d-flex align-items-center h-100">
                            Client reference:
                        </div>
                    </td>
                    <td class="p-0 h-100">
                        <div class="text-black pl-4 d-flex align-items-center h-100">
                            {{ $interpreterJob->client_reference ?? 'n/a' }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        @if ($interpreterJob->hasAddressFields())
            <div class="col-xl-6 d-lg-flex flex-lg-column">
                <div class="position-relative">
                    <div id="map" class="map position-relative" data-lat="{{ $interpreterJob->latitude }}" data-lng="{{ $interpreterJob->longitude }}" data-dest="{{ ($interpreterJob->latitude && $interpreterJob->longitude) ? ($interpreterJob->latitude . ',' . $interpreterJob->longitude) : trim(implode(', ', array_filter([$interpreterJob->address_line_1, $interpreterJob->address_line_2, $interpreterJob->county, $interpreterJob->postcode]))) }}"></div>
                    @php
                        // Build a robust Google Maps directions URL that works for admins (no agent) and agents alike
                        $originLat = optional(optional(auth()->user())->agent)->latitude;
                        $originLng = optional(optional(auth()->user())->agent)->longitude;

                        // Prefer job coordinates when available; otherwise fall back to a formatted address
                        $destinationString = ($interpreterJob->latitude && $interpreterJob->longitude)
                            ? $interpreterJob->latitude . ',' . $interpreterJob->longitude
                            : trim(implode(', ', array_filter([
                                $interpreterJob->address_line_1,
                                $interpreterJob->address_line_2,
                                $interpreterJob->county,
                                $interpreterJob->postcode,
                            ])));

                        $mapsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($destinationString);
                        if ($originLat && $originLng) {
                            $mapsUrl .= '&origin=' . urlencode($originLat . ',' . $originLng);
                        }
                    @endphp
                    <a class="btn btn--secondary btn--get-directions"
                       href="{{ $mapsUrl }}"
                       target="_blank" rel="noopener noreferrer">Get directions</a>
                </div>
                <div class="bg-white p-4 pb-md-5 flex-lg-fill">
                    <div class="row">
                        <div class="col-xl-6">
                            <div class="job__detail mb-2">Appointment address</div>
                            <address class="text-black">
                                <div class="job__address mb-1">{{ $interpreterJob->address_line_1 }}</div>
                                <div class="job__address mb-1">{{ $interpreterJob->address_line_2 }}</div>
                                <div class="job__address mb-1">{{ $interpreterJob->county }}</div>
                                <div class="job__address mb-1">{{ $interpreterJob->postcode }}</div>
                            </address>
                        </div>
                        <div class="col-xl-6">
                            <div class="job__detail mb-2">Contact information of person on arrival</div>
                            <div class="job__address text-black">
                                {{ $interpreterJob->contact_information_is_same_as_account ? 'Same as client account' : $interpreterJob->contact_information  }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="col-12">

            @if ($interpreterJob->getStatusForAgent() == 'matched')
                {{ Form::open(['route' => ['interpreter-jobs.matched.reject', $interpreterJob->id]]) }}
                    {{ Form::submit('Reject Job', ['class' => 'btn btn--reject btn--long mt-4']) }}
                {{ Form::close() }}
            @endif

            @auth
            @role('agent')
            <div class="mt-4 d-flex flex-wrap">
                @if ($interpreterJob->statusName !== 'cancelled')
                <a href="{{ route('agent.job-entry.create', ['job_type' => 'interpreter', 'job_id' => $interpreterJob->id]) }}"
                   class="btn btn--primary mr-2 mb-2">
                    Add/Update Amounts
                </a>
                @endif
                <a href="{{ route('agent.job-entry.pending') }}" class="btn btn-outline-secondary mr-2 mb-2">
                    View Pending Entries
                </a>
                <a href="{{ route('invoices.agent.create') }}" class="btn btn-success mb-2">
                    Create Invoice
                </a>
                <a href="{{ route('interpreter-jobs.appointment-pdf', $interpreterJob) }}" class="btn btn-outline-primary ml-2 mb-2" target="_blank" rel="noopener noreferrer">
                    Appointment PDF
                </a>
            @php 
                $eligibleStatus = ($interpreterJob->statusName == 'completed') || (($interpreterJob->statusName == 'cancelled') && method_exists($interpreterJob, 'isLateCancellation') && $interpreterJob->isLateCancellation());
                $isPaid = \App\ClientInvoiceItem::where('job_id', $interpreterJob->id)->whereHas('invoice', function($q){ $q->where('status','paid'); })->exists();
            @endphp
            @if (!empty(optional($interpreterJob->timesheet)->job_id) && $eligibleStatus && !$isPaid)
                <a href="{{ route('timesheet.edit', $interpreterJob) }}" class="btn btn--secondary ml-2 mb-2">Sign Timesheet</a>
            @endif
                <a href="{{ route('interpreter-jobs.timesheets.index', $interpreterJob) }}" class="btn btn--secondary ml-2 mb-2">
                    Upload signed timesheet
                </a>
                @if(optional($interpreterJob->timesheet)->id)
                    <a href="{{ route('timesheet-pdf', ['id' => $interpreterJob->timesheet->id]) }}" class="btn btn--primary ml-2 mb-2">
                        Print timesheet
                    </a>
                @endif
                @if ($interpreterJob->getStatusForAgent() == 'assigned' && $interpreterJob->statusName != 'completed' && auth()->user()->can('unassign', $interpreterJob) && $interpreterJob->matchedAgentId)
                    {{ Form::open([
                        'method' => 'DELETE',
                        'route' => ['interpreter-jobs.matched.unassign', $interpreterJob->id, $interpreterJob->matchedAgentId],
                        'class' => 'd-inline-block ml-2 mb-2',
                    ]) }}
                    {{ Form::submit('Unassign', [
                        'class' => 'btn btn--delete',
                        'onclick' => "return confirm('Are you sure you want to be unassigned from this job?')",
                    ]) }}
                    {{ Form::close() }}
                @endif
            </div>
            @endrole
            @endauth

            @auth
            @hasanyrole('admin|client')
            <div class="mt-4 d-flex flex-wrap">
                @php
                    $hasDigitalTimesheet = !empty(optional($interpreterJob->timesheet)->agent_signature) && !empty(optional($interpreterJob->timesheet)->client_signature);
                    $uploadedSigned = $interpreterJob->documents->firstWhere('type', config('enums.document_types')['signed_timesheet']);
                @endphp
                @role('admin')
                    <a href="{{ route('interpreter-jobs.appointment-pdf', $interpreterJob) }}" class="btn btn-outline-primary ml-2 mb-2" target="_blank" rel="noopener noreferrer">
                        Appointment PDF
                    </a>
                    <a href="{{ route('interpreter-jobs.appointment-pdf', $interpreterJob) }}?regen=1" class="btn btn-warning ml-2 mb-2" target="_blank" rel="noopener noreferrer" onclick="return confirm('Regenerate the appointment snapshot and PDF? This will create a new versioned snapshot for auditing.');">
                        Regenerate Snapshot + PDF
                    </a>
                    @php
                        $snapshots = \App\JobSnapshot::where('job_id', $interpreterJob->id)->orderByDesc('version')->get();
                    @endphp
                    @if($snapshots->count())
                        <div class="ml-2 mb-2 d-flex align-items-center">
                            <label for="snapshotVersion" class="mr-2 mb-0">Download specific version:</label>
                            <form action="{{ route('interpreter-jobs.appointment-pdf', $interpreterJob) }}" method="GET" target="_blank" rel="noopener noreferrer" class="d-flex align-items-center">
                                <select id="snapshotVersion" name="version" class="form-control form-control-sm mr-2">
                                    @foreach($snapshots as $snap)
                                        <option value="{{ $snap->version }}">v{{ $snap->version }} ({{ $snap->created_at->format('d/m/Y H:i') }})</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-outline-secondary btn-sm">Download</button>
                            </form>
                        </div>
                    @endif
                @endrole
                @if($hasDigitalTimesheet)
                    <a href="{{ route('timesheet-pdf', ['id' => $interpreterJob->timesheet->id]) }}" class="btn btn--primary ml-2 mb-2">
                        Print timesheet
                    </a>
                @endif
                @if($uploadedSigned)
                    <a href="{{ $uploadedSigned->fullUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn--secondary ml-2 mb-2">
                        View uploaded timesheet
                    </a>
                @endif
            </div>
            @endhasanyrole
            @endauth

            <hr class="mt-4">
            <footer class="d-flex justify-content-between">
                <span>Submitted: {{ $interpreterJob->created_at->format('d/m/Y') }}</span>
                <span>Last updated: {{ $interpreterJob->updated_at->format('d/m/Y') }}</span>
            </footer>
        </div>
    </div>
</main>

@endsection

@push('scripts')
    @if ($interpreterJob->hasAddressFields())
        <script>
            function showFallbackMap() {
                var mapEl = document.getElementById('map');
                if (!mapEl) return;
                var dest = mapEl.dataset.dest || '';
                if (!dest) return;
                var iframe = document.createElement('iframe');
                iframe.src = 'https://www.google.com/maps?q=' + encodeURIComponent(dest) + '&output=embed';
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.style.border = '0';
                iframe.setAttribute('allowfullscreen', '');
                mapEl.innerHTML = '';
                mapEl.appendChild(iframe);
            }

            function initMap() {
                var mapEl = document.getElementById('map');
                if (!mapEl) return;
                var lat = parseFloat(mapEl.dataset.lat);
                var lng = parseFloat(mapEl.dataset.lng);
                if (isNaN(lat) || isNaN(lng)) { showFallbackMap(); return; }
                var jobLocation = { lat: lat, lng: lng };
                var map = new google.maps.Map(mapEl, { center: jobLocation, zoom: 8 });
                new google.maps.Marker({ position: jobLocation, map: map });
            }

            window.gm_authFailure = function () { showFallbackMap(); };
            document.addEventListener('DOMContentLoaded', function () {
                setTimeout(function () {
                    if (typeof window.google === 'undefined' || typeof google === 'undefined' || typeof google.maps === 'undefined') {
                        showFallbackMap();
                    }
                }, 4000);
            });
        </script>
        @if ($interpreterJob->latitude && $interpreterJob->longitude && config('services.google.maps_api_key'))
            <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}&callback=initMap" async defer></script>
        @else
            <script>document.addEventListener('DOMContentLoaded', showFallbackMap);</script>
        @endif
    @endif
@endpush
