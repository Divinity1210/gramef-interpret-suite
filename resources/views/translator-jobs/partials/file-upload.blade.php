@role('agent')

    <translation-file-dropzone
        :existing-documents="{{ json_encode($translatorJob->documents->where('type', $type)->values()) }}"
        :document-type="{{ $type }}"
        label="{{ $label }}"
        url="{{ route('translator-jobs.documents.store', $translatorJob) }}"
    ></translation-file-dropzone>

@else

    <div class="font-weight-bold mb-2">{{ $label }}</div>

    <ul class="list-unstyled mb-0">

        @forelse ($translatorJob->documents->where('type', $type) as $document)
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
