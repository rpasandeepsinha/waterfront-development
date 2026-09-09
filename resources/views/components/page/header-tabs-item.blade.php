<li class="
    @if (!empty($active))
        active
    @endif
">
    <a
        href="#{{ $id }}"
        aria-controls="#{{ $id }}"
        role="tab"
        data-toggle="tab"
    >
        {{ $slot }}
    </a>
</li>
