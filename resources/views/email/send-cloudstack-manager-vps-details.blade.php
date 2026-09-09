<ul>
    <li>{{ trans('email.send_cloudstack_manager_vps_details.username') }} {{ $username }}</li>
    <li>
        @isset($password)
            {{ trans('email.send_cloudstack_manager_vps_details.password') }} {{ $password }}
        @else
            {{ trans('email.send_cloudstack_manager_vps_details.ssh-keys') }}
        @endisset
    </li>
    <li>{{ trans('email.send_cloudstack_manager_vps_details.ipaddress') }} {{ $ipaddress }}</li>
    <li>{{ trans('email.send_cloudstack_manager_vps_details.ip6address') }} {{ $ip6address }}</li>
</ul>
