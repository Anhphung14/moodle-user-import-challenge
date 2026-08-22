# Moodle User Import

A CSV user-import application with a React interface, a PHP REST API, and a command-line interface. The web and CLI entry points share the same parsing, normalization, validation, duplicate-detection, and PostgreSQL persistence flow.

## Requirements

- PHP 8.3+ with the `pdo_pgsql` and `mbstring` extensions
- Composer 2
- A locally running PostgreSQL instance
- Node.js 20+ and npm

## Main dependencies

Backend:

- `vlucas/phpdotenv` loads the local database configuration from `.env`.
- PHPUnit provides the unit, feature, integration, and smoke test suites.
- PHPStan performs static analysis at level 6.
- PHP CS Fixer enforces the PHP coding standard.

Frontend:

- React and React DOM provide the user interface.
- Vite provides the development server and production build.
- Vitest and Testing Library test components and user interactions.
- ESLint and TypeScript provide linting and compile-time type checks.

Exact versions are locked in `backend/composer.lock` and `frontend/package-lock.json`.

## Installation

From the project root:

```bash
cp .env.example .env
cd backend && composer install
cd ../frontend && npm install
```

Create a PostgreSQL database (the recommended name is `moodle_user_import`):

```bash
createdb moodle_user_import
```

Set the connection string in the root `.env` file:

```dotenv
DATABASE_URL=postgresql://postgres:your_password@127.0.0.1:5432/moodle_user_import
```

Never commit `.env` or real credentials. Create the `users` table with:

```bash
php backend/bin/user_upload.php --create-table
```

> Warning: `--create-table` drops and recreates the current `users` table. All existing user data will be deleted.

## Running the application

Open two terminals from the project root.

Terminal 1 — start the PHP API at `http://localhost:8080`:

```bash
php -S localhost:8080 -t backend/public backend/public/index.php
```

Verify the API:

```bash
curl http://localhost:8080/api/health
```

Terminal 2 — start React at `http://localhost:5173`:

```bash
cd frontend
npm run dev
```

The frontend calls `http://localhost:8080` by default. To use a different API URL:

```bash
VITE_API_BASE_URL=http://localhost:9000 npm run dev
```

## Web UI usage

1. Open `http://localhost:5173` after starting both the API and frontend.
2. Select a UTF-8 CSV file that follows the format below.
3. Choose **Validate CSV** to parse and validate the file without writing to the database.
4. Review the total, valid, and invalid counts. Each preview row shows its normalized values, status, and validation errors.
5. Choose **Import N users**, where `N` is the valid-record count. The server validates the uploaded file again and imports only valid records.
6. Review the final imported and rejected counts. Correct any reported rows in the source CSV before trying them again.

Selecting a different file clears the previous preview and import result. Previewing is safe to repeat because it does not modify the database.

## CSV format

Web uploads must be valid UTF-8, no larger than 5 MiB, and contain exactly these three columns in this order:

```csv
name,surname,email
An,Nguyen,an@example.com
```

Processing rules:

- Leading and trailing whitespace is removed.
- Names and surnames are normalized to initial capitals.
- Email addresses are converted to lowercase.
- `name`, `surname`, and `email` are required.
- Email addresses must have a valid format.
- An email duplicated in the same file or already stored in the database is rejected.
- Blank rows are skipped. An invalid header, column count, or UTF-8 sequence rejects the file.
- Valid rows can still be imported when other rows fail record-level validation.

An example file is available at [`samples/input/users.csv`](samples/input/users.csv).

## CLI usage

Preview a file without modifying the database:

```bash
php backend/bin/user_upload.php --file samples/input/users.csv --dry-run
```

Import all valid rows:

```bash
php backend/bin/user_upload.php --file samples/input/users.csv
```

Display help:

```bash
php backend/bin/user_upload.php --help
```

CSV paths are resolved relative to the terminal's current directory. When running from `backend`, use `../samples/input/users.csv` for the sample file.

## API usage

Preview a CSV file:

```bash
curl -F 'file=@samples/input/users.csv' \
  http://localhost:8080/api/imports/preview
```

Import a CSV file:

```bash
curl -F 'file=@samples/input/users.csv' \
  http://localhost:8080/api/imports
```

See [`docs/API.md`](docs/API.md) for endpoint contracts, responses, and error codes.

## Quality checks

Backend coding standard, static analysis, and tests:

```bash
cd backend
composer check
```

Apply PHP formatting automatically:

```bash
cd backend
composer format
```

Frontend tests, linting, and production build:

```bash
cd frontend
npm test -- --run
npm run lint
npm run build
```

Integration tests use the local PostgreSQL database configured in `.env`. They manage data in the `users` table, so never point the test suite at a production database.

## Assumptions and design decisions

- CSV files are comma-delimited, UTF-8 encoded, and contain exactly the `name,surname,email` header in that order.
- Email uniqueness is case-insensitive because addresses are normalized to lowercase before validation and storage.
- Invalid records are rejected individually; valid records in the same well-formed file can still be imported.
- Preview never writes data. Import repeats validation inside a database transaction instead of trusting client-side preview state.
- The web and CLI entry points share `UserImportService` so normalization, validation, and duplicate handling remain consistent.
- Database constraints provide a final integrity layer, while prepared statements and batched queries keep persistence safe and efficient.
- `--create-table` is intentionally destructive because the challenge explicitly requires a create/rebuild operation.
- The application manages its own `users` table and does not write directly into a production Moodle schema.
- CORS is configured for the local Vite origin (`http://localhost:5173`). Production deployment would require environment-specific origin configuration.

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for the complete processing flow, transaction strategy, concurrency handling, and testing rationale.

## Project structure

```text
backend/bin/       CLI entry point
backend/public/    HTTP entry point
backend/src/       Domain, CSV, service, repository, database, and HTTP code
backend/tests/     Unit, feature, integration, and smoke tests
database/          PostgreSQL schema
frontend/src/      React UI, API client, types, and component tests
samples/input/     Example CSV input
docs/              API and architecture documentation
```
