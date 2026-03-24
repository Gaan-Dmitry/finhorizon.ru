# FinHorizon SaaS Demo

FinHorizon is now configured as a PHP + MySQL SaaS-style dashboard with:

- registration and login;
- isolated company workspaces;
- dynamic KPI, budget, scenario and report calculations from MySQL data;
- phpMyAdmin for database administration;
- an SQL bootstrap file for schema + seed data.

## Quick start

1. Copy `.env.example` to `.env`.
2. Start MySQL and phpMyAdmin:
   ```bash
   docker compose up -d
   ```
3. Start PHP locally:
   ```bash
   php -S 0.0.0.0:8080
   ```
4. Open:
   - App: `http://localhost:8080`
   - phpMyAdmin: `http://localhost:8081`

## Demo account

- Email: `owner@demo.fin`
- Password: `DemoPass123!`

## Database

The schema and seed data live in `sql/schema.sql`.
