<table role="presentation" border="0" cellpadding="0" cellspacing="0" class="btn btn-primary">
    <tbody>
        <tr>
            <td align="left">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                    <tbody>
                        <tr>
                            <td>
                                <p>{{ trans('email.activate-identity-code-text') }}</p>
                                <p>{{ $activationCode }}</p>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <p>{{ trans('email.activate-account-code-label') }}</p>
                                <p><a href="{{ $activationUrl }}">Klik hier</a></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>
