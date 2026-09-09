<table role="presentation" class="btn btn-primary">
    <tbody>
    <tr>
        <td>
            <p>{{ trans('email.recover-account-code-text') }}</p>
            <p>{{ $recoveryCode }}</p>
            <a href={{$recoveryLink}} target="_blank">{{ trans('email.recovery-code-button') }}</a>
        </td>
    </tr>
    </tbody>
</table>
