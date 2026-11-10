# Laravel Stripe Subscription API

[![CI](https://github.com/sachchin06/laravel-stripe-subscription-api/actions/workflows/ci.yml/badge.svg)](https://github.com/sachchin06/laravel-stripe-subscription-api/actions/workflows/ci.yml)

A Laravel REST API for SaaS subscription billing with Stripe: Checkout, webhooks, plan management and plan-based feature gating.

## Features

- **Authentication** - Laravel Sanctum token auth, with rate-limited login and registration and expiring tokens
- **Subscription Management** - Subscribe through Stripe Checkout, cancel, resume and change plans (one active subscription per user)
- **Stripe Integration** - Checkout sessions, Billing Portal, and webhooks that are idempotent and safe against events arriving out of order
- **Product Management (admin)** - Create, update and archive products and prices, and sync them with Stripe in either direction
- **Billing** - Invoice history, upcoming invoice preview, usage tracking
- **Feature Gating** - Plan-based feature access and monthly usage limits, applied by middleware (see `GET /api/reports/usage`)
- **Tested** - 147 Pest tests with Stripe mocked, run in GitHub Actions together with a Pint style check

## Requirements

- PHP 8.2+ to run the app (8.3+ to run the tests)
- MySQL
- Composer
- Stripe CLI (for local webhook testing)
- Node.js & npm (optional: only the default welcome page uses Vite)

## First Time Setup

```bash
# 1. Install PHP dependencies
composer install

# 2. Copy environment file
copy .env.example .env          # Git Bash / macOS / Linux: cp .env.example .env

# 3. Generate application key
php artisan key:generate

# 4. Configure .env:
#    - Database: DB_DATABASE, DB_USERNAME, DB_PASSWORD
#    - Stripe test keys: STRIPE_KEY, STRIPE_SECRET
#    - STRIPE_WEBHOOK_SECRET comes from `stripe listen` (see below)

# 5. Run migrations with seed data (plans, plus a test user and an admin user)
php artisan migrate --seed

# 6. Create the seeded plans in YOUR Stripe account (prints the price IDs)
php artisan stripe:sync to

# 7. Generate Swagger documentation
php artisan l5-swagger:generate
```

### Seeded users

| Email | Password | Role |
|-------|----------|------|
| `test@example.com` | `password` | Normal user |
| `admin@example.com` | `password` | Admin (can use `/api/products`) |

### Optional settings

These have defaults, so you only need to set them to change the behaviour:

```env
FRONTEND_URL="${APP_URL}"            # where Stripe sends users back after Checkout / Billing Portal
STRIPE_API_VERSION=2025-10-29.clover  # the Stripe API version the code is written for
SANCTUM_TOKEN_EXPIRATION=10080        # API token lifetime in minutes (7 days)
```

By default the app serves simple success and cancel pages at `/subscription/success` and `/subscription/cancel`.

## Running the Application

You need **3 terminals** running at the same time:

| Terminal | Command | Purpose |
|----------|---------|---------|
| 1 | `php artisan serve` | API server at http://localhost:8000 |
| 2 | `php artisan queue:work` | Process webhook jobs |
| 3 | `stripe listen --latest --forward-to http://localhost:8000/api/webhooks/stripe` | Forward Stripe webhooks |

> **Important:** The queue worker MUST be running, or webhooks never create subscriptions in the database.

**The first time,** `stripe listen` prints a webhook secret (`whsec_...`). Put it in `.env` as `STRIPE_WEBHOOK_SECRET`, then restart terminals 1 and 2. The `--latest` flag sends events in the Stripe API version the code expects.

`composer dev` starts terminals 1 and 2 (plus Vite) with one command, but it does **not** start the Stripe CLI.

## Installing Stripe CLI

**Windows (Scoop):**
```powershell
scoop install stripe
```

**Windows (Chocolatey):**
```powershell
choco install stripe-cli
```

**Mac:**
```bash
brew install stripe/stripe-cli/stripe
```

After installation:
```bash
stripe login
```

## API Endpoints

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | Register new user (rate limited) |
| POST | `/api/auth/login` | Login user (rate limited) |
| POST | `/api/auth/logout` | Logout user (revokes the token) |
| GET | `/api/auth/user` | Get current user |

### Plans
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/plans` | List active subscription plans |

### Subscriptions
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/subscriptions` | Get user's subscription status |
| GET | `/api/subscriptions/details` | Get detailed Stripe subscription info |
| POST | `/api/subscriptions/checkout` | Create Stripe checkout session (409 if already subscribed) |
| POST | `/api/subscriptions/cancel` | Cancel subscription at period end |
| POST | `/api/subscriptions/resume` | Resume cancelled subscription |
| POST | `/api/subscriptions/change-plan` | Change subscription plan/price |

### Billing
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/billing/invoices` | Get invoice history (`limit` 1-100) |
| GET | `/api/billing/upcoming-invoice` | Get next invoice preview |
| POST | `/api/billing/portal` | Create Stripe billing portal session |
| GET | `/api/billing/usage` | Get feature usage summary |

### Reports (plan-gated)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/reports/usage` | Daily API usage for 30 days. Needs a plan with `advanced_analytics` (403 on the free tier, 429 over the monthly limit); each call counts towards `api_calls` |

### Products (Admin only)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/products` | List all products, including archived ones |
| POST | `/api/products` | Create product with prices (`interval`: `month` or `year`) |
| GET | `/api/products/{id}` | Get product details |
| PUT | `/api/products/{id}` | Update product |
| DELETE | `/api/products/{id}` | Archive product (kept as inactive if it has subscribers) |
| POST | `/api/products/{id}/prices` | Add price to product |
| POST | `/api/products/sync` | Sync products with Stripe (`direction`: `from` or `to`) |

### Webhooks
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/webhooks/stripe` | Stripe webhook handler (signature verified, duplicates skipped) |

## Example Usage

### Login
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'
```

### Subscribe to a Plan
```bash
# Get plans
curl http://localhost:8000/api/plans \
  -H "Authorization: Bearer YOUR_TOKEN"

# Create checkout session, then open checkout_url in your browser
curl -X POST http://localhost:8000/api/subscriptions/checkout \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"price_id":"price_xxxxx"}'
```

On the Stripe page, pay with the test card `4242 4242 4242 4242` (any future date, any CVC). The webhook then stores the subscription within a few seconds.

### Manage Subscription
```bash
# Check status
curl http://localhost:8000/api/subscriptions \
  -H "Authorization: Bearer YOUR_TOKEN"

# Cancel
curl -X POST http://localhost:8000/api/subscriptions/cancel \
  -H "Authorization: Bearer YOUR_TOKEN"

# Resume
curl -X POST http://localhost:8000/api/subscriptions/resume \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Swagger Documentation

Access interactive API docs at: `http://localhost:8000/api/documentation`

## Testing

```bash
vendor/bin/pest            # run the test suite
vendor/bin/pint --test     # check code style
```

- The tests use an in-memory SQLite database (the `pdo_sqlite` extension must be enabled) and need no `.env` file, database server or Stripe keys.
- `StripeService` is replaced with a mock in every test, so no request ever reaches Stripe.
- Webhook tests sign their payloads the same way Stripe does, so the real signature check runs.

GitHub Actions runs the style check and the tests on PHP 8.3 and 8.4 for every push and pull request (`.github/workflows/ci.yml`).

## Useful Commands

```bash
# Sync products from Stripe to local DB
php artisan stripe:sync from

# Create local products and prices that are missing in Stripe
php artisan stripe:sync to

# Test webhook configuration
php artisan webhook:test

# List webhook jobs that failed
php artisan queue:failed

# Run tests
composer test

# Format code
./vendor/bin/pint

# Clear all caches
php artisan optimize:clear

# Fresh database reset
php artisan migrate:fresh --seed
```

## Architecture

```
app/
├── Domains/
│   ├── Auth/           # Authentication
│   ├── Billing/        # Checkout, invoices, billing portal
│   ├── Subscription/   # Plans, subscriptions, feature gating, usage
│   ├── Webhooks/       # Webhook verification and queued jobs
│   └── Shared/         # StripeService (all Stripe API calls)
├── Enums/              # SubscriptionStatus, BillingInterval
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
└── Models/
tests/
├── Feature/            # HTTP, webhook, sync and migration tests
├── Unit/               # Models, usage limits, StripeService
└── Support/            # Fake Stripe HTTP client
```

## Troubleshooting

**New routes give 404, or old behaviour comes back?**
- Clear cached config and routes: `php artisan optimize:clear`

**Subscription not created after payment?**
- Make sure the queue worker is running: `php artisan queue:work`
- Check failed jobs: `php artisan queue:failed`

**Webhook signature invalid (400)?**
- Copy the webhook secret from the Stripe CLI output to `.env` as `STRIPE_WEBHOOK_SECRET`, then restart the server and queue worker

**Checkout says the price is invalid?**
- The local price has no Stripe ID yet. Run `php artisan stripe:sync to`

**Login returns 429?**
- Login is limited to 5 attempts a minute. Wait a minute.

**Stripe CLI not recognized?**
- Install Stripe CLI: https://stripe.com/docs/stripe-cli

## License

MIT
