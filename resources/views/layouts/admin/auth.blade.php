@extends('layouts.admin.page-wrapper')

@section('page-body')
    <main class="login-page | container-fluid">
        <div class="login-page-content">
            <div class="login-page-form">

                @include('layouts.admin.brand', ['extraClass' => 'navbar-brand-login'])

                @include('layouts.admin.alerts')

                @yield('content')

            </div>
        </div>
    </main>
@endsection
