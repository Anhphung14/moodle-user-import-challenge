import { afterEach, describe, expect, it, vi } from 'vitest';
import type { ImportPreview, ImportResult } from '../types';
import { ApiError } from './ApiError';
import { importCsv, previewCsv } from './userImportApi';

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('user import API client', () => {
  it('uploads a CSV as multipart data for preview', async () => {
    const preview: ImportPreview = {
      total: 1,
      valid: 1,
      invalid: 0,
      records: [{
        rowNumber: 2,
        name: 'John',
        surname: 'Smith',
        email: 'john@example.com',
        valid: true,
        errors: [],
      }],
    };
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: preview }));
    vi.stubGlobal('fetch', fetchMock);
    const file = new File(['name,surname,email\nJohn,Smith,john@example.com\n'], 'users.csv', {
      type: 'text/csv',
    });

    await expect(previewCsv(file)).resolves.toEqual(preview);
    expect(fetchMock).toHaveBeenCalledOnce();
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe('http://localhost:8080/api/imports/preview');
    expect(init.method).toBe('POST');
    expect(init.headers).toBeUndefined();
    expect(init.body).toBeInstanceOf(FormData);
    expect((init.body as FormData).get('file')).toBe(file);
  });

  it('uploads the original CSV to the import endpoint', async () => {
    const result: ImportResult = {
      total: 2,
      imported: 1,
      rejected: 1,
      errors: [{
        rowNumber: 3,
        field: 'email',
        code: 'invalid_email',
        message: 'Email is invalid.',
      }],
    };
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: result }));
    vi.stubGlobal('fetch', fetchMock);
    const file = new File(['csv contents'], 'users.csv', { type: 'text/csv' });

    await expect(importCsv(file)).resolves.toEqual(result);
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe('http://localhost:8080/api/imports');
    expect((init.body as FormData).get('file')).toBe(file);
  });

  it('converts a structured non-2xx response to ApiError', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({
      error: {
        code: 'invalid_csv',
        message: 'CSV header is invalid.',
        details: { rowNumber: 1 },
      },
    }, 422)));

    const promise = previewCsv(new File(['bad csv'], 'users.csv'));

    await expect(promise).rejects.toMatchObject({
      name: 'ApiError',
      status: 422,
      code: 'invalid_csv',
      message: 'CSV header is invalid.',
      details: { rowNumber: 1 },
    });
  });

  it('uses a safe error when a non-2xx response has an unknown shape', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ message: 'proxy failure' }, 502)));

    const promise = importCsv(new File(['csv'], 'users.csv'));

    await expect(promise).rejects.toEqual(expect.objectContaining<ApiError>({
      name: 'ApiError',
      status: 502,
      code: 'request_failed',
      message: 'The request could not be completed.',
      details: null,
    }));
  });

  it('rejects a successful response that is not valid JSON', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('not-json', {
      status: 200,
      headers: { 'Content-Type': 'text/plain' },
    })));

    await expect(previewCsv(new File(['csv'], 'users.csv'))).rejects.toMatchObject({
      status: 200,
      code: 'invalid_response',
    });
  });
});

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}
