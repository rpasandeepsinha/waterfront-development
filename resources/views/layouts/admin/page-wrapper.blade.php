<!DOCTYPE html>
<html lang="{{ Lang::locale() }}" class="js-language">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title') - {{ config('app.name') }}</title>
    <link href="{{ mix("css/main.css", 'dist/admin') }}" rel="stylesheet">
    <script src="{{ asset("dist/admin/js/modernizr.js") }}"></script>
</head>
<body>
    <div class="js-vue">
        @yield('page-body')
    </div>

    <script src="{{ mix("js/legacy-vendor.js", 'dist/admin') }}"></script>
    <script src="{{ mix("js/legacy.js", 'dist/admin') }}"></script>
    <script src="{{ mix("js/manifest.js", 'dist/admin') }}"></script>
    <script src="{{ mix("js/vendor.js", 'dist/admin') }}"></script>
    <script src="{{ mix("js/app.js", 'dist/admin') }}"></script>
</body>
</html>
