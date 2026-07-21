# Cloudflare R2 asset storage

Hotel and organization branding belongs in the public asset bucket. Guest identity documents, request attachments, exports, and backups remain private and must not be moved into this public bucket.

Create an R2 API token limited to Object Read & Write for the asset bucket, enable a public development URL or custom asset domain, and configure every Railway service with the same variables:

```env
ASSET_DISK=s3
AWS_ACCESS_KEY_ID=R2_ACCESS_KEY_ID
AWS_SECRET_ACCESS_KEY=R2_SECRET_ACCESS_KEY
AWS_DEFAULT_REGION=auto
AWS_BUCKET=hotelcheckin-assets
AWS_ENDPOINT=https://ACCOUNT_ID.r2.cloudflarestorage.com
AWS_URL=https://YOUR_PUBLIC_R2_DOMAIN
AWS_USE_PATH_STYLE_ENDPOINT=false
```

`AWS_URL` must be the public bucket URL or custom domain, not the S3 API endpoint. Do not include the bucket name at the end unless it is part of the public URL Cloudflare provides.

After deployment, clear cached configuration and run the non-destructive connection check:

```bash
php artisan config:clear
php artisan storage:asset-check
```

The check writes a temporary object, prints its public URL, and deletes it. Upload a hotel logo from Property Settings afterward to verify the full application flow.
