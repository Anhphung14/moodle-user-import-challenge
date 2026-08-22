const configuredBaseUrl = import.meta.env.VITE_API_BASE_URL?.trim();

export const API_BASE_URL = (
  configuredBaseUrl === undefined || configuredBaseUrl === ''
    ? 'http://localhost:8080'
    : configuredBaseUrl
).replace(/\/+$/, '');
