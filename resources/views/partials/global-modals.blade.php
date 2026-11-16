@push('modals')
    {{-- Global modals pushed once for the entire app layout to prevent duplicates --}}
    @include('partials.cancel-modal')

    {{-- Message modal is safe for all roles (shows cancellation messages) --}}
    @include('partials.message-modal')

    {{-- Agent card modal restricted to admin and client roles --}}
    @hasanyrole('admin|client')
        @include('partials.agent-card-modal')
    @endhasanyrole
@endpush