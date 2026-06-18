# SGI Deployment Guide

## 📋 Pre-Deployment Checklist

### 1. Security Files (DO NOT COMMIT)
These files contain sensitive credentials and are already in `.gitignore`:
- `.env` - Environment variables with passwords
- `config.php` - Database configuration
- `vendor/` - Composer dependencies (will be installed on server)

### 2. Files Ready to Commit
All other files are safe to push to GitHub:
- ✅ PHP application files
- ✅ HTML/CSS/JavaScript files
- ✅ `.gitignore`
- ✅ `composer.json`
- ✅ `.env.example` (template for others)
- ✅ `config.php.example` (template for others)
- ✅ `README.md`
- ✅ Documentation files

---

## 🚀 Deployment Steps

### Step 1: Push to GitHub

```bash
# Navigate to your project directory
cd C:\xampp\htdocs\SGI

# Add all files (only non-sensitive files will be added)
git add .

# Commit changes
git commit -m "Prepare for production deployment"

# Push to GitHub
git push origin main
```

### Step 2: Deploy to Production Server

#### Option A: Traditional Hosting (cPanel, VPS, etc.)

1. **Upload Files:**
   - Use FTP/SFTP or Git to upload all files to your server
   - Upload to: `/public_html` or your web root

2. **Install Dependencies:**
   ```bash
   cd /path/to/your/project
   composer install --no-dev --optimize-autoloader
   ```

3. **Set Up Environment:**
   - Copy `.env.example` to `.env`
   - Edit `.env` with your production credentials:
     ```
     SMTP_HOST=smtp.gmail.com
     MAIL_USERNAME=your-production-email@gmail.com
     MAIL_PASSWORD=your-production-app-password
     RESEND_API_KEY=your-resend-api-key
     APP_ENV=production
     APP_DEBUG=false
     ```

4. **Set Up Database:**
   - Install MongoDB on your server
   - Create database named `sgi`
   - Copy `config.php.example` to `config.php`
   - Update with your MongoDB connection details

5. **Set Permissions:**
   ```bash
   chmod 755 /path/to/project
   chmod 755 /path/to/project/uploads
   chmod 644 /path/to/project/.env
   chmod 644 /path/to/project/config.php
   ```

6. **Configure Web Server:**

   **Apache (.htaccess already included):**
   - Ensure `mod_rewrite` is enabled
   - The `.htaccess` file will handle URL rewriting

   **Nginx Configuration:**
   ```nginx
   server {
       listen 80;
       server_name your-domain.com;
       root /path/to/project;
       index index.php;

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php-fpm.sock;
           fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
           include fastcgi_params;
       }

       location ~ /\.(htaccess|env|git) {
           deny all;
       }
   }
   ```

#### Option B: Docker Deployment

1. **Build and Run:**
   ```bash
   docker build -t sgi-app .
   docker run -d -p 80:80 sgi-app
   ```

2. **Environment Variables:**
   - Set environment variables in Docker Compose or Docker run command

#### Option C: Cloud Platform (Heroku, DigitalOcean, AWS, etc.)

1. **Connect GitHub Repository:**
   - Link your GitHub repository to the platform
   - Set up automatic deployments

2. **Configure Environment Variables:**
   - Add all required environment variables in the platform's settings:
     - `SMTP_HOST`
     - `MAIL_USERNAME`
     - `MAIL_PASSWORD`
     - `RESEND_API_KEY`
     - `MONGODB_HOST`
     - `MONGODB_PORT`
     - `MONGODB_DB`
     - `APP_ENV`
     - `APP_DEBUG`

3. **Set Up MongoDB:**
   - Use MongoDB Atlas (cloud) or install MongoDB on your server

---

## 🔧 Required Environment Variables

### Email Configuration (PHPMailer)
```
SMTP_HOST=smtp.gmail.com
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-gmail-app-password
```

### Contact Form (Resend API)
```
RESEND_API_KEY=re_xxxxxxxxxxxxxxxxxxxxx
```

### Database (MongoDB)
```
MONGODB_HOST=localhost
MONGODB_PORT=27017
MONGODB_DB=sgi
```

### Cloudinary (Optional - for file uploads)
```
CLOUDINARY_CLOUD_NAME=your-cloud-name
CLOUDINARY_API_KEY=your-api-key
CLOUDINARY_API_SECRET=your-api-secret
```

### Application Settings
```
APP_ENV=production
APP_DEBUG=false
```

---

## 📝 Post-Deployment Tasks

### 1. Test Email Functionality
- Visit `your-domain.com/test_email.php` (delete after testing)
- Verify OTP emails are sent successfully

### 2. Test User Registration
- Register a test student account
- Verify email confirmations work

### 3. Test Forgot Password
- Use the forgot password feature
- Verify OTP is received and password reset works

### 4. Test Contact Form
- Submit a contact form
- Verify email is received by admin

### 5. Security Hardening
- **Delete `test_email.php`** after testing
- Ensure `.env` and `config.php` are not accessible via web
- Set up SSL certificate (HTTPS)
- Enable firewall rules
- Set up regular backups

---

## 🛡️ Security Recommendations

### 1. HTTPS/SSL
- Obtain SSL certificate (Let's Encrypt is free)
- Force HTTPS in `.htaccess`:
  ```apache
  RewriteEngine On
  RewriteCond %{HTTPS} off
  RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
  ```

### 2. Database Security
- Use strong passwords for MongoDB
- Restrict MongoDB access to localhost only
- Enable MongoDB authentication

### 3. File Permissions
- Set proper file permissions (755 for directories, 644 for files)
- Make `uploads/` directory writable but not executable

### 4. Regular Updates
- Keep PHP and MongoDB updated
- Update Composer dependencies regularly
- Monitor for security vulnerabilities

### 5. Backups
- Set up automated database backups
- Backup uploaded files regularly
- Test backup restoration process

---

## 🆘 Troubleshooting

### Email Not Sending
1. Check `.env` file has correct credentials
2. Verify Gmail App Password (not regular password)
3. Check server can connect to SMTP (port 587 open)
4. Review error logs

### Database Connection Failed
1. Verify MongoDB is running
2. Check connection string in `config.php`
3. Ensure database `sgi` exists
4. Check MongoDB authentication credentials

### Permission Denied Errors
```bash
# Fix permissions
find /path/to/project -type f -exec chmod 644 {} \;
find /path/to/project -type d -exec chmod 755 {} \;
chmod 777 /path/to/project/uploads
```

### 500 Internal Server Error
1. Check PHP error logs
2. Verify PHP extensions are installed (MongoDB, curl, etc.)
3. Check `.htaccess` syntax
4. Ensure `config.php` exists and is correct

---

## 📞 Support

If you encounter issues during deployment:
1. Check application logs
2. Review this deployment guide
3. Verify all environment variables are set
4. Test each component individually

---

## ✅ Deployment Complete!

Once all steps are completed and tests pass, your SGI application is live and ready for users!

**Important:** Always keep your `.env` file secure and never commit it to version control.