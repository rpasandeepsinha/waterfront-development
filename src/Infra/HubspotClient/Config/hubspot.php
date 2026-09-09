<?php

declare (strict_types=1);

use Illuminate\Support\Env;

return [
    'marketing_mail_actions' => Env::get('HUBSPOT_MARKETING_MAIL_ACTIONS_KEY', ''),
    'marketing_mail_surveys' => Env::get('HUBSPOT_MARKETING_MAIL_SURVEYS_KEY', ''),
    'marketing_mail_newsletter' => Env::get('HUBSPOT_MARKETING_MAIL_NEWSLETTER_KEY', ''),
    'email_history_retention_days' => 90,
];
