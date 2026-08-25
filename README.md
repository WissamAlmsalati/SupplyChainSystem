# SupplyChainSystem

Cafe supply-chain platform split into independent projects. Each project owns its Docker setup.

## Project layout

```
.
├── backend/              # Laravel API, DB, Redis, worker, Reverb, Nginx
│   ├── docker-compose.yml
│   ├── docker-compose.prod.yml
│   ├── Dockerfile
│   ├── docker-entrypoint.sh
│   └── docker/           # PHP tuning, DB init, Nginx config
├── frontend-admin/       # Admin dashboard (React + Vite)
│   ├── docker-compose.yml
│   ├── docker-compose.prod.yml
│   └── Dockerfile
├── frontend-cafe/        # Cafe mobile/web app (React + Vite)
│   ├── docker-compose.yml
│   ├── docker-compose.prod.yml
│   └── Dockerfile
└── docs/                 # Documentation and diagrams
```

## Environment

Copy the backend example environment file and adjust secrets:

```bash
cp backend/.env.example backend/.env
```

Edit `backend/.env` and set `APP_ENV=local` for development.

## Development

### Backend

```bash
cd backend
docker compose up -d --build
```

The backend stack includes its own Nginx reverse proxy, so you get:
- API base URL: http://localhost/api/v1
- Swagger docs: http://localhost/docs
- Storage files: http://localhost/storage
- Reverb WebSocket: ws://localhost/app/local
- MySQL: localhost:3306
- Redis: localhost:6379

### Admin dashboard

```bash
cd frontend-admin
docker compose up -d --build
```

Dev server: http://localhost:5173

### Cafe app

```bash
cd frontend-cafe
docker compose up -d --build
```

Dev server: http://localhost:5174

### Stop a project

```bash
docker compose down
```

## Production

Production builds optimized static frontends and runs the queue worker:

```bash
cd backend
docker compose -f docker-compose.prod.yml up -d --build

cd ../frontend-admin
docker compose -f docker-compose.prod.yml up -d --build

cd ../frontend-cafe
docker compose -f docker-compose.prod.yml up -d --build
```

In production the backend exposes port `80` with its own Nginx container, and the frontend containers serve their static builds internally.

## Default admin credentials (development)

When `APP_ENV=local`, the database is seeded automatically on first start:

- Email: `admin@example.com`
- Password: `password`
