<header class="
    page-header
    @if (!empty($tabs))
        page-header-tabs
    @endif
">
    <h1 class="page-header-title">{{ $slot }}</h1>
    @if (!empty($actions))
        <nav class="page-header-actions">
            {{ $actions }}
        </nav>
    @endif

    @if (!empty($tabs))
        {{ $tabs }}
    @endif
</header>
