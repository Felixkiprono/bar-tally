## Bar Tally

Bar Tally is a lightweight system for clubs that tallies daily stocks and sales. It helps club managers and bar staff track inventory, record sales per shift, and produce end-of-day summaries and reports.

### Features
- Inventory tracking: record stock levels, deliveries, and adjustments.
- Sales logging: capture itemized sales per shift, per bartender, or per point-of-sale.
- Daily summaries: end-of-day stock reconciliation and sales reports.
- Shift and user reporting: sales by shift, bartender performance, and cash-up summaries.
- CSV import/export: import product lists and export reports for accounting.
- Simple role management: admin, manager, and staff access levels.

### Typical Architecture
- Built with Laravel and a relational database (MySQL/Postgres supported).
- Optional Redis for queues and caching.
- Frontend can be classic Blade, Inertia, or a SPA depending on the app configuration.

### Local Development
1. Install dependencies:
   - `composer install`
   - `npm install`
2. Environment:
   - `cp .env.example .env`
   - `php artisan key:generate`
3. Database and assets:
   - Configure DB settings in `.env`
   - `php artisan migrate --seed`
   - `npm run dev`
4. Run services:
   - App (local): `php artisan serve`
   - Optional queues: `php artisan queue:work`

### Docker (optional)
If a `docker-compose.yml` is present you can run the full stack with:
```bash
docker compose up -d --build
# App available on the configured host/port (e.g. http://localhost:8000)
```

### Testing
- Run the test suite:
```bash
php artisan test
```

### Documentation & Next Steps
- Add project-specific docs to the `docs/` directory (inventory mapping, report examples, shift workflows).
- If you want automated tenant databases or per-club isolation, implement DB provisioning in `app/Models/Tenant.php` or add a CLI command to create club databases and run migrations.

### License
MIT
# bar-tally
