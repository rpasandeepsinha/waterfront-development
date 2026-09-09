<ul>
    {{ $domainLink = ($use_ssl ? 'https://' : 'http://') . $domain . ':' . $port }}
    {{ $ipLink =  ($use_ssl ? 'https://' : 'http://') . $ipv4_address . ':' . $port }}
    <li>{{ trans('email.directadmin_details.hostname') }} <a href="{{ $domainLink }}">{{ $domainLink }}</a> {{ trans('email.directadmin_details.or') }} <a href="{{ $ipLink }}">{{ $ipLink }}</a></li>
    <li>{{ trans('email.directadmin_details.username') }} {{ $username }}</li>
    <li>{{ trans('email.directadmin_details.password') }} {{ $password }}</li>
</ul>
