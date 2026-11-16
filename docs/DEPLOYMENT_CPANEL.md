# AtoZ Interpreting – cPanel Deployment Guide

This guide helps you deploy the Laravel application to cPanel reliably. It assumes you will upload a ZIP of the project root and extract it on the server.

## Requirements
- PHP 8.1 (set via cPanel > Select PHP Version)
- MySQL 5.7+ or MariaDB equivalent
- Required PHP extensions: pdo_mysql, mbstring, tokenizer, ctype, json, openssl, curl, fileinfo, gd, dom
- Web server document root should point to the project’s `public/` directory

## High‑level steps
1. Upload the deployment ZIP to your account (e.g., to `/home/<cpanel_user>/apps/AtoZ-upgrade`) and extract it.
2. In cPanel, set your domain or subdomain’s Document Root to the extracted project’s `public` folder (e.g., `/home/<cpanel_user>/apps/AtoZ-upgrade/public`).
3. Create your environment file:
   - Copy `.env.cpanel.example` to `.env`.
   - Update `APP_URL`, and all DB credentials.
   - Set `APP_ENV=production` and `APP_DEBUG=false` for production.
   - Generate a new `APP_KEY` (see below) and set it in `.env`.
4. Ensure writable permissions for runtime directories:
   - Directories: `storage/` and `bootstrap/cache/` must be writable by the web server user.
   - Typical cPanel permissions: Directories 755 or 775; Files 644 or 664. Use cPanel File Manager to set permissions.
5. Optional: If you have SSH access, you can run artisan optimizations:
   - `php artisan key:generate` (if you didn’t manually set APP_KEY)
   - `php artisan config:cache`
   - `php artisan route:cache`
   - `php artisan view:cache`

## Environment configuration (.env)
Use `.env.cpanel.example` as a base. Important values to review:
- `APP_URL`: Full URL to your site, e.g., `https://portal.example.com`
- `APP_ENV`: `production` for live
- `APP_DEBUG`: `false` in production
- `DB_*`: Set to your cPanel MySQL host, database, username, password
- `MAIL_*`: Choose `MAIL_DRIVER=log` initially to avoid external calls, then configure SMTP/Mailgun when ready
- `LOG_CHANNEL=daily`: Recommended for production rotation

### Generating APP_KEY without artisan
If you cannot run `php artisan key:generate`, you can create a key manually:
1. On any PHP CLI (local), run: `php -r "echo base64_encode(random_bytes(32));"` and prepend `base64:` to the output.
2. Set the value in `.env` as: `APP_KEY=base64:<your_generated_value>`

## Document Root options
- Recommended: Point your domain/subdomain’s Document Root directly to the app’s `public/` folder.
- Alternative (not recommended): Move the contents of `public/` into your current document root and adjust `index.php` paths. This is brittle; prefer changing the document root.

## Post‑deploy checks
- Visit `/login` and ensure the app loads.
- Test key pages:
  - Admin client invoice preview: `/invoices/admin/client/1/preview`
  - Admin client invoice PDF: `/invoices/admin/client/1/pdf`

## Troubleshooting
- 500 error and `APP_KEY` missing: Ensure `.env` exists and contains a valid `APP_KEY`.
- SQL errors: Verify DB credentials in `.env` and PHP extensions (pdo_mysql) are enabled.
- File permission errors: Make sure `storage/` and `bootstrap/cache/` are writable.
- Mail failures: Use `MAIL_DRIVER=log` initially, then configure SMTP/Mailgun with valid credentials.

## Notes on migrations
Migrations are guarded to be idempotent (tables/columns/indexes are checked before changes). In production, apply migrations only from the CLI when needed:
- `php artisan migrate --force`

If you cannot run CLI commands, coordinate migration application outside cPanel or use a staging environment first.