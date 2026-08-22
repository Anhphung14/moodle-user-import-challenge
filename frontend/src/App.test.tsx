import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ApiError, previewCsv } from './api';
import App from './App';
import type { ImportPreview } from './types';

vi.mock('./api', async (importOriginal) => ({
  ...await importOriginal<typeof import('./api')>(),
  previewCsv: vi.fn(),
}));

const previewMock = vi.mocked(previewCsv);
const preview: ImportPreview = {
  total: 2,
  valid: 1,
  invalid: 1,
  records: [],
};

afterEach(() => {
  cleanup();
  vi.clearAllMocks();
});

describe('App CSV validation workflow', () => {
  it('does not allow validation before a file is selected', () => {
    render(<App />);

    expect(screen.getByRole('heading', { name: 'User Import' })).toBeInTheDocument();
    expect(screen.getByLabelText('CSV file')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Validate CSV' })).toBeDisabled();
  });

  it('selects one CSV and sends it to the preview API', async () => {
    const user = userEvent.setup();
    previewMock.mockResolvedValue(preview);
    render(<App />);
    const file = new File(['name,surname,email\nJohn,Smith,john@example.com'], 'users.csv', {
      type: 'text/csv',
    });

    await user.upload(screen.getByLabelText('CSV file'), file);
    expect(screen.getByText('Selected: users.csv')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Validate CSV' }));

    expect(previewMock).toHaveBeenCalledOnce();
    expect(previewMock).toHaveBeenCalledWith(file);
    expect(await screen.findByRole('heading', { name: 'Preview ready' })).toBeInTheDocument();
    expect(screen.getByText('2 records found: 1 valid and 1 invalid.')).toBeInTheDocument();
  });

  it('shows loading state while preview is pending', async () => {
    const user = userEvent.setup();
    let resolvePreview: ((value: ImportPreview) => void) | undefined;
    previewMock.mockReturnValue(new Promise((resolve) => {
      resolvePreview = resolve;
    }));
    render(<App />);

    await user.upload(screen.getByLabelText('CSV file'), new File(['csv'], 'users.csv'));
    await user.click(screen.getByRole('button', { name: 'Validate CSV' }));

    expect(screen.getByRole('button', { name: 'Validating…' })).toBeDisabled();
    expect(screen.getByRole('status')).toHaveTextContent('Uploading and validating CSV…');
    resolvePreview?.(preview);
    expect(await screen.findByRole('heading', { name: 'Preview ready' })).toBeInTheDocument();
  });

  it('rejects a non-CSV file before calling the API', async () => {
    const user = userEvent.setup({ applyAccept: false });
    render(<App />);

    await user.upload(screen.getByLabelText('CSV file'), new File(['text'], 'users.txt'));

    expect(screen.getByRole('alert')).toHaveTextContent('Please choose a file with a .csv extension.');
    expect(screen.getByRole('button', { name: 'Validate CSV' })).toBeDisabled();
    expect(previewMock).not.toHaveBeenCalled();
  });

  it('shows API errors and allows the user to start over', async () => {
    const user = userEvent.setup();
    previewMock.mockRejectedValue(new ApiError('CSV header is invalid.', 422, 'invalid_csv'));
    render(<App />);

    await user.upload(screen.getByLabelText('CSV file'), new File(['bad'], 'users.csv'));
    await user.click(screen.getByRole('button', { name: 'Validate CSV' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('CSV header is invalid.');
    await user.click(screen.getByRole('button', { name: 'Start over' }));
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    expect(screen.queryByText(/Selected:/)).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Validate CSV' })).toBeDisabled();
  });

  it('clears an old preview when another file is selected', async () => {
    const user = userEvent.setup();
    previewMock.mockResolvedValue(preview);
    render(<App />);

    await user.upload(screen.getByLabelText('CSV file'), new File(['first'], 'first.csv'));
    await user.click(screen.getByRole('button', { name: 'Validate CSV' }));
    expect(await screen.findByRole('heading', { name: 'Preview ready' })).toBeInTheDocument();

    await user.upload(screen.getByLabelText('CSV file'), new File(['second'], 'second.csv'));
    expect(screen.queryByRole('heading', { name: 'Preview ready' })).not.toBeInTheDocument();
    expect(screen.getByText('Selected: second.csv')).toBeInTheDocument();
  });
});
