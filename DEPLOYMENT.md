# SGI Deployment Guide

## Required Environment Variables

Set these in your platform dashboard (Render, Railway, Heroku, Docker, cPanel, etc.).
**Never commit `.env` to version control.**

| Variable | Required | Description |
|---|---|---|
| `MONGODB_URI` | Yes* | Full Atlas connection string e.g. `mongodb+srv://user:pass@cluster.mongodb.net/` |
| `MONGODB_HOST` | Yes* | Hostname for self-hosted MongoDB (use instead of URI) |
| `MONGODB_PORT` | No | Port for self-hosted MongoDB, default `27017` |
| `MONGODB_DB` | No | Database name, default `sgi_db` |
| `CLOUDINARY_CLOUD_NAME` | No | Cloudinary cloud name — omit to use local `uploads/` folder |
| `CLOUDINARY_API_KEY` | No | Cloudinary API key |
| `CLOUDINARY_API_SECRET` | No | Cloudinary API secret |
| `RESEND_API_KEY` | Yes | Resend API key for OTP and contact emails |
| `RESEND_FROM_EMAIL` | Yes | Verified sender address in Resend |
| `MAIL_USERNAME` | Yes* | Existing destination address for student/mentor contact forms |
| `APP_ENV` | No | `production` or `development`, default `development` |
| `APP_DEBUG` | No | `true` or `false` |

*Use either `MONGODB_URI` **or** `MONGODB_HOST`/`MONGODB_PORT`/`MONGODB_DB` — not both.

---

## Local Development (XAMPP)

1. Copy `.env.example` to `.env` and fill in your values.
2. Run `composer install`.
3. Copy `cacert.pem` from your PHP installation into the project root if needed for Atlas SSL.
4. Start Apache and visit `http://localhost/SGI/`.

---

## Docker

```bash
docker build -t sgi-app .
docker run -d -p 8080:8080 \
  -e MONGODB_URI="mongodb+srv://..." \
  -e RESEND_API_KEY="re_xxx" \
  -e RESEND_FROM_EMAIL="noreply@yourdomain.com" \
  -e MAIL_USERNAME="support@yourdomain.com" \
  -e CLOUDINARY_CLOUD_NAME="your-cloud" \
  -e CLOUDINARY_API_KEY="your-key" \
  -e CLOUDINARY_API_SECRET="your-secret" \
  -e APP_ENV="production" \
  sgi-app
```

The build step runs `composer install` and verifies the MongoDB PHP extension loaded.
If the extension fails to load the build will **fail with an error** rather than silently deploying a broken image.

---

## Render / Railway / Heroku

1. Connect your GitHub repository.
2. Set all environment variables listed above in the platform dashboard.
3. The platform will run `composer install` automatically if a `composer.json` is present, **or** use the Dockerfile.
4. `config.php` and `.env` are **not** committed — the app reads credentials directly from the platform env vars.

---

## cPanel / Traditional Hosting

1. Upload all files via FTP/SFTP (excluding `.env` and `vendor/`).
2. SSH in and run:
   ```bash
   cd /path/to/project
   composer install --no-dev --optimize-autoloader
   ```
3. Create `.env` on the server with your production values (do not upload from local).
4. Ensure `mod_rewrite` is enabled — the `.htaccess` handles URL rewriting.
5. Set permissions:
   ```bash
   find . -type f -exec chmod 644 {} \;
   find . -type d -exec chmod 755 {} \;
   chmod 777 uploads/
   ```

---

## Nginx Configuration

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/SGI;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(htaccess|env|git) {
        deny all;
    }
}
```

---

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Blank page / 500 on every page | `config.php` missing or `vendor/` missing | Set env vars on platform; run `composer install` |
| `Class "MongoDB\Client" not found` | MongoDB PHP extension not loaded | Check `php -m \| grep mongodb`; reinstall PECL extension |
| `headers already sent` warnings | Old `ob_start()` in config.php | Fixed in current version — update your config.php |
| OTP or contact emails not sending | Wrong email configuration | Set `RESEND_API_KEY` and a verified `RESEND_FROM_EMAIL`; contact forms also require `MAIL_USERNAME` |
| MongoDB connection refused | Wrong env var form | Use `MONGODB_URI` for Atlas; `MONGODB_HOST/PORT/DB` for local |
| Photos not uploading | Cloudinary not configured | Set all three Cloudinary vars, or leave blank for local storage |
| 404 on all pages except index | `mod_rewrite` disabled | Enable `mod_rewrite` and ensure `AllowOverride All` in Apache config |
