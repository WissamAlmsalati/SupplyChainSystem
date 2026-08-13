#!/bin/bash
# Start TAQA frontend + Laravel backend for local development (no Docker)

set -e

cd "$(dirname "$0")"

# Kill any existing servers on ports 5173 and 8000
lsof -ti:5173 | xargs kill -9 2>/dev/null || true
lsof -ti:8000 | xargs kill -9 2>/dev/null || true

# Start Laravel backend
nohup bash -c 'cd backend && DB_CONNECTION=sqlite DB_DATABASE="$(pwd)/database/database.sqlite" php artisan serve --host=127.0.0.1 --port=8000' > backend.log 2>&1 &
echo "Backend PID: $!"

# Start Vite frontend
nohup bash -c 'VITE_BACKEND_URL=http://127.0.0.1:8000 npx vite --host' > frontend.log 2>&1 &
echo "Frontend PID: $!"

echo "Servers starting..."
echo "Frontend: http://localhost:5173"
echo "Backend:  http://127.0.0.1:8000"
echo "Logs: backend.log, frontend.log"
