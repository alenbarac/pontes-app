<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## Payment slip email

Each uplatnica is one transactional message with its own PDF. The recipient is `members.invoice_email` only (the “Email za račune” field). Other member email fields are ignored. An empty `invoice_email` is skipped in a bulk send; a single send returns an error.

Staff sends queue one `SendInvoiceMailing` job per invoice. Every environment uses the Laravel `smtp` mailer. `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and `MAIL_FROM_*` are the only mail settings that change between environments.

### Local

Log mail and run the database queue:

```env
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="Pontes App"
QUEUE_CONNECTION=database
MAIL_BULK_SENDS_PER_SECOND=1
```

```bash
php artisan queue:work
```

Logged messages are in `storage/logs/laravel.log`.

To preview the Croatian body and the PDF, use a [Mailtrap Email Sandbox](https://mailtrap.io) inbox with the staging SMTP block below. The sandbox stores the message. It does not deliver to parents. Keep `MAIL_BULK_SENDS_PER_SECOND=1`. The sandbox rejects faster sends.

`php artisan mail:test` sends immediately and skips the queue. Use it to check SMTP credentials:

```bash
php artisan mail:test --to=your-email@example.com
php artisan mail:test --invoice=334
```

The test suite forces `MAIL_MAILER=array` in `phpunit.xml`, so PHPUnit never opens an SMTP connection.

### Laravel Cloud staging (`develop`)

Staging uses Mailtrap **Email Sandbox** and a Laravel Cloud **managed queue**. This environment does not email parents.

In the Cloud dashboard for the staging environment:

1. Attach a managed queue on **Flex**, standard size (about 512 MiB). Cloud sets `QUEUE_CONNECTION=cloud` and injects `queue.connections.cloud` at boot from `LARAVEL_CLOUD_MANAGED_QUEUES_CONFIG`. Leave that connection in place. `sync` runs every slip inside the HTTP request and will time out a group send. The app requires Laravel 11.55+ and `aws/aws-sdk-php`. A Flex worker stops a job after 90 seconds. `SendInvoiceMailing` allows 75 seconds, which covers one PDF and one SMTP send.
2. Set Sandbox SMTP from the inbox credentials (not an Email Sending API token):

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=<sandbox inbox username>
MAIL_PASSWORD=<sandbox inbox password>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="Pontes App"
MAIL_BULK_SENDS_PER_SECOND=1
```

After a `develop` deploy, check one group for one month:

1. Send the group from the app.
2. In the Sandbox inbox, each message is addressed to that member's `invoice_email`, the body is the Croatian uplatnica text, and the PDF is attached.
3. On the member profile, **Evidencija slanja** shows **Poslana** with the send date.
4. Send the same group again and leave resend unconfirmed. Nothing new is queued.

### Production

Production uses Mailtrap **Email Sending** when that environment is ready. Sandbox and Sending are separate products and separate credentials:

```env
MAIL_HOST=live.smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=api
MAIL_PASSWORD=<sending api token>
MAIL_ENCRYPTION=tls
```

Verify SPF and DKIM for `MAIL_FROM_ADDRESS` before the first real send. Raise `MAIL_BULK_SENDS_PER_SECOND` to match the sending plan. Staging keeps `sandbox.smtp.mailtrap.io` and the sandbox inbox password.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
