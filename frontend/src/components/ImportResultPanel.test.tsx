import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { ImportResult } from '../types';
import { ImportResultPanel } from './ImportResultPanel';

afterEach(cleanup);

describe('ImportResultPanel', () => {
  it('shows result counts, final errors and restart action', async () => {
    const user = userEvent.setup();
    const onStartOver = vi.fn();
    const result: ImportResult = {
      total: 2,
      imported: 1,
      rejected: 1,
      errors: [{
        rowNumber: 3,
        field: 'email',
        code: 'duplicate_email_in_database',
        message: 'Email already exists.',
      }],
    };
    render(<ImportResultPanel result={result} onStartOver={onStartOver} />);

    expect(screen.getByRole('heading', { name: 'Import complete' })).toBeInTheDocument();
    expect(screen.getByText('Row 3, email: Email already exists.')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Start a new import' }));
    expect(onStartOver).toHaveBeenCalledOnce();
  });
});
