import type {
  ApiErrorPayload,
  ImportPreview,
  ImportResult,
} from '../types';
import { ApiError } from './ApiError';
import { API_BASE_URL } from './config';

interface SuccessEnvelope<T> {
  data: T;
}

interface ErrorEnvelope {
  error: ApiErrorPayload;
}

export async function previewCsv(file: File): Promise<ImportPreview> {
  return sendCsv<ImportPreview>('/api/imports/preview', file);
}

export async function importCsv(file: File): Promise<ImportResult> {
  return sendCsv<ImportResult>('/api/imports', file);
}

async function sendCsv<T>(path: string, file: File): Promise<T> {
  const body = new FormData();
  body.append('file', file);

  const response = await fetch(`${API_BASE_URL}${path}`, {
    method: 'POST',
    body,
  });
  const payload: unknown = await parseJson(response);

  if (!response.ok) {
    throw toApiError(response.status, payload);
  }

  if (!isSuccessEnvelope<T>(payload)) {
    throw new ApiError(
      'The server returned an invalid response.',
      response.status,
      'invalid_response',
    );
  }

  return payload.data;
}

async function parseJson(response: Response): Promise<unknown> {
  try {
    return await response.json();
  } catch {
    throw new ApiError(
      'The server returned an invalid response.',
      response.status,
      'invalid_response',
    );
  }
}

function toApiError(status: number, payload: unknown): ApiError {
  if (isErrorEnvelope(payload)) {
    return new ApiError(
      payload.error.message,
      status,
      payload.error.code,
      payload.error.details,
    );
  }

  return new ApiError(
    'The request could not be completed.',
    status,
    'request_failed',
  );
}

function isSuccessEnvelope<T>(payload: unknown): payload is SuccessEnvelope<T> {
  return isObject(payload) && 'data' in payload;
}

function isErrorEnvelope(payload: unknown): payload is ErrorEnvelope {
  if (!isObject(payload) || !isObject(payload.error)) {
    return false;
  }

  const { error } = payload;

  return (
    typeof error.code === 'string'
    && typeof error.message === 'string'
    && 'details' in error
    && (error.details === null || isObject(error.details))
  );
}

function isObject(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
