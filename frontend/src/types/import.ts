export interface ValidationError {
  rowNumber: number;
  field: string;
  code: string;
  message: string;
}

export interface UserRecord {
  rowNumber: number;
  name: string;
  surname: string;
  email: string;
  valid: boolean;
  errors: ValidationError[];
}

export interface ImportPreview {
  total: number;
  valid: number;
  invalid: number;
  records: UserRecord[];
}

export interface ImportResult {
  total: number;
  imported: number;
  rejected: number;
  errors: ValidationError[];
}

export type ApiErrorDetails = Record<string, unknown> | null;

export interface ApiErrorPayload {
  code: string;
  message: string;
  details: ApiErrorDetails;
}
