# Mini Order Management API

A production-quality REST API for order management, built as a Lead Backend Engineer take-home assignment.

## 1. Project Overview
The Mini Order Management API provides a robust backend system for handling e-commerce orders. Built with Laravel 10 and PHP 8.2, it utilizes MySQL for persistent storage and Redis for caching. The project focuses on data integrity, concurrency safety, and scalable architecture.

## 2. Features
- **Authentication**: Secure registration, login, and logout using Laravel Sanctum.
- **Product Management**: Full CRUD operations for products (Admin only).
- **Product Search & Filtering**: Filter products by price, stock availability, and search by name.
- **Order Creation**: Place orders for multiple products with duplicate product validation.
- **Order Management**: Users can list their orders and view specific order details.
- **Stock Validation & Reduction**: Ensures sufficient stock before order placement and deducts stock automatically.
- **Server-Side Calculations**: Order totals are calculated securely on the server; client prices are ignored.
- **Concurrency Safety**: Utilizes database transactions and row-level pessimistic locking (`SELECT ... FOR UPDATE`) to prevent race conditions during checkout.
- **Redis Caching**: Tag-based caching for the product catalog with targeted invalidation on updates.
- **API Rate Limiting**: Protection against brute-force attacks and abuse.
- **Asynchronous Processing**: Queue-based background jobs dispatch only after a successful database commit.
- **Email Notifications**: Order confirmation emails are sent asynchronously.

## 3. Tech Stack
- **Framework**: Laravel 10
- **Language**: PHP 8.2
- **Database**: MySQL 8.0
- **Cache/Queue**: Redis 6.2
- **Mail**: Mailhog (Local testing)
- **Containerization**: Docker & Docker Compose

## 4. Project Setup

### Docker Environment (Recommended)
A `docker-compose.yml` is provided for a complete local environment.

1. Clone the repository:
   ```bash
   git clone <repo-url>
   cd mini-order-api
   ```
2. Copy environment file and install dependencies:
   ```bash
   cp .env.example .env
   docker compose run --rm app composer install
   ```
3. Generate application key:
   ```bash
   docker compose run --rm app php artisan key:generate
   ```
4. Start all services:
   ```bash
   docker compose up -d
   ```
5. Run migrations and seed the database:
   ```bash
   docker compose exec app php artisan migrate --seed
   ```
*Note: The queue worker is automatically started as a separate container.*

### Local Environment
If you prefer not to use Docker, ensure you have PHP 8.2, MySQL, and Redis installed.

1. Install dependencies: `composer install`
2. Configure `.env` with your local database and Redis credentials.
3. Run migrations and seeders: `php artisan migrate --seed`
4. Start the server: `php artisan serve`
5. Start the queue worker: `php artisan queue:work`

## 5. Environment Configuration
Key environment variables to configure in your `.env` file:

```env
# Database configuration
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=mini_order_api
DB_USERNAME=mini_order
DB_PASSWORD=secret

# Redis Cache configuration
CACHE_DRIVER=redis
REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PORT=6379

# Queue configuration
QUEUE_CONNECTION=database

# Mail configuration (MailHog)
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
```

## 6. API Endpoints

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| POST | `/api/v1/auth/register` | Public | Register a new user |
| POST | `/api/v1/auth/login` | Public | Login and receive Sanctum token |
| POST | `/api/v1/auth/logout` | Token | Revoke current token |
| GET | `/api/v1/auth/me` | Token | Get authenticated user profile |
| GET | `/api/v1/products` | Public | List products (with search/filter/pagination) |
| GET | `/api/v1/products/{id}` | Public | Get single product details |
| POST | `/api/v1/products` | Admin | Create a new product |
| PUT | `/api/v1/products/{id}` | Admin | Update a product |
| DELETE | `/api/v1/products/{id}` | Admin | Delete a product |
| POST | `/api/v1/orders` | Token | Place a new order |
| GET | `/api/v1/orders` | Token | List authenticated user's orders |
| GET | `/api/v1/orders/{id}` | Token | Get specific order details |

## 7. Order Processing Flow
1. **Request Validation**: Validates the payload structure and checks for duplicate `product_id`s.
2. **Database Transaction**: Opens a MySQL transaction.
3. **Row Locking**: Executes `SELECT ... FOR UPDATE` on requested products to acquire pessimistic locks, preventing concurrent modifications.
4. **Stock Check**: Verifies sufficient stock *after* acquiring the lock.
5. **Order Creation**: Calculates totals server-side and creates `orders` and `order_items` records. Prices are snapshot at the time of purchase.
6. **Stock Reduction**: Decrements available product stock.
7. **Commit**: Transaction commits if all steps succeed.
8. **Queue Dispatch**: Dispatches `ProcessOrderJob` explicitly `afterCommit()` to ensure data visibility.
9. **Email Notification**: The queue worker picks up the job and sends an order confirmation email via Mailhog.

## 8. Technical Decisions
- **Redis Caching**: Improves performance for the read-heavy product catalog. Uses tag-based caching to allow precise invalidation when products are created, updated, or deleted, avoiding stale data while maintaining high cache hit rates.
- **API Rate Limiting**: Protects against brute-force attacks on auth endpoints and provides API abuse/rate-limit protection on product/order endpoints.
- **Queue/Job Processing**: Decouples slow, non-critical tasks (like sending emails) from the main request lifecycle, ensuring fast API response times.
- **Database Transactions & Row Locking**: Essential for e-commerce to prevent overselling. Pessimistic locking ensures atomic stock decrement operations even under high concurrent load.

## 9. Database Design
- `users`: Stores user credentials and role (`is_admin`).
- `products`: Stores product catalog. Indexed on `price` and `stock`, with a full-text index on `name`.
- `orders`: Stores order metadata and total amount. Belongs to a `user`.
- `order_items`: Maps products to orders. Stores a snapshot of `unit_price` and `quantity`. Belongs to `orders` and `products`.

## 10. Validation & Error Handling
- **Form Requests**: Strict validation using Laravel Form Requests (e.g., `StoreOrderRequest`, `StoreProductRequest`).
- **Consistent Responses**: API Resources (`OrderResource`, `ProductResource`) ensure a consistent, versionable JSON structure and prevent accidental exposure of sensitive model attributes.
- **Custom Exceptions**: Domain-specific exceptions like `InsufficientStockException` render structured 422 JSON responses automatically.

## 11. Testing
A suite of 44 automated tests is included, testing happy paths, edge cases, validation, concurrency logic, and authorization.

To run the tests via Docker:
```bash
docker compose exec app php artisan test
```

## 12. API Documentation / Postman / Swagger
- **Swagger/OpenAPI**: Available at `http://localhost:8000/api/documentation` after starting the application.
- **Postman**: A Postman collection is included in the project root: `mini_order_api_postman_collection.json`.

## 13. Assumptions / Notes
- **Testing**: A separate testing database (`mini_order_api_test`) is required for running tests. The `.env.testing` configuration uses MySQL, array cache, and sync queue for reliable test execution.
- **Admin Users**: The database seeder creates a demo admin user (`admin@miniorderapi.com` / `password`). This is for development and testing purposes only.

## 14. Author
Tejpratap Yadav
