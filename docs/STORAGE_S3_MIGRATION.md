# Storage Architecture Migration to Amazon S3

## Goals
- Eliminate cPanel disk growth by moving uploads, exports, logs, and persistent files to S3
- Replace local filesystem operations with S3 equivalents while preserving functionality
- Move cache/session off local disk; add monitoring and cleanup

## Environment Variables
Set these in `.env` for production:

```
FILESYSTEM_DRIVER=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=eu-west-2
AWS_BUCKET=...
AWS_PREFIX=atoz
LOG_CHANNEL=stack
LOG_S3_PATH=logs/laravel.log

# Per-area override (optional)
TRANSLATOR_DOCS_DISK=s3
INTERPRETER_DOCS_DISK=s3
PAYMENT_PROOFS_DISK=s3
TIMESHEETS_DISK=s3
EXPORTS_DISK=s3
RECEIPTS_DISK=s3
REMITTANCES_DISK=s3

# Sessions and cache
SESSION_DRIVER=database
CACHE_DRIVER=database
```

## IAM Policy
Attach a policy to the application role permitting scoped access:

```
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:DeleteObject",
        "s3:ListBucket"
      ],
      "Resource": [
        "arn:aws:s3:::<BUCKET>",
        "arn:aws:s3:::<BUCKET>/<PREFIX>/*"
      ]
    }
  ]
}
```

## Application Changes
- Default filesystem driver set to `s3`
- Custom log channel writes logs to `s3://<bucket>/<prefix>/logs/laravel.log`
- Cache/session default drivers set to `database` with migrations for `sessions` and `cache` tables
- Remittance views and export downloads now use S3-aware logic
- Scheduled cleanup removes local storage residue
- Monitoring command reports local and S3 usage

## Migration Steps
1. Create IAM user/role and apply the policy above
2. Create the S3 bucket and optional prefix folder
3. Set `.env` variables per above
4. Run database migrations: `php artisan migrate --force`
5. Sync existing public storage to S3: `php artisan storage:sync-s3`
6. Verify logs are appearing in `logs/laravel.log` in S3
7. Enable scheduled commands via cron for `schedule:run`

## Monitoring
- Run `php artisan monitor:storage` to output JSON usage metrics and log them

## Backward Compatibility
- All file operations continue to function with local storage in non‑production environments
- Per‑area `*_DISK` env overrides allow selective fallback during transition

## Notes
- Storing cache/session directly in S3 is not recommended; database drivers avoid local disk growth and improve reliability
- Temporary rendering uses in‑memory/local temp but is purged daily to prevent growth

