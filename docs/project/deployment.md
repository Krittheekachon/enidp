# Deployment Checklist

## Before deployment

1. Copy `.env.production.example` to the server's `.env` and replace every placeholder.
2. Generate `APP_KEY` once with `php artisan key:generate`. Never copy a development key to production.
3. Confirm `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE=Asia/Bangkok`, HTTPS `APP_URL`, and `SESSION_SECURE_COOKIE=true`.
4. Keep `MOCK_SSO_ENABLED=false`. Mock SSO routes must never be available in production.
5. Until production email is approved, keep `MAIL_NOTIFICATIONS_ENABLED=false`, `MAIL_MAILER=log`, `MAIL_SEND_TO_REAL_USERS=false`, and `MAIL_DEV_TO=`.
6. Back up PostgreSQL before applying migrations.
7. Ensure `storage` and `bootstrap/cache` are writable by the PHP runtime. IDP evidence is stored on the private local disk and must not be exposed through the public web root.
8. Confirm how the first active admin account will be provisioned or imported. Production seeding intentionally does not create demo users; never run `DemoUserSeeder`, `DemoIdpGapSeeder`, or `DemoFacultyAnalyticsSeeder` in production.
9. Confirm the assessment round has all three dates and that they are ordered correctly. Round activation validates the schedule only; assessment and IDP readiness are checked per user when each workflow becomes available.

## Release commands

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan storage:link
```

Restart the PHP runtime after the release so OPcache cannot serve old code. Run the configured queue worker under a process supervisor when the deployment uses queued jobs.

Run Laravel's scheduler every minute so notification digests can execute:

```cron
* * * * * cd /path/to/application && php artisan schedule:run >> /dev/null 2>&1
```

When email is ready, configure the real mail transport first. Test with `MAIL_SEND_TO_REAL_USERS=false` and a controlled `MAIL_DEV_TO`, then set `MAIL_SEND_TO_REAL_USERS=true` and clear cached configuration. Do not enable `MAIL_NOTIFICATIONS_ENABLED` until both recipient modes have been verified.

## Smoke checks

- `/up` returns HTTP 200.
- `/mock-sso` returns HTTP 404 in production.
- Public registration routes return HTTP 404.
- An active admin can log in with username and password.
- Existing accounts without a username can still log in with their full email address until an admin assigns a username.
- Self-service password reset routes return HTTP 404; users who forget their password must contact an admin.
- A suspended account cannot log in and an existing session is ended.
- Employee/HR dashboards do not receive the global user list.
- Admin and HR write routes reject unauthorized roles.
- Self-assessment and supervisor actions stop at their configured dates; IDP remains usable while the same round stays active.
- Creating and activating a new round does not reuse assessment or IDP records from the old round.
