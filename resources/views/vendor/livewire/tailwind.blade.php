@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between gap-4">

        {{-- Result count --}}
        <p class="text-xs text-white/30 hidden sm:block">
            @if ($paginator->firstItem())
                {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} / {{ $paginator->total() }}
            @else
                {{ $paginator->count() }} Einträge
            @endif
        </p>

        {{-- Page buttons --}}
        <div class="flex items-center gap-1">

            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-white/20 cursor-default">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                </span>
            @else
                <button wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled"
                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-white/50 hover:text-white hover:bg-white/[0.08] border border-transparent hover:border-white/10 transition-all duration-150"
                   aria-label="{{ __('pagination.previous') }}">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                </button>
            @endif

            {{-- Page numbers --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="inline-flex items-center justify-center w-8 h-8 text-xs text-white/25 cursor-default">…</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page"
                                  class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs font-semibold text-neon-blue bg-neon-blue/10 border border-neon-blue/30 cursor-default">
                                {{ $page }}
                            </span>
                        @else
                            <button wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                               class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs text-white/50 hover:text-white hover:bg-white/[0.08] border border-transparent hover:border-white/10 transition-all duration-150"
                               aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                {{ $page }}
                            </button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <button wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled"
                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-white/50 hover:text-white hover:bg-white/[0.08] border border-transparent hover:border-white/10 transition-all duration-150"
                   aria-label="{{ __('pagination.next') }}">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                </button>
            @else
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-white/20 cursor-default">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                </span>
            @endif

        </div>
    </nav>
@endif
