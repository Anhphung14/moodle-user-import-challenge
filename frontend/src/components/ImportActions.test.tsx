import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ImportActions } from './ImportActions';

afterEach(cleanup);

describe('ImportActions', () => {
  it('shows the valid count and invokes import once clicked', async () => {
    const user = userEvent.setup();
    const onImport = vi.fn();
    render(<ImportActions validCount={3} isImporting={false} onImport={onImport} />);

    await user.click(screen.getByRole('button', { name: 'Import 3 users' }));

    expect(onImport).toHaveBeenCalledOnce();
  });

  it('is disabled for zero valid users and while importing', () => {
    const { rerender } = render(
      <ImportActions validCount={0} isImporting={false} onImport={vi.fn()} />,
    );
    expect(screen.getByRole('button', { name: 'Import 0 users' })).toBeDisabled();

    rerender(<ImportActions validCount={2} isImporting onImport={vi.fn()} />);
    expect(screen.getByRole('button', { name: 'Importing…' })).toBeDisabled();
  });
});
