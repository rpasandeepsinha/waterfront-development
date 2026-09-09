<!-- Layouts based on https://github.com/leemunroe/responsive-html-email-template -->

<!DOCTYPE html>
<html lang="{{ Lang::locale() }}">
    <head>
        <meta name="viewport" content="width=device-width" />
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

        @include('email.brands.base')

        @include('email.brands.' . config('app.theme'))
    </head>
    <body>
        <table border="0" cellpadding="0" cellspacing="0" class="body">
            <tr>
                <td>&nbsp;</td>
                <td class="container">
                    <div class="content">
                        <table class="main">
                            @if (!empty ($message))
                                <tr>
                                    <td class="masthead align-center">
                                        <img border="0" width="300" class="logo" alt="logo" src="{{ $message->embed(resource_path($logoPath)) }}">
                                    </td>
                                </tr>
                            @endif

                            <tr>
                                <td class="wrapper">
                                    <table border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td>
                                                @if (!empty($greeting))
                                                    <p>
                                                        {{ $greeting }}
                                                    </p>
                                                @endif
                                                @if (!empty($header))
                                                    <p>
                                                        {!! $header !!}
                                                    </p>
                                                @endif
                                                <p>
                                                    {!! $body !!}
                                                </p>
                                                @if (!empty($footer))
                                                    <p>
                                                        {!! $footer !!}
                                                    </p>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
                <td>&nbsp;</td>
            </tr>
        </table>
    </body>
</html>
