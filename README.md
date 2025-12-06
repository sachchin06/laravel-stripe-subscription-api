# Laravel Stripe Subscription API

A clean, production-ready Laravel API for managing SaaS subscriptions with Stripe integration.

## Features

- 🔐 Token-based authentication (Laravel Sanctum)
- 💳 Stripe Checkout integration
- 🔄 Real-time webhook sync
- 📦 Multiple subscription plans
- 🎯 Clean architecture (Actions, DTOs, Events, Jobs)
- 📚 Swagger/OpenAPI documentation

## Tech Stack

- Laravel 12
- PHP 8.2+
- MySQL
- Stripe PHP SDK
- Laravel Sanctum

## Quick Start

### 1. Install Dependencies

```bash
composer install
npm install
```

### 2. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

Update `.env` with your credentials:
```env
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

### 3. Database Setup

```bash
php artisan migrate --seed
```

This creates:
- Database tables
- Sample plans (Basic, Pro)
- Test user (test@example.com / password)

### 4. Start Development

```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Queue worker (for webhooks)
php artisan queue:work --verbose

# Terminal 3: Stripe webhook listener
stripe listen --forward-to http://127.0.0.1:8000/api/webhooks/stripe
```

Copy the webhook secret from Terminal 3 and update `.env`:
```env
STRIPE_WEBHOOK_SECRET=whsec_xxxxx
```

Then run:
```bash
php artisan config:clear
```

## API Documentation

Interactive API docs available at:
```
http://127.0.0.1:8000/api/documentation
```

Generate docs:
```bash
php artisan l5-swagger:generate
```

## API Endpoints

### Authentication
```
POST   /api/auth/register    - Register new user
POST   /api/auth/login       - Login
POST   /api/auth/logout      - Logout
GET    /api/auth/user        - Get current user
```

### Subscriptions
```
GET    /api/plans                      - List available plans
GET    /api/subscriptions              - Get user subscriptions
POST   /api/subscriptions/checkout     - Create checkout session
POST   /api/subscriptions/cancel       - Cancel subscription
```

### Webhooks
```
POST   /api/webhooks/stripe   - Stripe webhook endpoint
```

## Common Commands

```bash
# Development
composer dev              # Start all services
php artisan serve        # Start server only
php artisan queue:work   # Start queue worker

# Testing
php artisan test

# Code Quality
./vendor/bin/pint        # Format code

# Database
php artisan migrate:fresh --seed   # Reset database
```

## License

MIT License
