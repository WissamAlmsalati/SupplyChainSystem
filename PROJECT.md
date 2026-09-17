---
title: "الساحل لمستلزمات المقاهي"
date: 2026-08-21
tags: [project, supply-chain, customer, laravel, react]
status: growing
goal: "Enhance AI results for the cafe supply-chain platform by keeping structured context, decisions, and code docs in one place."
context: |
  This is an existing cafe supply-chain software project at /Users/wissamalmsalati/cafe-supply-chain.
  Stack: Laravel backend, React + Vite frontends (admin and customer), Docker, MySQL, Redis, Reverb WebSocket.
  Docs live in docs/. The project already has backend, frontend-admin, frontend-customer, and Docker setup.
  We are using Obsidian + AI to improve decision-making, documentation, and feature planning.
---

# الساحل لمستلزمات المقاهي

## Goal

Enhance AI results for the cafe supply-chain platform by keeping structured context, decisions, and code docs in one place.

## Repository

- Path: `/Users/wissamalmsalati/cafe-supply-chain`

## Architecture

- `backend/` — Laravel API, DB, Redis, queue worker, Reverb, Nginx.
- `frontend-admin/` — Admin dashboard (React + Vite).
- `frontend-customer/` — Customer mobile/web app (React + Vite).
- `docs/` — Documentation and diagrams.
- `vendor/` — Third-party dependencies.

## Docs

- `docs/customer-endpoints-demo.md` — API endpoints demo.
- `docs/sequence-diagrams.md` — Sequence diagrams.

## Development quickstart

```bash
cd backend
cp .env.example .env
# set APP_ENV=local
docker compose up -d --build

cd ../frontend-admin
docker compose up -d --build

cd ../frontend-customer
docker compose up -d --build
```

## Default credentials

- Email: `admin@example.com`
- Password: `password`

## Open questions

- What feature or improvement should we tackle first?
- Are there bugs, performance issues, or missing tests?
- What should AI help document or refactor?

## Tasks

- [ ] Review current docs and identify gaps.
- [ ] Pick the next feature or fix to work on.
- [ ] Update this PROJECT.md as decisions are made.

## Related

- [[Home]] (in AI New ERA vault)
