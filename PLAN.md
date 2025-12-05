# Project Plan: AWS E-commerce Microservices (Laravel 12 + Vue.js)

## 1. Goals and Scope

- **Goal**: Build a production-ready e-commerce system with 3 backend microservices and a Vue.js public frontend, deployed on AWS using CloudFormation, with Laravel 12 and Filament 4, while keeping all cloud resources within AWS Free Tier limits for this test assignment.
- **Microservices**:
  - **Catalog**: Product catalog, categories, stock, public product APIs, admin product management.
  - **Checkout**: Shopping cart, orders, pricing, checkout flow, payment integration hook.
  - **Email**: Asynchronous email processing, order confirmation emails.
- **Data**:
  - Product catalog and order data stored in **MySQL** (database-per-service via separate schemas on one MySQL instance, containerized on EC2 by default; optional RDS Free Tier in a real production setup).
- **Infrastructure** (Free Tier-friendly by default):
  - **Docker** for local development and for running all services in containers on a single EC2 instance.
  - **AWS EC2**: one small instance (for example, `t2.micro` or `t3.micro`) hosting all containers (Laravel services, Vue frontend via Nginx, MySQL, Redis) within Free Tier hours.
  - **MySQL container**, **SQS** (order events), **SES** (email delivery) used in a way that fits in the AWS Free Tier quotas.
  - Provisioned with **AWS CloudFormation** stacks focused on a single EC2 instance and minimal supporting resources.


## 2. High-Level Architecture

- **Frontend**:
  - **Vue.js SPA** in a `frontend` directory.
  - Public pages: home, product listing, product detail, cart, checkout, order confirmation, user account (optional v2).
  - Communicates with backend via REST JSON APIs.

- **Backend microservices** (each is a separate Laravel 12 app):
  - `services/catalog`
  - `services/checkout`
  - `services/email`

- **Admin interface**:
  - **Filament 4** integrated into the relevant Laravel apps (primarily Catalog and Checkout).
  - Accessible on separate subpaths (for example, `/admin` per service) with proper authentication and authorization.

- **Service-to-service communication**:
  - **Synchronous**: Vue frontend calls Catalog and Checkout services directly via HTTP.
  - **Asynchronous**: Checkout publishes `OrderCreated` events to **SQS**; Email service consumes from SQS and sends emails via SES.

- **Databases**:
  - **MySQL** running as a Docker container on the EC2 instance, with at least 3 logical schemas:
    - `catalog_db` for Catalog service.
    - `checkout_db` for Checkout service (orders, payments, carts).
    - `email_db` for Email service (email logs, failures).
  - Each service only accesses its own schema (database-per-service pattern) via separate DB users.

- **Deployment topology (production / test environment)**:
  - A **single EC2 instance** (Free Tier-eligible) running Docker, hosting all microservices, MySQL and the Vue.js frontend.
  - **Nginx** as a reverse proxy on that EC2 instance to route requests:
    - `/api/catalog/*` to the Catalog container.
    - `/api/checkout/*` to the Checkout container.
    - `/api/email/*` (if needed) to the Email container.
    - `/` and SPA routes to the built Vue.js frontend.
  - No ALB, NAT Gateway or CloudFront required by default, to avoid extra costs. **S3 + CloudFront** can be added later as an optional, non-free-tier enhancement for a real production environment.


## 3. Repository and Directory Structure

Single public GitHub repository (for example, `aws-ecommerce-microservices`).

- **Root**
  - `PLAN.md` (this file)
  - `README.md`
  - `docker-compose.yml` (local dev)
  - `infra/`
    - `cloudformation/`
      - `networking.yml` (VPC, subnets, security groups)
      - `database.yml` (RDS MySQL)
      - `ecs.yml` (ECS cluster, services, task definitions, ALB, SQS, SES config)
      - `iam.yml` (IAM roles and policies)
  - `services/`
    - `catalog/` (Laravel 12 app with Filament 4 admin for products)
    - `checkout/` (Laravel 12 app with Filament 4 admin for orders)
    - `email/` (Laravel 12 app for consuming SQS and sending emails)
  - `frontend/` (Vue.js SPA)
  - `.github/workflows/` (CI CD pipelines)

- **Branching strategy**
  - `main`: stable, deployable branch.
  - `develop`: integration branch.
  - `feature/*`: feature branches merged into `develop` via pull requests.


## 4. Service Responsibilities and API Design

### 4.1 Catalog Service (Laravel 12)

- **Responsibilities**
  - Manage products, categories, inventory, basic pricing data.
  - Provide public APIs for product listing and detail.
  - Provide admin APIs and UI (Filament) for catalog management.

- **Core entities**
  - `Product`: id, sku, name, slug, description, price, status, created at, updated at.
  - `Category`: id, name, slug.
  - `ProductCategory`: pivot between products and categories.
  - `ProductImage`: product id, image url, sort order, alt text.
  - `Inventory`: product id, quantity available, reserved quantity.

- **Key APIs (example paths)**
  - `GET /api/catalog/products`
    - Query parameters: pagination, category, search term, min max price, sort.
  - `GET /api/catalog/products/{slug}`
    - Returns product details, images, availability.
  - `GET /api/catalog/categories`

- **Admin (Filament 4)**
  - Filament resources for:
    - Products (CRUD, price, inventory, images).
    - Categories (CRUD).
    - Basic reports (top products by sales, low stock) via read-only panels.

### 4.2 Checkout Service (Laravel 12)

- **Responsibilities**
  - Manage shopping cart and checkout process.
  - Create orders, calculate totals, taxes and shipping (simplified at first).
  - Integrate with payment provider (initially stub with test provider or manual status).
  - Publish `OrderCreated` events to SQS for the Email service (simulated via Laravel's queue system in the local Docker environment using the `sync` driver, with SQS wired in the AWS environment).

- **Core entities**
  - `Cart`: id, user id (nullable for guest), session token, status.
  - `CartItem`: cart id, product id (from Catalog), quantity, unit price snapshot.
  - `Order`: id, order number, user id, email, status, subtotal, tax, shipping, total, created at.
  - `OrderItem`: order id, product id, product name snapshot, unit price snapshot, quantity, line total.
  - `Payment`: order id, provider, provider reference, status, amount.
  - `Shipment` (optional v2): carrier, tracking number, status.

- **Key APIs**
  - **Cart**
    - `POST /api/checkout/cart` to create a cart (or reuse session cart).
    - `POST /api/checkout/cart/items` to add item.
    - `PUT /api/checkout/cart/items/{id}` to update quantity.
    - `DELETE /api/checkout/cart/items/{id}` to remove item.
    - `GET /api/checkout/cart` to retrieve current cart (by session token or user).
  - **Checkout**
    - `POST /api/checkout/place-order`
      - Request: cart id, customer info (name, email, address), payment token (or placeholder), shipping method.
      - Steps:
        - Validate cart against Catalog (prices, availability) via internal HTTP call or cache of product data.
        - Reserve stock through Catalog service (synchronous call or event).
        - Create order and order items.
        - Handle payment (mock or provider integration).
        - Change order status to `paid` or `pending`.
        - Publish `OrderCreated` message to SQS with full order summary payload.
    - `GET /api/checkout/orders/{orderNumber}` for order summary (for authenticated users or via secure token in confirmation page).

- **Admin (Filament 4)**
  - Resources for:
    - Orders (list, filter by status, view details, set status, refund stub).
    - Payments.
    - Basic sales reports.

### 4.3 Email Service (Laravel 12)

- **Responsibilities**
  - Consume SQS `OrderCreated` messages.
  - Generate HTML and text emails using Blade templates.
  - Send emails via SES.
  - Log email send results (success and errors).

- **Data model**
  - `EmailLog`: id, type, recipient email, subject, payload snippet, status, error message, sent at.

- **Flow**
  - SQS queue `order-events` receives messages from Checkout.
  - Email service worker reads messages, parses order data, renders templates, sends email through SES.
  - On success or failure, log to `email_db.email_logs`.


### 4.4 Advanced SQL & MySQL Performance

- **Catalog service**
  - Design composite and covering indexes for common queries, for example on `(category_id, status, price)` and `slug`.
  - Use MySQL `FULLTEXT` indexes on `name` and `description` for basic relevance-ranked search via `MATCH() AGAINST()`.
  - Use `EXPLAIN` to tune product listing, detail and related-products queries, avoiding full table scans.

- **Checkout service**
  - Add indexes on `orders(user_id, created_at)` and `order_items(product_id, created_at)` to support order history and reporting.
  - Implement efficient aggregate queries for admin reports (top-selling products, sales by day) and consider summary tables if needed.

- **General tuning**
  - Monitor slow queries in development, iteratively refine indexes and queries, and keep transactions small and well-scoped around inventory and order creation.


### 4.5 Optional: Elasticsearch/OpenSearch Search

- **Purpose**
  - Provide a more advanced search experience (analyzers, relevance tuning, autocomplete) for the product catalog beyond basic SQL full-text.

- **Architecture (optional enhancement)**
  - Run **Elasticsearch or OpenSearch as a Docker container** on the same Free Tier EC2 instance (no managed OpenSearch service required).
  - Catalog service publishes product documents to the search index on create, update and delete operations.

- **Capabilities to demonstrate**
  - Define index mappings with appropriate analyzers (for example, standard or ICU analyzer, optional edge n-gram analyzer for autocomplete).
  - Implement a `/api/catalog/search` endpoint backed by ES/OpenSearch using the Query DSL (`bool`, `multi_match`, filters for category and price range).
  - Tune relevance by boosting matches on product name over description and optionally incorporating popularity signals (such as sales count) in the scoring.


## 5. Vue.js Frontend Plan (Public Pages)

- **Stack**
  - Vue 3, Vite, TypeScript (optional but recommended), Pinia for state management, Vue Router, Axios for HTTP.

- **Pages**
  - `HomePage`: featured products, categories.
  - `ProductListPage`: list with filters, sorting, pagination.
  - `ProductDetailPage`: product details, images, add to cart.
  - `CartPage`: show items, quantities, totals, update remove.
  - `CheckoutPage`: customer details, shipping address, payment info (simplified form), place order.
  - `OrderConfirmationPage`: show order summary and status after successful checkout.

- **State management**
  - `catalogStore` (Pinia): products, filters, categories.
  - `cartStore` (Pinia): cart id token, items, totals, sync with Checkout service APIs.
  - `userStore` (optional): authentication state if login is implemented.

- **Integration with backend**
  - Base API URL from environment variables.
  - Use Axios interceptors for error handling, auth headers when needed.

- **Deployment**
  - Build static assets with Vite.
  - **Default (Free Tier-friendly)**: serve built SPA via Nginx on the single EC2 instance, alongside the Laravel services.
  - **Optional production-style**: deploy to **S3 + CloudFront** (still within Free Tier limits for low traffic) if you want to demonstrate a CDN-based architecture, but this is not required for the test assignment.


## 6. Shopping Cart and Checkout Best Practices

- **Cart behavior**
  - Guest carts identified by secure cart token stored in cookie or local storage.
  - Authenticated carts attached to user id and merged from guest cart on login.
  - Cart item prices are **snapshotted** at the time of adding to cart and revalidated at checkout.

- **Inventory**
  - On order placement:
    - Validate available stock via Catalog service.
    - Reserve or decrement inventory in a single transactional operation, or use a reserved quantity field to avoid overselling.

- **Promotions and discounts (optional v2)**
  - Coupon codes entity and validation in Checkout service.
  - Price rules (percentage, fixed amount, free shipping).

- **Error handling**
  - Clear error messages to frontend for out-of-stock, payment failures, or validation errors.
  - Idempotent `place-order` endpoint using idempotency keys or cart status flags to avoid duplicate orders.


## 7. Infrastructure Plan (AWS + CloudFormation)

### 7.1 Networking stack (`infra/cloudformation/networking.yml`)

- Use the **default VPC** or a minimal VPC with a single public subnet (no NAT Gateways to avoid extra cost).
- Internet Gateway attached to the VPC for outbound internet access.
- Security group(s):
  - One security group for the EC2 instance allowing:
    - HTTP (80) and optionally HTTPS (443) from the internet.
    - SSH (22) only from your own IP for admin access.

### 7.2 Compute and database stack (`infra/cloudformation/compute-db.yml`)

- A single Free Tier-eligible **EC2 instance** (for example, `t2.micro` or `t3.micro`) with an IAM role allowing:
  - Access to SQS.
  - Access to SES (send email).
  - Access to SSM Parameter Store for configuration.
- User data or post-provisioning instructions to:
  - Install Docker and Docker Compose.
  - Clone the GitHub repository.
  - Start the Docker Compose stack which includes:
    - Three Laravel services (Catalog, Checkout, Email).
    - A MySQL container with the three logical schemas.
    - Nginx serving both the APIs and the built Vue SPA.
    - Optional Redis and Mailhog for development.
- Database schemas created via Laravel migrations from each service.

### 7.3 Application and messaging stack (`infra/cloudformation/app.yml`)

- SQS queue `order-events` for order event messages (Email service consumer).
- Basic SES configuration assumptions:
  - A verified sender email or domain in SES (manual console step, outside the template scope for the test).
  - SES in sandbox mode is sufficient for sending to verified email addresses during testing.
- IAM policies attached to the EC2 instance role granting least privilege to SQS, SES and SSM Parameter Store.

### 7.4 Configuration and secrets

- Use **SSM Parameter Store** (standard parameters, which have an always-free tier) for database credentials, app keys, mail config and queue URLs.
- Optionally use **Secrets Manager** for more advanced secret rotation in a real project (not required for this test).


## 8. Local Development Environment (Docker)

- `docker-compose.yml` services:
  - `catalog-app`: Laravel Catalog, running PHP-FPM, with Nginx as `catalog-web`.
  - `checkout-app`: Laravel Checkout, PHP-FPM, with Nginx as `checkout-web`.
  - `email-app`: Laravel Email, PHP-FPM, with `email-worker` (queue worker) and optional web.
  - `mysql`: shared MySQL with multiple databases.
  - `redis`: cache and queue backend (for async jobs locally).
  - `mailhog`: capture outgoing emails during development.

- Developer workflow:
  - `composer create-project` for each Laravel 12 service.
  - `npm install` and `npm run dev` for frontend (Vite dev server) locally.
  - `php artisan migrate` run per service, pointing to local MySQL.
  - `.env` files templated via `.env.example` per service.


## 9. Security, Authentication and Authorization

- **Public API**
  - Catalog endpoints are mostly read-only and public.

- **Checkout and user accounts**
  - If user accounts are implemented:
    - Use **Laravel Sanctum** for SPA authentication.
    - Protect order history endpoints behind auth middleware.

- **Admin (Filament 4)**
  - Use Laravel authentication plus roles permissions (for example, spatie permission package).
  - Restrict Filament panels to admin roles.
  - Provide a default Filament super admin account per service (Catalog and Checkout) created via a dedicated database seeder, with credentials sourced from environment variables or configuration (no hard-coded passwords).
  - Force HTTPS in production, secure cookies and CSRF protection.

- **Infrastructure security**
  - Minimum open ports on security groups.
  - For this test environment: HTTP only is acceptable, or use a free Let's Encrypt certificate on Nginx running on the EC2 instance for HTTPS.
  - Ensure the EC2 EBS volume is encrypted (default), and keep SQS and SSM Parameter Store encrypted with AWS-managed keys. In a real production deployment, RDS and Secrets Manager with encryption can be added as an enhancement.


## 10. Observability and Operations

- **Logging**
  - Laravel logs written to disk inside containers on the EC2 instance; optionally use the CloudWatch Logs agent on EC2 to ship logs, staying within Free Tier limits.

- **Metrics and monitoring**
  - Basic CloudWatch alarms on EC2 instance CPU, disk and network.
  - Optional alarms on SQS metrics (age of oldest message, number of messages visible) to detect issues with the Email worker.

- **Tracing (optional v2)**
  - Use X-Ray or OpenTelemetry instrumentation for tracing between services.


## 11. Testing Strategy

- **Backend services**
  - Unit tests for domain logic (pricing, inventory checks, order creation).
  - Feature tests for key flows: add to cart, place order, email dispatch.
  - Contract tests for inter-service API contracts between Catalog and Checkout.

- **Frontend**
  - Component tests (for example, Vitest) for key components (cart, product list).
  - End-to-end tests (for example, Playwright or Cypress) for checkout flow.

- **CI pipeline (GitHub Actions)**
  - On pull request:
    - Run PHP CS fixer or Laravel Pint.
    - Run PHP unit tests.
    - Run frontend tests and lints.
  - On merge to `main`:
    - Build Docker images for all services and frontend.
    - Optionally push images to ECR (within Free Tier storage limits) or build images directly on the EC2 instance.
    - Deploy or update CloudFormation stacks for the single EC2 instance, SQS and IAM roles.


## 12. Implementation Phases

1. **[COMPLETED] Bootstrap repository and install base stack (Laravel 12 + Filament 4 + Vue 3)**
   - Initialize the Git repository and push it to GitHub as a public repo.
   - Add base `README.md` and this `PLAN.md` to document the design.
   - Create the directory structure: `services/catalog`, `services/checkout`, `services/email`, `frontend`, `infra/cloudformation`.
   - In each service folder (`services/catalog`, `services/checkout`, `services/email`), run `composer create-project` to install a fresh Laravel 12 application.
   - Install Filament 4 in Catalog and Checkout services, and create a Filament admin user seeder per service (credentials to be provided via environment variables later).
   - Scaffold a Vue 3 + Vite frontend (optionally with TypeScript and Pinia) in the `frontend` directory.

2. **[COMPLETED] Local environment and Docker**
   - Write Dockerfiles for the Laravel services (PHP-FPM) and an Nginx container to serve the APIs and Vue build.
   - Define a `docker-compose.yml` including: Catalog, Checkout, Email, Nginx, MySQL, Redis and Mailhog.
   - Create `.env` files for each Laravel service pointing to the Dockerized MySQL and Redis instances.
   - Run `php artisan key:generate` and initial migrations for each service, verifying that they can connect to MySQL.
   - Confirm that all containers start successfully and that the basic health endpoints for each service are reachable from the host.

3. **Catalog service implementation**
   - [COMPLETED] Define migrations, models and relationships for Product, Category, Inventory, ProductImage, with appropriate indexes and FULLTEXT indexes as described in section 4.4.
   - [COMPLETED] Seed some sample products and categories for local testing.
   - [COMPLETED] Implement REST APIs for product listing, product detail and category listing, including filtering, sorting and pagination.
   - [COMPLETED] Add a basic search endpoint using MySQL FULLTEXT and relevance ordering.
   - [COMPLETED] Integrate Filament 4 admin resources for managing products, categories, inventory and images.
   - [COMPLETED] Add unit and feature tests for key Catalog flows (list products, view detail, basic search).

4. **Checkout service implementation**
   - [COMPLETED] Define migrations and models for Cart, CartItem, Order, OrderItem and Payment, with the indexes needed for reporting and order history (section 4.4).
   - [COMPLETED] Implement cart APIs (create cart, add item, update item, remove item, get cart) using token-based guest carts.
   - [COMPLETED] Integrate with the Catalog service to validate prices at checkout time using SKU-based product lookups and snapshots.
   - [COMPLETED] Implement the `place-order` endpoint: create order, snapshot product data, handle a mock payment provider and set order status.
   - [COMPLETED] Publish `OrderCreated` messages to the SQS queue (logical `order-events` queue) with a summarized order payload.
   - [COMPLETED] Integrate Filament 4 for orders and payments (list, filter, view details, basic delete actions) with host-based Livewire routing per service.
   - [COMPLETED] Add automated tests for cart behavior, order creation and SQS event publishing:
     - [COMPLETED] Feature tests for `POST /checkout/api/place-order` covering the happy path, price-change validation and out-of-stock scenarios (see `CheckoutPlaceOrderTest`).
     - [COMPLETED] Feature tests for cart lifecycle endpoints (`POST/GET /checkout/api/cart`, `POST/PUT/DELETE /checkout/api/cart/items`) including validation errors and edge cases (empty cart, invalid quantity, missing product), implemented in `CartApiTest`.
     - [COMPLETED] End-to-end style assertion that a successful order creation dispatches the `SendOrderCreatedMessage` job with the expected queue and payload structure, implemented by strengthening the happy-path assertions in `CheckoutPlaceOrderTest`.
     - [COMPLETED] Feature tests for `GET /checkout/api/orders/{orderNumber}` to verify the public order summary contract (status, totals, items, payment snapshot), implemented in `OrderSummaryTest`.

5. **Email service implementation**
   - [COMPLETED] Introduce an `EmailLog` model and `email_logs` table to record email sending attempts and results (type, recipient, order number, payload snapshot, status, error message, sent at).
   - [COMPLETED] Implement an order confirmation Mailable (`OrderConfirmationMail`) and Blade view (`emails.order-confirmation`) for HTML order confirmation emails.
   - [COMPLETED] Implement a `ProcessOrderCreated` queue job that consumes `OrderCreated` payloads (from the logical `order-events` queue), sends order confirmation emails and writes `EmailLog` entries.
   - [COMPLETED] Add feature tests to verify that processing an `OrderCreated` payload results in a sent email and a corresponding `EmailLog` record (see `ProcessOrderCreatedTest` in the Email service).
   - [COMPLETED] Document how to wire the Email service to use the real SQS `order-events` queue and SES mailer configuration in the AWS environment (local Docker remains Mailhog + Redis queues), including environment examples in `DEPLOYMENT.md`.

6. **Frontend implementation**
   - [COMPLETED] Set up Vue Router, layout components and base pages (home, product list, product detail, cart, checkout, order confirmation) using Vue 3 + Vite and TailwindCSS.
   - [COMPLETED] Implement Pinia stores for catalog and cart state, including persistence of the cart token and a simple Add/Remove-from-cart UX shared between the product grid and product detail pages.
   - [COMPLETED] Integrate with Catalog and Checkout APIs for product browsing, cart operations and placing orders, including host-aware Axios clients and CORS-friendly dev setup (Vite on `http://localhost:5173` calling Nginx on `http://localhost:8080` and `http://checkout.localhost:8080`).
   - [COMPLETED] Refine client-side form validation and clear error handling for out-of-stock, price-change and payment errors, including redirecting stock/price failures from Checkout back to the Cart page.
   - [COMPLETED] Build the production Vue bundle and configure Nginx in Docker to serve the SPA from `frontend/dist` on `http://localhost:8080` while proxying Catalog and Checkout API calls.

7. **AWS infrastructure and deployment**
   - [COMPLETED] Author CloudFormation templates for networking and compute (single EC2 with Docker and MySQL container) using a Free Tier-eligible `t3.micro` host.
   - [TODO] Author a CloudFormation template for the application stack (SQS queue, IAM roles and SES permissions).
   - [COMPLETED] Deploy the networking stack (or configure the default VPC and security groups for the EC2 instance).
   - [COMPLETED] Provision the EC2 instance via CloudFormation with an IAM role, user data that installs Docker and Docker Compose, and security groups.
   - [TODO] Deploy the application stack for SQS and IAM, then configure the Laravel services to read their configuration from SSM Parameter Store.
   - [COMPLETED] Build or transfer Docker artifacts, start the Docker Compose stack on the instance, and verify that the Vue SPA and both Filament admin panels (`/catalog/admin`, `/checkout/admin`) are reachable behind Nginx on the single EC2 host.

8. **Hardening and polish**
   - Refine validation, error handling and logging across services.
   - Add authentication where applicable (for example, customer accounts via Sanctum and admin roles for Filament).
   - Apply the advanced SQL tuning plan (indexes, EXPLAIN-based query refinements, slow query monitoring) and, time permitting, implement the optional Elasticsearch/OpenSearch container and `/api/catalog/search` endpoint.
   - Add initial monitoring and alarms (EC2 metrics, SQS queue depth) and finalize CI pipeline steps for tests and basic deployment automation.


## 13. README Content Outline

The `README.md` will include:

- **Project overview and architecture diagram**.
- **Prerequisites**:
  - PHP, Composer, Node, Docker, Docker Compose.
  - AWS account and IAM user or role with required permissions to deploy CloudFormation.
- **Local setup instructions**:
  - Cloning repo.
  - Environment variable setup for each service and frontend.
  - Running containers with Docker Compose.
- **Provisioning instructions**:
  - How to deploy CloudFormation stacks (networking, database, ECS).
  - How to configure SES (verified domain, sandbox exit where applicable).
- **Deployment instructions**:
  - Building and pushing Docker images.
  - Updating CloudFormation stacks.
- **Testing instructions**:
  - Running backend and frontend tests locally and via GitHub Actions.
- **Operational notes**:
  - Rotating secrets.
  - Scaling ECS services.
  - Troubleshooting common issues.


## 14. Best Practices Summary

- Use **Laravel 12** and **Filament 4** following their official recommendations.
- Enforce separation of concerns between Catalog, Checkout, and Email services.
- Use **database-per-service** and async messaging via **SQS** for decoupling.
- Protect admin areas with strong authentication and role-based access control.
- Store secrets in **SSM Parameter Store or Secrets Manager**, not in code.
- Automate testing and deployment with **GitHub Actions**.
- Use **Docker** for reproducible environments locally and in production.
- Monitor and log all services using **CloudWatch** and alarms for critical metrics.
