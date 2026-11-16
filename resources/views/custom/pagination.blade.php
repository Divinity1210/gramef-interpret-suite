@if ($paginator->hasPages())
    @php
        $firstItem = ($paginator->count() > 0) ? (($paginator->currentPage() - 1) * $paginator->perPage() + 1) : 0;
        $lastItem = ($paginator->count() > 0) ? (($paginator->currentPage() - 1) * $paginator->perPage() + $paginator->count()) : 0;
        $total = method_exists($paginator, 'total') ? $paginator->total() : $lastItem;
    @endphp
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="pagination-container d-flex align-items-center justify-content-between w-100">
        <div class="d-flex justify-content-between align-items-center w-100 d-sm-none">
            @if ($paginator->onFirstPage())
                <span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">&laquo; Prev</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-sm btn-outline-secondary" rel="prev" aria-label="{{ __('pagination.previous') }}">&laquo; Prev</a>
            @endif

            <span class="small text-muted ms-2 me-2">Showing {{ $firstItem }} to {{ $lastItem }} of {{ $total }}</span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-sm btn-outline-secondary" rel="next" aria-label="{{ __('pagination.next') }}">Next &raquo;</a>
            @else
                <span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true" aria-label="{{ __('pagination.next') }}">Next &raquo;</span>
            @endif
        </div>

        <div class="d-none d-sm-flex w-100 align-items-center justify-content-between">
            <div>
                <p class="small text-muted mb-0">
                    Showing <span class="fw-medium">{{ $firstItem }}</span> to <span class="fw-medium">{{ $lastItem }}</span> of <span class="fw-medium">{{ $total }}</span> results
                </p>
            </div>

            <div>
                <ul class="pagination pagination-sm mb-0">
                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <li class="page-item disabled" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                            <span class="page-link" aria-hidden="true">&laquo;</span>
                        </li>
                    @else
                        <li class="page-item">
                            <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}">&laquo;</a>
                        </li>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                                @else
                                    <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <li class="page-item">
                            <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}">&raquo;</a>
                        </li>
                    @else
                        <li class="page-item disabled" aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                            <span class="page-link" aria-hidden="true">&raquo;</span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>
@endif

@push('styles')
<style>
.pagination-container { margin-bottom: 1.5rem; padding-bottom: .5rem; }
.pagination { margin-bottom: 1.5rem; padding-bottom: .5rem; }
.pagination .page-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    padding: 0 .5rem;
}
.pagination-info { white-space: nowrap; }
</style>
@endpush