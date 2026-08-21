# Moodle User Import Application

A small user-import application for processing CSV files through both a React web interface and a PHP command-line interface. The backend will share the same parsing, normalisation, validation, preview, and import logic across both entry points.

## Technology stack

- PHP 8.3+
- PostgreSQL
- React with TypeScript
- Git

## Planned import flow

```text
Upload -> Parse -> Validate -> Preview -> Import
```

## Repository structure

```text
backend/    PHP application, CLI, and backend tests
frontend/   React web interface and frontend tests
database/   PostgreSQL schema and database assets
samples/    Example CSV files
```

## Local PostgreSQL

Install and run PostgreSQL locally, then create the application's environment
file:

```bash
cp .env.example .env
```

Add your local PostgreSQL connection string to `DATABASE_URL` in `.env`. The
expected format is:

```dotenv
DATABASE_URL=postgresql://username:password@127.0.0.1:5432/moodle_user_import
```

The `.env` file is ignored by Git. Never commit a real connection string or
database credentials.

## Development status

The project is being implemented incrementally. Phase 1 establishes the repository, backend and frontend tooling, and the local PostgreSQL environment.

## Documentation roadmap

The completed README will include:

- Requirements and installation
- Database configuration
- Backend and frontend startup instructions
- Web UI and CLI usage
- CLI examples
- Testing instructions
- Assumptions and design decisions
