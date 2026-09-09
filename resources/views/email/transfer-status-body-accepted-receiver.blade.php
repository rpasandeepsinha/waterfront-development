@inject('translationLoader', 'Modules\Translation\Loaders\TranslationLoader')

    @foreach ($domains as $domain)
        <ul>
            <li>
                {{ $domain ?? '' }}
            </li>
        </ul>
    @endforeach
