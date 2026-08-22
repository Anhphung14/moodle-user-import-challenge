# HTTP API

Default base URL: `http://localhost:8080`.

All responses use JSON. CORS is enabled for the development frontend at `http://localhost:5173`.

## Health check

```http
GET /api/health
```

`200 OK` response:

```json
{
  "data": {
    "status": "ok"
  }
}
```

## Preview an import

```http
POST /api/imports/preview
Content-Type: multipart/form-data
```

Required form field: `file`. This endpoint parses and validates the file without writing to the database.

`200 OK` response:

```json
{
  "data": {
    "total": 2,
    "valid": 1,
    "invalid": 1,
    "records": [
      {
        "rowNumber": 2,
        "name": "An",
        "surname": "Nguyen",
        "email": "an@example.com",
        "valid": true,
        "errors": []
      }
    ]
  }
}
```

## Import users

```http
POST /api/imports
Content-Type: multipart/form-data
```

Required form field: `file`. Valid records are stored in one transaction, while invalid records are returned as validation errors.

`200 OK` response:

```json
{
  "data": {
    "total": 2,
    "imported": 1,
    "rejected": 1,
    "errors": [
      {
        "rowNumber": 3,
        "field": "email",
        "code": "invalid_email",
        "message": "Email must be a valid email address."
      }
    ]
  }
}
```

## Error responses

HTTP errors use a consistent envelope:

```json
{
  "error": {
    "code": "file_required",
    "message": "A CSV file is required in the file field.",
    "details": null
  }
}
```

Common errors:

| HTTP status | Code | Meaning |
| --- | --- | --- |
| 400 | `file_required` | The multipart `file` field is missing |
| 400 | `upload_failed` | PHP did not receive a valid uploaded file |
| 404 | `not_found` | The requested endpoint does not exist |
| 413 | `file_too_large` | The upload exceeds 5 MiB |
| 422 | `invalid_csv` | The file is empty or has an invalid header, column count, or encoding |
| 503 | `database_unavailable` | Database configuration, connection, or operation failed |
| 500 | `internal_error` | An unexpected error occurred and technical details were hidden |

## Validation error codes

| Code | Meaning |
| --- | --- |
| `required` | A required field is empty |
| `invalid_email` | The email format is invalid |
| `duplicate_email_in_file` | The email occurs more than once in the CSV file |
| `duplicate_email_in_database` | The email already exists in PostgreSQL |
