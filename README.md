# Laravel Stripe Subscription API

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.2-blue.svg)](https://www.php.net/)
[![Laravel Version](https://img.shields.io/badge/Laravel-10-red.svg)](https://laravel.com/)
[![Stripe](https://img.shields.io/badge/Stripe-Payment-orange.svg)](https://stripe.com/)

**A fully API-based subscription management system built with Laravel and Stripe.**
This project demonstrates best practices for building scalable and secure subscription backends for SaaS apps, SPAs, or mobile apps.

---

## Table of Contents

* [Features](#features)
* [Tech Stack](#tech-stack)
* [Setup Instructions](#setup-instructions)
* [API Endpoints](#api-endpoints)
* [Future Enhancements](#future-enhancements)
* [License](#license)

---

## Features

* **Dynamic Plans Management**: Store Basic, Pro, or custom plans in database
* **Stripe Checkout Integration**: Secure, PCI-compliant, recurring subscription support
* **Webhook Handling**: Sync subscription status between Stripe and database
* **API-Only Architecture**: Ready for frontend frameworks or mobile apps
* **Subscription Management**: Check, cancel, or manage subscriptions via API
* **Security Best Practices**: Webhook signature verification, env-based keys

---

## Tech Stack

* **Backend:** Laravel 12 (API only)
* **Payment Gateway:** Stripe (Checkout & Subscriptions)
* **Database:** MySQL
* **Authentication:** Laravel Sanctum (API token-based auth)
* **Testing:** Postman 

---

## Setup Instructions

1. Clone the repository:

```bash
git clone git@github.com:sachchin06/laravel-stripe-subscription-api.git
cd laravel-stripe-subscription-api
```

2. Install dependencies:

```bash
composer install
```

3. Copy `.env.example` to `.env` and configure Stripe and app URLs:

```env
APP_URL=http://127.0.0.1:8000
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
```

4. Run migrations and seed plans:

```bash
php artisan migrate --seed
```

5. Start local server:

```bash
php artisan serve
```

6. Test Stripe webhooks locally using Stripe CLI:

```bash
stripe listen --forward-to http://127.0.0.1:8000/api/stripe/webhook
```

7. Use Postman or your frontend app to interact with the API.

---

## API Endpoints

| Method | Endpoint                   | Description                     |
| ------ | -------------------------- | ------------------------------- |
| GET    | `/api/plans`               | List all subscription plans     |
| POST   | `/api/subscribe`           | Create Stripe Checkout session  |
| GET    | `/api/subscription`        | Get current user's subscription |
| POST   | `/api/cancel-subscription` | Cancel subscription             |
| POST   | `/api/stripe/webhook`      | Handle Stripe webhook events    |

---

## Future Enhancements

* Upgrade / downgrade subscription plans
* Trial periods and coupon handling
* Automatic invoicing and email notifications
* Admin dashboard for managing plans and subscriptions

---

## License

MIT License © [Sachchin]
