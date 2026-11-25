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

### Mandatory PHP formatting (Laravel Pint)

Laravel Pint is a required pre-commit and pre-push check for every PHP change,
including new or modified tests, controllers, services, requests, policies,
models, migrations, commands and other PHP files.

After each PHP edit, format the touched PHP file(s):

    docker compose exec app vendor/bin/pint path/to/ChangedFile.php

Then run the repository-wide formatting check:

    docker compose exec app composer lint

The `composer lint` script runs `vendor/bin/pint --test`. It checks formatting
without changing files. A successful check must happen after the final PHP edit
and before committing or pushing. If any PHP file changes afterwards, repeat
the formatting and check steps.

To inspect whether a specific file conforms without modifying it:

    docker compose exec app vendor/bin/pint --test path/to/ChangedFile.php

If Pint reports a failure, inspect its exact file/output and the resulting
formatter diff; correct the reported formatting, then rerun the checks. Do not
guess at whitespace or make unrelated code changes while fixing a formatting
failure. Never disable Pint rules, add ignore entries, or weaken Pint
configuration to make a check pass without explicit approval.

CI must pass its Pint formatting check before a change is considered ready
for merge. Report formatting as passed only when the command or CI result was
actually observed. Pint is separate from PHPUnit: a passing Pint check does
not establish that tests passed.

## Useful commands

    docker compose ps
    docker compose logs -f app
    docker compose logs -f frontend
    docker compose down

Do not use `docker compose down -v` unless you intentionally want
to remove persistent volumes, including the MySQL database volume.
