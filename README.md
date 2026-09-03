# Mini Order API

A production-quality REST API for order management built with Laravel 10 and PHP 8.2.

## Architecture Decisions

- **Transactional Consistency**: Order creation is wrapped in a DB transaction with pessimistic locking (`lockForUpdate()`) to prevent stock race conditions.
- **Historical Pricing**: `order_items` stores a `unit_price` snapshot, guaranteeing historical integrity even if the product price changes later.
- **Asynchronous Processing**: Email notifications are dispatched to a queue only *after* the database transaction commits successfully.
- **Caching**: The product catalog uses tag-based Redis caching with targeted invalidation on write.
- **API Resources**: Strict API resources ensure sensitive data (e.g., passwords, tokens) is never accidentally exposed.
- **Rate Limiting**: Native Laravel rate limiters protect auth (IP-based brute-force prevention), products (IP/User-based throttling), and order endpoints.

## Requirements

- PHP 8.2+
- Composer
- MySQL 8.0+
- Redis (Optional, default is array cache for local if not set)

## Setup Instructions (Local)

1. Clone the repository:
   ```bash
   git clone <repo-url>
   cd mini-order-api
   ```
2. Install dependencies:
   ```bash
   composer install
   ```
3. Copy environment file and generate key:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. Configure your `.env` (Database, Redis, Mail).
5. Run migrations and seeders:
   ```bash
   php artisan migrate --seed
   ```
6. Start the local server and queue worker:
   ```bash
   php artisan serve
   php artisan queue:work
   ```

## Setup Instructions (Docker)

A `docker-compose.yml` is provided for a complete local environment including MySQL, Redis, and Mailhog.

1. Start all services:
   ```bash
   docker-compose up -d
   ```
2. Run migrations and seed the database inside the app container:
   ```bash
   docker-compose exec app php artisan migrate --seed
   ```

## API Documentation

The API uses Swagger (OpenAPI 3.0) for documentation.

1. Generate the docs:
   ```bash
   php artisan l5-swagger:generate
   ```
2. View the docs in your browser at:
   `http://localhost:8000/api/documentation`

A Postman collection is also provided in the repository root (`mini_order_api_postman_collection.json`).

## Environment Variables

Key variables to configure in `.env`:

```env
# Database configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mini_order_api
DB_USERNAME=root
DB_PASSWORD=secret

# Redis Cache configuration
CACHE_DRIVER=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1

# Queue driver (database recommended for local)
QUEUE_CONNECTION=database

# Rate limits (requests per minute)
AUTH_RATE_LIMIT=10
PRODUCT_RATE_LIMIT=60
ORDER_RATE_LIMIT=20
```

## Running Tests

The project includes a comprehensive PHPUnit test suite.

```bash
# Uses .env.testing configuration
php artisan test --env=testing
```
