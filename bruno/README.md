# Bruno API Collection

GUI + CLI collection to test the Customer Mobile API endpoints.

## Quick start (GUI)

1. Download Bruno from https://www.usebruno.com/downloads
2. Open this folder (`bruno/`) in Bruno
3. Select the `local` environment
4. Click the collection run button or send requests one by one

## Quick start (CLI)

```bash
cd bruno
npm install
npm run run:local
```

To get an HTML report:

```bash
npm run run:local:reporter
```

## Prerequisites

1. Start the backend:
   ```bash
   cd backend
   php artisan serve
   ```

2. Seed the demo data once:
   ```bash
   cd backend
   php artisan db:seed --class=BrunoDemoSeeder
   ```

The demo seeder creates a customer user with:
- Phone: `0911111111`
- Password: `password`

## Environments

- `local`: `http://localhost:8000/api/v1`

Edit `customer-api/environments/local.bru` if your backend runs on a different URL.
