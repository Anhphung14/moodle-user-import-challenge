# Implementation notes

This document explains how the application was designed, why the main implementation choices were made, and which trade-offs remain. Setup and usage instructions are kept in the main [`README.md`](../README.md), while endpoint contracts are documented in [`API.md`](API.md).

## 1. Interpreting the problem

The application has two entry points with the same business requirements:

- A React web interface that uploads, previews, and imports a CSV file.
- A PHP CLI that imports a file or performs the same work in dry-run mode.

The central design goal was therefore not merely to support two interfaces, but to ensure that both interfaces produce the same normalization, validation, duplicate detection, and persistence decisions.

The resulting flow is:

```text
CSV input
  -> parse
  -> normalize
  -> validate fields
  -> detect duplicates in the file
  -> detect duplicates in PostgreSQL
  -> preview or import valid records
```

## 2. Shared application logic

`UserImportService` owns the import workflow. HTTP controllers and the CLI are adapters: they collect input, call the service, and translate its result for their respective output formats.

This separation was chosen because duplicating import rules in controllers and CLI commands would allow the two interfaces to drift. Keeping domain and service code independent from HTTP and console concerns also makes it easier to test without starting a server.

The implementation uses explicit dependency construction in the two entry points instead of adding a dependency-injection framework. The object graph is small, and keeping it visible reduces framework overhead for a coding challenge of this size.

## 3. CSV parsing

`CsvUserParser` uses PHP's CSV parser rather than manually splitting strings. This correctly handles quoted values and embedded delimiters.

The parser yields one `UserRecord` at a time through a generator. This avoids first creating a second, complete in-memory copy of the raw CSV. The validated records are still retained because preview must return every row and import must classify the complete file.

The parser rejects structural file errors:

- Missing or unreadable files.
- Empty files.
- A header other than exactly `name,surname,email`.
- Rows with the wrong number of columns.
- Invalid UTF-8 data.

A UTF-8 byte-order mark is accepted in the first header field because spreadsheet applications commonly add one. Completely blank rows are ignored.

Structural errors reject the file because column-to-field mapping would otherwise be ambiguous. Record-level validation errors reject only the affected record, allowing other valid records to be imported.

## 4. Normalization

Normalization happens before validation and duplicate detection:

- Surrounding whitespace is removed.
- Names and surnames are converted to lowercase and then capitalized at word boundaries.
- Email addresses are trimmed and converted to lowercase.

Doing this first makes validation and uniqueness checks operate on the same canonical values that will be stored. In particular, `USER@EXAMPLE.COM` and `user@example.com` cannot bypass duplicate detection through casing differences.

The name transformation supports spaces, apostrophes, typographic apostrophes, and hyphens. It uses multibyte string functions so non-ASCII names are handled more safely than with byte-based casing functions.

## 5. Validation and error reporting

`UserValidator` checks required fields and email syntax. Each problem is represented by a `ValidationError` containing:

- The physical CSV row number.
- The affected field.
- A stable machine-readable code.
- A human-readable message.

Stable codes let the web and CLI present errors differently without parsing message text. Row numbers let users locate and repair source data directly.

Validation results are represented by `ValidatedUserRecord` rather than mutating the parsed record. This keeps the transition from unvalidated input to validated domain data explicit.

## 6. Duplicate email handling

Uniqueness is enforced at three levels:

1. `DuplicateEmailDetector` rejects repeated normalized addresses within the current file.
2. `DatabaseDuplicateEmailDetector` rejects addresses already stored in PostgreSQL.
3. A unique database constraint protects against concurrency and programming errors.

Application checks are required for useful row-level feedback, while the database constraint is required for correctness. An application-only check would have a race between checking an address and inserting it.

Database existence checks are batched in groups of up to 500 addresses rather than issuing one query for every row. Duplicate input addresses are removed from the lookup batch to reduce placeholders and database work.

## 7. Preview and import correctness

Preview runs the complete parsing and validation flow but never inserts records. It is therefore safe to repeat and is also used by CLI `--dry-run`.

Import does not accept a client-provided list of previously approved records. Instead, the client uploads the original file again and the server performs the complete validation flow inside the import operation. This prevents stale or modified client state from bypassing server-side rules.

Only valid records are passed to the repository. Invalid records remain visible in the result so a partially valid, structurally sound file can still make progress.

## 8. Transactions and concurrency

An import owns a PostgreSQL transaction unless it is already participating in one. Batch inserts either complete together or roll back together when an unexpected failure occurs.

A concurrent transaction can insert an email after duplicate detection but before this application inserts it. PostgreSQL then raises unique violation `23505`. When the service owns the transaction, it retries the import once. The second preview sees the newly stored email and reports that row as rejected instead of exposing a raw database error.

The retry is deliberately limited to one attempt. Repeated retries could hide a persistent failure or create unbounded work under contention.

## 9. Repository and schema

`PostgresUserRepository` isolates SQL from application services. It uses prepared statements for both lookups and inserts, so CSV values are never concatenated into SQL text.

Valid users are inserted in batches of up to 500. Batching reduces database round trips while keeping statement and parameter sizes bounded.

The schema adds safeguards beyond the minimum challenge requirements:

- An identity primary key.
- `NOT NULL` constraints.
- Non-empty trimmed name checks.
- A unique email constraint.
- A lowercase email check.
- A timezone-aware creation timestamp.

These constraints are the final integrity boundary even if data is written by code outside this importer.

## 10. HTTP API design

The API exposes separate preview and import endpoints. Both accept `multipart/form-data`, which avoids encoding the complete file in JSON.

Uploads are limited to 5 MiB. The controller checks both PHP's upload status and the actual temporary-file size. The uploaded file is copied to an application-owned temporary path and removed in a `finally` block after processing.

Success responses use a `data` envelope, and failures use a consistent `error` envelope. Expected CSV errors are separated from unavailable-database errors and unexpected internal errors. Technical exception details are logged rather than returned to the browser.

The HTTP layer is intentionally small and framework-free. A larger application would likely use a mature router, middleware stack, dependency container, and configurable CORS package, but those dependencies would add more structure than this challenge requires.

## 11. CLI design

The CLI supports the required `--file`, `--dry-run`, `--create-table`, and `--help` options. Invalid arguments return a distinct exit code from runtime failures, which makes the command easier to use in scripts.

Normal output is written to standard output and errors to standard error. Dry-run executes the same preview service used by the web API and performs no insert.

`--create-table` rebuilds the table transactionally where possible. It prints an explicit warning because the operation is destructive and required by the challenge specification.

## 12. Frontend design

The React interface follows a single, explicit workflow:

```text
Select file -> Validate -> Review preview -> Import valid users -> Review result
```

API payloads have TypeScript types, while the API client still checks the top-level response shape at runtime because network data cannot be trusted solely through compile-time types.

The UI clears stale preview and result state when the selected file changes. Loading and importing states disable repeated actions, and status/error messages use accessible live regions. The preview table exposes normalized values and row-level errors before an import is allowed.

The interface remains deliberately simple because clarity and maintainability are more important here than visual complexity.

## 13. Error-handling strategy

Errors are handled at the boundary closest to the user:

- The parser explains file and structural CSV failures.
- Validators attach field and row details.
- The repository lets database exceptions reach the service transaction boundary.
- HTTP maps known exception types to stable status codes and safe messages.
- CLI maps invalid arguments and runtime failures to separate exit codes.

Unexpected exceptions never expose stack traces, SQL text, credentials, or internal paths in user-facing output.

## 14. Testing strategy

The test suite is divided by responsibility:

- Unit tests verify isolated domain, parsing, normalization, validation, duplicate, CLI, database, and repository behavior.
- Feature tests verify HTTP routing, upload validation, JSON responses, and error mapping.
- Integration tests exercise schema operations, repository queries, transactions, HTTP import, CLI import, and service behavior against local PostgreSQL.
- Smoke tests confirm basic application loading.
- Frontend tests cover the API client, workflow states, summaries, tables, actions, and final results.

Quality gates also include PHP CS Fixer, PHPStan level 6, ESLint, TypeScript compilation, and the Vite production build.

The database tests intentionally require an explicit local `DATABASE_URL`. They should never be run against a production database because schema and table contents are managed during testing.

## 15. Security considerations

- Database credentials are supplied through `.env` and excluded from Git.
- SQL values are bound through prepared statements.
- Server-side validation is repeated during import.
- Upload size and structure are checked before processing.
- Temporary upload copies are always removed.
- Internal exception details are not returned to users.

Authentication, authorization, rate limiting, malware scanning, and CSRF protection are outside this coding challenge's scope. They would be required before exposing an import endpoint in a production Moodle environment.

## 16. Trade-offs and alternatives

### Framework-free PHP

Plain PHP keeps the challenge focused on application design and makes the shared logic easy to inspect. A production service with more endpoints would benefit from a framework for routing, middleware, validation, dependency injection, and operational integrations.

### Partial record success

A structurally valid file imports valid rows while reporting invalid rows. Rejecting the complete file would provide stronger all-or-nothing business semantics, but the challenge explicitly emphasizes rejecting invalid records and reporting preview counts. The chosen behavior allows users to import useful records without concealing failures.

### In-memory validated records

Raw CSV reading is streamed, but preview retains validated records so it can display every result. For files much larger than the current 5 MiB web limit, a staged database import, background job, or paginated preview would be more appropriate.

### Fixed development CORS origin

CORS currently permits the local Vite origin. A production deployment should read an allowed frontend origin from environment configuration rather than committing a deployment-specific URL.

## 17. Known limitations

- The web upload limit is fixed at 5 MiB.
- CSV delimiter and header order are fixed.
- There is no authentication or role-based access control.
- Imports run synchronously rather than through a job queue.
- Preview results are not persisted or assigned an import token.
- CORS is configured for local development only.
- The application uses a standalone challenge `users` table rather than Moodle's complete user model and APIs.
- CLI input does not have the HTTP upload-size limit, although it still uses the same parser and validation rules.

## 18. Production deployment considerations

Before production deployment, the following work would be required:

- Configure the allowed frontend origin through an environment variable.
- Use managed PostgreSQL with encrypted connections and least-privilege credentials.
- Add authentication, authorization, rate limiting, and audit logging.
- Add request identifiers, structured logs, metrics, and error monitoring.
- Run schema migrations rather than a destructive rebuild command.
- Consider background processing and progress reporting for large imports.
- Define retention and privacy rules for uploaded personal data.
- Deploy API compute close to the database to reduce latency.

For a demonstration deployment, the Vite frontend can be hosted on Vercel while the PHP API runs on a PHP-capable service and connects to managed PostgreSQL. Deploying PHP directly to Vercel would require adapting the API to a community PHP function runtime and should remain isolated from the core challenge implementation.

## 19. Future improvements

- Configurable delimiters and optional header mapping.
- Downloadable validation-error reports.
- Persistent import history and audit records.
- Background imports with progress and cancellation.
- Configurable duplicate policies such as skip or update.
- Database migrations and deployment automation.
- Authentication and Moodle-specific permission checks.
- Integration through Moodle's supported APIs and complete user-field requirements.
