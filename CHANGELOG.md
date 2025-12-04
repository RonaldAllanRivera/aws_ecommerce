# Changelog

All notable changes to this project will be documented in this file.

## 2025-12-04

### Added
- Filament 4 admin resources for Checkout `Order` and `Payment` models, with read-focused list, filter, detailed view, and delete actions.
- `CheckoutDemoSeeder` to create demo orders, items, and payments for local admin testing.
- Feature tests for Checkout cart lifecycle and validation rules, and the public order summary endpoint (see `CartApiTest` and `OrderSummaryTest` in the Checkout service).
- Strengthened Checkout `CheckoutPlaceOrderTest` to assert `SendOrderCreatedMessage` is dispatched to the `order-events` queue with a realistic payload.
- Email service `EmailLog` model and `email_logs` table for logging email sending attempts and results.
- Email service `OrderConfirmationMail` Mailable and `emails.order-confirmation` Blade view for rendering order confirmation emails.
- Email service `ProcessOrderCreated` job to consume `OrderCreated` payloads, send confirmation emails, and write `EmailLog` entries.
- Feature test coverage for the Email service `ProcessOrderCreated` job (see `ProcessOrderCreatedTest`).
- `DEPLOYMENT.md` documenting how to switch from Mailhog to SES/SQS in AWS, including environment variable examples for the Email service.
- Vue 3 + Vite SPA frontend with TailwindCSS, consuming Catalog and Checkout APIs for product listing/detail, cart, checkout and order confirmation flows (Phase 6).
- Pinia stores for Catalog and Checkout cart state, including cart token persistence and Add/Remove from cart UX on product grid and detail pages.
- Frontend Axios configuration and CORS/host-based routing adjustments to support `http://localhost:5173` calling `http://localhost:8080` (Catalog) and `http://checkout.localhost:8080` (Checkout) in the Docker-based local environment.
- Inventory-aware out-of-stock UX in the Vue SPA, driven by Catalog `inventory.quantity_available` (including a sample product seeded with zero stock), disabling new adds while still allowing removal from the cart.
- Checkout UX improvements: client-side form validation, clearer error messages for out-of-stock, price-change and payment failures, and automatic redirect from the Checkout page back to the Cart page on stock/price validation errors.
- CloudFormation templates for Phase 7 networking and compute stacks (`infra/cloudformation/networking.yml` and `infra/cloudformation/compute.yml`) provisioning a minimal VPC, public subnet, security group and a single Free Tier-eligible `t3.micro` EC2 Docker host with Docker and Docker Compose bootstrapped via user data.

### Changed
- Nginx configuration and Checkout/Catalog `.env` settings to use host-based routing (`catalog.localhost`, `checkout.localhost`) and separate session cookies per service, resolving Livewire checksum issues between the Catalog and Checkout Filament panels.
- Docker Compose + Nginx configuration to optionally serve the built Vue SPA from `frontend/dist` on `http://localhost:8080` while proxying `/catalog/*` and `/checkout/*` API calls to the respective Laravel services.
- Updated `PLAN.md`, `README.md` and `DEPLOYMENT.md` to describe the Phase 7 networking and compute stacks and the single-EC2 Docker host deployment flow.

## 2025-12-03

### Added
- Checkout database schema and Eloquent models for `Cart`, `CartItem`, `Order`, `OrderItem`, and `Payment`.
- Checkout JSON APIs for cart and checkout flows:
  - `POST /checkout/api/cart` to create or reuse an open cart.
  - `GET /checkout/api/cart` to retrieve the current cart by `cart_token`.
  - `POST /checkout/api/cart/items` to add items to a cart.
  - `PUT /checkout/api/cart/items/{id}` to update cart item quantities.
  - `DELETE /checkout/api/cart/items/{id}` to remove cart items.
  - `POST /checkout/api/place-order` to create orders and payments from carts.
  - `GET /checkout/api/orders/{orderNumber}` to retrieve order summaries.
 - Integration between Checkout and Catalog for SKU-based product lookups, canonical pricing, and product name snapshots on order items.
 - Queue job `OrderCreated` dispatched after successful `place-order`, targeting the logical `order-events` queue for downstream processing.
 - Feature tests for the Checkout `place-order` endpoint covering happy path, price-change, and out-of-stock business validation scenarios.

### Changed
 - Configured CSRF middleware in the Checkout service to exclude `checkout/*` paths, enabling stateless JSON API calls from Postman and the frontend.
 - Adjusted Checkout logging and queue configuration for the Docker-based local environment to log to stderr and use the synchronous queue driver, avoiding missing Redis extension and log file permission issues during API testing while keeping SQS as the target in AWS.

## 2025-12-02

### Added
- Docker-based local environment with MySQL, Redis, Mailhog, and Nginx reverse proxy (Phase 2 completed).
- Laravel 12 Catalog, Checkout, and Email services wired to the Docker MySQL instance.
- Basic `/health` JSON endpoints for all three services via Nginx.
- Catalog database schema (Product, Category, Inventory, ProductImage) with appropriate indexes and MySQL FULLTEXT on `name` and `description`.
- Sample Catalog data seeder (`CatalogSampleDataSeeder`) including placeholder product images.
- Catalog REST APIs exposed via Nginx:
  - `GET /catalog/api/products` (pagination, filters, FULLTEXT-based `search` on MySQL).
  - `GET /catalog/api/products/{slug}` for product detail.
  - `GET /catalog/api/categories` for listing categories.
- Documentation updates in `PLAN.md` and `README.md` to reflect completed Phase 2 and current Catalog capabilities.
 - Feature tests for Catalog API endpoints (product listing, product detail by slug, basic search), including `Tests\\CreatesApplication` and updated `Tests\\TestCase` for Laravel 12 test bootstrapping.

### Changed
- Updated `CatalogSampleDataSeeder` to use `https://placehold.co` image URLs for sample product images.

### Fixed
- Resolved Filament 4 asset and Livewire 404s for the Catalog admin panel behind Nginx (`/catalog/admin`).
- Fixed `419 Page Expired` errors on Filament login by adjusting session, CSRF, and Filament panel middleware configuration for the Catalog service.
- Updated Filament table actions to use the `Filament\Actions` namespace and cleared stale compiled views that caused Blade parse errors on Catalog admin list pages.