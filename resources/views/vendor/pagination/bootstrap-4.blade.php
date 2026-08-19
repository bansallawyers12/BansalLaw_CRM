@if ($paginator->hasPages())
    @php
        $lastPage = $paginator->lastPage();
        $currentPage = $paginator->currentPage();

        // Compact CRM pattern: 1, 2, 3, …, last-1, last (show all when <= 5 pages)
        if ($lastPage <= 5) {
            $pageItems = collect(range(1, $lastPage))->map(fn ($page) => ['type' => 'page', 'page' => $page]);
        } else {
            $pageItems = collect([
                ['type' => 'page', 'page' => 1],
                ['type' => 'page', 'page' => 2],
                ['type' => 'page', 'page' => 3],
                ['type' => 'dots'],
                ['type' => 'page', 'page' => $lastPage - 1],
                ['type' => 'page', 'page' => $lastPage],
            ]);
        }
    @endphp
    <nav>
        <ul class="pagination">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <li class="disabled" aria-disabled="true">
                    <span>&laquo;</span>
                </li>
            @else
                <li>
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo;</a>
                </li>
            @endif

            {{-- Page Numbers --}}
            @foreach ($pageItems as $item)
                @if ($item['type'] === 'dots')
                    <li class="disabled" aria-disabled="true">
                        <span>&hellip;</span>
                    </li>
                @elseif ($item['page'] == $currentPage)
                    <li class="active" aria-current="page">
                        <span>{{ $item['page'] }}</span>
                    </li>
                @else
                    <li>
                        <a href="{{ $paginator->url($item['page']) }}">{{ $item['page'] }}</a>
                    </li>
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <li>
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next">&raquo;</a>
                </li>
            @else
                <li class="disabled" aria-disabled="true">
                    <span>&raquo;</span>
                </li>
            @endif
        </ul>
    </nav>
@endif
