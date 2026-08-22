import type { ApiErrorDetails } from '../types';

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code: string,
    public readonly details: ApiErrorDetails = null,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}
