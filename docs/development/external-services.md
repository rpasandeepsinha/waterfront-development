# External services

You can run external services locally for extra testing during development.

## Plesk

[See this confluence page](https://yh-jira.atlassian.net/wiki/spaces/SANDWAVE/pages/1199702123/Setting+up+your+dev+environment#Setting-up-Plesk-locally-for-testing)

## PowerDNS

Make sure the `.env` file is correct:
```
APP_FAKE_POWERDNS_CLIENT=false

# PowerDNS test environment
POWERDNS_API_URL=http://powerdns:8081
POWERDNS_API_KEY=secret
```

If you want to connect to the database, add `127.0.0.1 powerdns_db` to your `/etc/hosts` file to use for the database hostname.

You can then connect with the following credentials:
```
hostname: powerdns_db
username: powerdns
password: secret
database: powerdns
port: 3307
```

## Redirecting database

The database is provisioned automatically, this happens because of the `.sql` files in the infra repository in: `./docker/postgresql/redirects.sql`.

Make sure the `.env` file contains the following keys:
```
REDIRECT_DB_HOST
REDIRECT_DB_PORT
REDIRECT_DB_DATABASE
REDIRECT_DB_DRIVER
REDIRECT_DB_USERNAME
REDIRECT_DB_PASSWORD
```

You can connect to the database locally using your favorite client. See the `.env` file for the credentials
