# Scholarship Backend

Laravel REST API backend.

## Requirements

- PHP 8.2+
- Composer

## Setup

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

## API

- `GET /api/health` returns service status.
- `GET /api/user` returns the authenticated Sanctum user.
