<div>
    <p> {{ trans('email.ssl-renewal-failed-missing-cname-text', [
        'domain' => $domain,
        'cname_name' => $cnameName,
        'cname_value' => $cnameValue,
        'expiry_date' => $expirydate,
    ]) }}
    </p>
</div>
