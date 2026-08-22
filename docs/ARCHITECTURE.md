# Architecture and design decisions

## Shared processing flow

```text
Web/CLI
  -> CsvUserParser
  -> UserNormalizer
  -> UserValidator
  -> DuplicateEmailDetector (current file)
  -> DatabaseDuplicateEmailDetector
  -> Preview or PostgresUserRepository
```

HTTP controllers and the CLI only adapt input and output. All import rules live in `UserImportService`, so both entry points produce consistent results.

## Layers

- `Csv` streams CSV rows with a generator and validates the header, column count, and UTF-8 encoding.
- `Domain` contains records and result objects independent of HTTP, CLI, and PostgreSQL.
- `Service` contains normalization, validation, duplicate detection, and import orchestration.
- `Repository` isolates PostgreSQL queries and batch inserts.
- `Database` creates PDO connections and manages the schema.
- `Http` contains request handling, routing, controllers, JSON responses, and error mapping.
- `Cli` contains option parsing, usage text, exit codes, and console output.

## Data integrity

The database adds `NOT NULL`, unique-email, lowercase-email, and non-empty-name constraints. These provide a final protection layer in addition to application validation.

An import runs inside a transaction. An unexpected database failure rolls back the transaction to prevent a partially completed import. A unique violation caused by a concurrent import is retried once; the second attempt detects the newly inserted address and reports that record as rejected.

Preview checks duplicates in both the file and the database but never writes data. Import performs the complete preview process again inside its transaction rather than trusting preview data supplied by a client. This avoids stale validation between separate preview and import requests.

## Performance

- The parser yields rows instead of loading the complete raw CSV into memory.
- Email existence checks are issued in batches rather than one query per record.
- Valid users are inserted in batches of up to 500 records.
- Only normalized and validated domain records required by preview or import are retained.
- HTTP uploads are limited to 5 MiB to bound input resource usage.

## Security and error handling

- Credentials are read from `.env` and are never hard-coded.
- An HTTP upload is copied to an application-owned temporary file and always removed in a `finally` block.
- The API and CLI do not expose stack traces, SQL text, or connection details to users.
- The repository uses prepared statements; CSV values are never concatenated into SQL.

## Testing strategy

- Unit tests cover parsers, validators, normalizers, duplicate detectors, domain objects, repositories, and CLI option parsing.
- Feature tests cover HTTP routing, controllers, file uploads, and error responses.
- Integration tests use local PostgreSQL to verify the schema, repository, transaction, and complete import flow.
- Frontend tests cover the API client and user-interface behavior.
- `composer check` combines the PHP coding standard, PHPStan level 6, and the complete PHPUnit suite.

## Assumptions

- CSV files are comma-delimited and contain exactly the `name,surname,email` header.
- The first row is the header; validation `rowNumber` values match physical CSV line numbers.
- Email matching is case-insensitive after lowercase normalization.
- This challenge application manages its own `users` table and does not write directly into a production Moodle schema.
- CORS is currently configured only for the development frontend at `http://localhost:5173`.
