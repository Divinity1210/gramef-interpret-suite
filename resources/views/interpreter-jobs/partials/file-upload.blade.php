{{-- # --}}
@role('agent')
    @php($signedType = config('enums.document_types')['signed_timesheet'])
    @if((int) $type === (int) $signedType)
        {{-- Optional paper timesheet metadata capture alongside upload --}}
        <div class="mb-3">
            <div class="font-weight-bold mb-2">Paper timesheet details (optional)</div>
            <div class="d-flex flex-wrap">
                <div class="mr-3">
                    <label class="label mb-1" for="agent_start_time">Agent Start Time</label>
                    <input type="time" name="agent_start_time" id="agent_start_time" class="input form__input form__input--smaller">
                </div>
                <div class="mr-3">
                    <label class="label mb-1" for="agent_end_time">Agent End Time</label>
                    <input type="time" name="agent_end_time" id="agent_end_time" class="input form__input form__input--smaller">
                </div>
            </div>
            <div class="d-flex flex-wrap mt-2">
                <div class="mr-3">
                    <label class="label mb-1" for="client_name">Client Name</label>
                    <input type="text" name="client_name" id="client_name" class="input form__input" placeholder="e.g. Jane Doe">
                </div>
                <div class="mr-3">
                    <label class="label mb-1" for="client_designation">Client Position/Designation</label>
                    <input type="text" name="client_designation" id="client_designation" class="input form__input" placeholder="e.g. Case Worker">
                </div>
                <div class="mr-3">
                    <label class="label mb-1" for="client_phone">Client Phone</label>
                    <input type="tel" name="client_phone" id="client_phone" pattern="[0-9+ ]*" class="input form__input form__input--smaller" placeholder="optional">
                </div>
            </div>
            <small class="text-muted">These details will be saved to the job’s timesheet when you upload the signed scan.</small>
        </div>

        {{-- Upload signed timesheet scan --}}
        <translation-file-dropzone
            :existing-documents="{{ json_encode($interpreterJob->documents->where('type', $type)->values()) }}"
            :document-type="{{ $type }}" label="{{ $label }}"
            :metadata-fields='["agent_start_time","agent_end_time","client_name","client_phone","client_designation"]'
            accepted-files="image/*,application/pdf"
            :max-filesize="20"
            url="{{ route('interpreter-jobs.documents.store', $interpreterJob) }}"
            remove-url="{{ route('interpreter-jobs.timesheets.cancel-upload', $interpreterJob) }}"
            :reload-on-success="true"></translation-file-dropzone>
        <small class="text-muted d-block mt-1">Accepted formats: PDF, JPG, PNG. Max size 20 MB.</small>
        @if($interpreterJob->documents->where('type', $type)->count())
            <ul class="list-unstyled mt-2 mb-0">
                @foreach ($interpreterJob->documents->where('type', $type) as $document)
                    <li class="d-flex align-items-center justify-content-between mb-2">
                        <a href="{{ $document->fullUrl }}" class="mr-2">{{ $document->name }}</a>
                        <form
                            action="{{ route('interpreter-jobs.timesheets.cancel-upload', $interpreterJob) }}"
                            method="POST"
                            class="d-inline-block"
                            onsubmit="return confirm('Remove this uploaded timesheet?');"
                        >
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="document_id" value="{{ $document->id }}">
                            <button type="submit" class="btn btn-outline-danger btn--small">Cancel upload</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    @else
        {{-- Generic upload without timesheet metadata --}}
        <translation-file-dropzone
            :existing-documents="{{ json_encode($interpreterJob->documents->where('type', $type)->values()) }}"
            :document-type="{{ $type }}" label="{{ $label }}"
            url="{{ route('interpreter-jobs.documents.store', $interpreterJob) }}"></translation-file-dropzone>
    @endif

@else
    <div class="font-weight-bold mb-2">{{ $label }}</div>

    <ul class="list-unstyled mb-0">

        @forelse ($interpreterJob->documents->where('type', $type) as $document)
            <li class="d-flex align-items-center justify-content-between">
                <a href="{{ $document->fullUrl }}" class="mr-2">
                    {{ $document->name }}
                </a>
                <form action="{{ route('documents.destroy', $document) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Remove this file?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn--small">Remove</button>
                </form>
            </li>
        @empty
            <li>This file has not been uploaded</li>
        @endforelse

    </ul>

@endrole
