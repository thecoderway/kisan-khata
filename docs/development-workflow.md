# Development Workflow

## Requirements

- Docker Engine and Docker Compose
- WSL 2 (for Windows development)

PHP, Composer, Node.js and npm run inside Docker containers.
Do not install Node.js or PHP locally for this project.

## Start the application

Run the application:

    docker compose up -d

URLs:
- Application: http://localhost:8081
- phpMyAdmin: http://localhost:8082
- Vite: http://localhost:5173

## PHP dependencies and environment

    docker compose exec app composer install
    docker compose exec app composer setup

The setup helper creates `.env` if missing and generates APP_KEY
only when empty. It does not run database migrations.

## Frontend

Install dependencies:

    docker compose run --rm frontend npm ci

Build production assets:

    docker compose run --rm frontend npm run build

The frontend service runs the Vite development server automatically.

## Tests and code style

    docker compose exec app composer test
    docker compose exec app composer test:unit
    docker compose exec app composer test:feature
    docker compose exec app composer lint

## Useful commands

    docker compose ps
    docker compose logs -f app
    docker compose logs -f frontend
    docker compose down

Do not use `docker compose down -v` unless you intentionally want
to remove persistent volumes, including the MySQL database volume.
