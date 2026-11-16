@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title">Activity Log</h1>
                    <p class="page-subtitle">Clear, human-readable timeline of requests and actions</p>
                </div>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0">Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.activity.index') }}" class="form-inline">
                <div class="form-group mr-2">
                    <label for="action" class="mr-2">Action</label>
                    <select name="action" id="action" class="form-control form-control-sm">
                        <option value="">Any</option>
                        @foreach($actions as $a)
                            <option value="{{ $a }}" @if(($filters['action'] ?? '') === $a) selected @endif>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mr-2">
                    <label for="entity_type" class="mr-2">Entity</label>
                    <select name="entity_type" id="entity_type" class="form-control form-control-sm">
                        <option value="">Any</option>
                        @foreach($entities as $e)
                            <option value="{{ $e }}" @if(($filters['entity_type'] ?? '') === $e) selected @endif>{{ $e }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mr-2">
                    <label for="actor_id" class="mr-2">Actor</label>
                    <select name="actor_id" id="actor_id" class="form-control form-control-sm">
                        <option value="">Any</option>
                        @foreach($actors as $actor)
                            <option value="{{ $actor->id }}" @if(($filters['actor_id'] ?? '') == $actor->id) selected @endif>{{ $actor->getFullName() }} ({{ $actor->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mr-2">
                    <label for="user_id" class="mr-2">User</label>
                    <select name="user_id" id="user_id" class="form-control form-control-sm">
                        <option value="">Any</option>
                        @foreach($actors as $actor)
                            <option value="{{ $actor->id }}" @if(($filters['user_id'] ?? '') == $actor->id) selected @endif>{{ $actor->getFullName() }} ({{ $actor->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mr-2">
                    <label for="method" class="mr-2">Method</label>
                    <select name="method" id="method" class="form-control form-control-sm">
                        @php $methods = ['','GET','POST','PUT','PATCH','DELETE']; @endphp
                        @foreach($methods as $m)
                            <option value="{{ $m }}" @if(($filters['method'] ?? '') === $m) selected @endif>{{ $m === '' ? 'Any' : $m }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mr-2">
                    <label for="status" class="mr-2">Status</label>
                    <input type="number" min="100" max="599" step="1" name="status" id="status" value="{{ $filters['status'] ?? '' }}" class="form-control form-control-sm" placeholder="e.g. 200" />
                </div>
                <div class="form-group mr-2">
                    <label for="path" class="mr-2">Path contains</label>
                    <input type="text" name="path" id="path" value="{{ $filters['path'] ?? '' }}" class="form-control form-control-sm" placeholder="/admin" />
                </div>
                <div class="form-group mr-2">
                    <label for="start_date" class="mr-2">From</label>
                    <input type="date" name="start_date" id="start_date" value="{{ $filters['start_date'] ?? '' }}" class="form-control form-control-sm" />
                </div>
                <div class="form-group mr-2">
                    <label for="end_date" class="mr-2">To</label>
                    <input type="date" name="end_date" id="end_date" value="{{ $filters['end_date'] ?? '' }}" class="form-control form-control-sm" />
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                <a href="{{ route('admin.activity.index') }}" class="btn btn-light btn-sm ml-2">Reset</a>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Activity</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Actor</th>
                            <th>Event</th>
                            <th>Details</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td>{{ optional($log->created_at)->format('d/m/Y H:i') }}</td>
                                <td>
                                    {{ optional($log->actor)->getFullName() ?? 'System' }}
                                    @if(optional($log->actor)->email)
                                        <div class="small text-muted">{{ optional($log->actor)->email }}</div>
                                    @endif
                                </td>
                                <td>
                                    @php $md = is_array($log->metadata) ? $log->metadata : []; @endphp
                                    @if($log->action === 'request')
                                        @php
                                            $status = (int) ($md['status'] ?? 0);
                                            $statusClass = $status >= 500 ? 'badge-danger' : ($status >= 400 ? 'badge-warning' : ($status >= 300 ? 'badge-info' : 'badge-success'));
                                        @endphp
                                        <div>
                                            <span class="badge {{ $statusClass }}">{{ $md['method'] ?? '—' }} {{ $md['uri'] ?? '' }} → {{ $md['status'] ?? '—' }}</span>
                                        </div>
                                        <div class="small text-muted">
                                            @if(!empty($md['route_name'])) Route: {{ $md['route_name'] }} @endif
                                            @if(!empty($md['is_redirect']) && !empty($md['redirect_to'])) • Redirect to: {{ $md['redirect_to'] }} @endif
                                        </div>
                                    @elseif($log->action === 'snapshot' && $log->entity_type === 'Payslip')
                                        <div>
                                            <span class="badge badge-info">Payslip #{{ $log->entity_id }} bank details snapshot captured ({{ $md['status'] ?? '' }})</span>
                                        </div>
                                    @elseif($log->action === 'recall' && $log->entity_type === 'Payslip')
                                        <div>
                                            <span class="badge badge-warning">Payslip #{{ $log->entity_id }} recalled to pending</span>
                                            @if(!empty($md['reason']))<div class="small text-muted">Reason: {{ $md['reason'] }}</div>@endif
                                        </div>
                                    @elseif(in_array($log->action, ['timesheet_client_invoice_created','collective_client_invoice_created','client_invoice_created_from_job_payments']) && $log->entity_type === 'ClientInvoice')
                                        <div>
                                            <span class="badge badge-success">Client Invoice #{{ $log->entity_id }} created</span>
                                            <div class="small text-muted">Total: £{{ number_format((float)($md['total'] ?? 0), 2) }}</div>
                                        </div>
                                    @elseif($log->action === 'remittance_status_updated' && $log->entity_type === 'Remittance')
                                        <div>
                                            <span class="badge badge-primary">Remittance #{{ $log->entity_id }} status → {{ strtoupper((string)($md['status'] ?? '')) }}</span>
                                        </div>
                                    @elseif(in_array($log->action, ['login','logout','login_failed','password_reset']))
                                        <div>
                                            <span class="badge badge-light text-uppercase">{{ $log->action }}</span>
                                            @if(!empty($md['email']))<div class="small text-muted">Email: {{ $md['email'] }}</div>@endif
                                        </div>
                                    @else
                                        <div>
                                            <span class="badge badge-light text-uppercase">{{ $log->action }}</span>
                                            @if($log->entity_type)
                                                <span class="ml-1">• {{ $log->entity_type }} @if($log->entity_id)#{{ $log->entity_id }}@endif</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if(!empty($md))
                                        <button class="btn btn-link btn-sm p-0" type="button" data-toggle="collapse" data-target="#md-{{ $log->id }}" aria-expanded="false" aria-controls="md-{{ $log->id }}">Show details</button>
                                        <div class="collapse mt-2" id="md-{{ $log->id }}">
                                            <pre class="small bg-light p-2">{{ json_encode($md, JSON_PRETTY_PRINT) }}</pre>
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $log->ip_address ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-muted">No activity found for the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>
                {{ $logs->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.page-header {
    margin-bottom: 1rem;
}
.page-title {
    font-size: 1.5rem;
    font-weight: 600;
}
.page-subtitle {
    color: #666;
}
</style>
@endpush

@push('scripts')
<script>
// No-op: Bootstrap collapse used for details toggle if available
</script>
@endpush