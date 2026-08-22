import { cleanup, render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import type { UserRecord } from '../types';
import { UserPreviewTable } from './UserPreviewTable';

afterEach(cleanup);

describe('UserPreviewTable', () => {
  it('shows normalized values and text statuses', () => {
    const records: UserRecord[] = [
      {
        rowNumber: 2,
        name: 'Élodie',
        surname: 'Smith',
        email: 'elodie@example.com',
        valid: true,
        errors: [],
      },
      {
        rowNumber: 3,
        name: '',
        surname: 'Doe',
        email: 'bad-email',
        valid: false,
        errors: [
          { rowNumber: 3, field: 'name', code: 'required', message: 'Name is required.' },
          { rowNumber: 3, field: 'email', code: 'invalid_email', message: 'Email is invalid.' },
        ],
      },
    ];
    render(<UserPreviewTable records={records} />);

    const table = screen.getByRole('table');
    expect(within(table).getByRole('columnheader', { name: 'Row' })).toBeInTheDocument();
    expect(within(table).getByText('Élodie')).toBeInTheDocument();
    expect(within(table).getByText('elodie@example.com')).toBeInTheDocument();
    expect(within(table).getByText('✓')).toHaveAttribute('aria-hidden', 'true');
    expect(within(table).getByText('Valid')).toBeInTheDocument();
    expect(within(table).getByText('Invalid')).toBeInTheDocument();
    expect(within(table).getByText('Name is required.')).toBeInTheDocument();
    expect(within(table).getByText('Email is invalid.')).toBeInTheDocument();
  });

  it('displays an explicit empty state', () => {
    render(<UserPreviewTable records={[]} />);

    expect(screen.getByText('No user records were found in this CSV file.')).toBeInTheDocument();
    expect(screen.queryByRole('table')).not.toBeInTheDocument();
  });
});
