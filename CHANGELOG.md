# Changelog

All notable changes to this project will be documented in this file.

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
