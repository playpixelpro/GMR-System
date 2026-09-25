# NFA GMR System

## Requirements

- PHP 8.3+
- Composer
- Node.js and npm
- MySQL
- A verified Brevo sender and SMTP credentials

## Local setup

### 1. Install dependencies

```bash
composer install
npm install
```

### 2. Create the environment file

```bash
cp ".env copy.example" .env
php artisan key:generate
```

On Windows PowerShell, use:

```powershell
Copy-Item ".env copy.example" .env
php artisan key:generate
```

### 3. Configure the local database

Update the database values in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nfa_gmr
DB_USERNAME=your-local-database-user
DB_PASSWORD=your-local-database-password
```

Create the database before running migrations.

### 4. Configure Brevo SMTP

Use placeholders in local configuration and replace them with your Brevo values locally:

```env
MAIL_MAILER=brevo
MAIL_FROM_ADDRESS=verified-sender@example.com
MAIL_FROM_NAME="${APP_NAME}"

BREVO_SMTP_HOST=smtp-relay.brevo.com
BREVO_SMTP_PORT=587
BREVO_SMTP_SCHEME=tls
BREVO_SMTP_USERNAME=your-brevo-smtp-login
BREVO_SMTP_PASSWORD=your-brevo-smtp-key
BREVO_SMTP_TIMEOUT=
BREVO_SMTP_EHLO_DOMAIN=your-domain.example
```

The sender address or domain must be verified in Brevo. Never commit `.env` or SMTP credentials.

### 5. Run the database setup

```bash
php artisan migrate --seed --no-interaction
```

To recreate a local database from scratch:

```bash
php artisan migrate:fresh --seed --no-interaction
```

### 6. Build frontend assets and start the application

```bash
npm run build
php artisan serve
```

Open the URL shown by `php artisan serve`.

For active frontend development, use:

```bash
npm run dev
```

## Production setup

### 1. Install dependencies

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

### 2. Configure production environment

Create `.env` on the server. Do not copy credentials into the repository.

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-production-domain.example

DB_CONNECTION=mysql
DB_HOST=your-production-database-host
DB_PORT=3306
DB_DATABASE=your-production-database
DB_USERNAME=your-production-database-user
DB_PASSWORD=your-production-database-password

MAIL_MAILER=brevo
MAIL_FROM_ADDRESS=verified-sender@example.com
MAIL_FROM_NAME="${APP_NAME}"

BREVO_SMTP_HOST=smtp-relay.brevo.com
BREVO_SMTP_PORT=587
BREVO_SMTP_SCHEME=tls
BREVO_SMTP_USERNAME=your-brevo-smtp-login
BREVO_SMTP_PASSWORD=your-brevo-smtp-key
BREVO_SMTP_TIMEOUT=
BREVO_SMTP_EHLO_DOMAIN=your-domain.example
```

Use a verified sender address and a production domain for `BREVO_SMTP_EHLO_DOMAIN`.

### 3. Run migrations and optimize Laravel

```bash
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

If the administrator has not been seeded yet, set these values before running the seeder:

```env
NFA_ADMIN_EMAIL=administrator@example.com
NFA_ADMIN_PASSWORD=use-a-strong-temporary-password
```

Then run:

```bash
php artisan db:seed --force
```

### 4. After changing environment values

```bash
php artisan config:clear
php artisan cache:clear
php artisan optimize
```

### 5. Web server document root

Configure the web server document root to the project’s `public` directory. Do not expose the project root or `.env` file.
