### Email template

When creating a new email template there are a few steps you need to follow:

1. Create a template model implementing the mailerInterface
   1. This contains the slug for the template
   2. ```php
        public function getTemplateSlug(): string
        {
            return 'account-forgot-password';
        }

2. Create a blade file for the body of you email
   1. Example <template-slug>.blade.php
3. Add a template to the database
   1. Add seeder
   2. The body needs to be the template name prefixed with email. (example: email.subscription-created)
   3. Write insert query for production / acceptance
   4. Make sure the query is executed on all environments

