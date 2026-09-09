<a
    class="
        navbar-brand
        @if (!empty($extraClass))
            {{ $extraClass }}
        @endif
    "
    href="{{ url('/') }}"
>
        <img class="logo" src="{{ asset('dist/admin/images/' . config('app.theme') . '.png') }}">
</a>
