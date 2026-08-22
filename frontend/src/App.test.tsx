import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ApiError, importCsv, previewCsv } from './api';
import App from './App';
import type { ImportPreview, ImportResult } from './types';

vi.mock('./api', async (importOriginal) => ({
  ...await importOriginal<typeof import('./api')>(),
  importCsv: vi.fn(),
  previewCsv: vi.fn(),
}));

const previewMock = vi.mocked(previewCsv);
const importMock = vi.mocked(importCsv);
const preview: ImportPreview = {
  total: 2,
  valid: 1,
  invalid: 1,
  records: [],
};
const importResult: ImportResult = {
  total: 2,
  imported: 1,
  rejected: 1,
  errors: [],
};

afterEach(() => {
  cleanup();
  vi.clearAllMocks();
});

describe('App import workflow', () => {
  async function reachPreview(user: ReturnType<typeof userEvent.setup>, value = preview) {
    previewMock.mockResolvedValue(value);
    const file = new File(['name,surname,email\nJohn,Smith,john@example.com'], 'users.csv');
    await user.upload(screen.getByLabelText('CSV file'), file);
    await user.click(screen.getByRole('button', { name: 'Validate CSV' }));
    await screen.findByRole('heading', { name: 'Preview ready' });

    return file;
  }

  it('imports the original file and displays the final result', async () => {
    const user = userEvent.setup();
    importMock.mockResolvedValue(importResult);
    render(<App />);
    const file = await reachPreview(user);

    await user.click(screen.getByRole('button', { name: 'Import 1 users' }));

    expect(importMock).toHaveBeenCalledOnce();
    expect(importMock).toHaveBeenCalledWith(file);
    expect(await screen.findByRole('heading', { name: 'Import complete' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Import complete' })).toHaveFocus();
    expect(screen.getByText('1 users imported and 1 rejected.')).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Preview ready' })).not.toBeInTheDocument();
  });

  it('disables import when preview has no valid users', async () => {
    const user = userEvent.setup();
    render(<App />);
    await reachPreview(user, { total: 1, valid: 0, invalid: 1, records: [] });

    expect(screen.getByRole('button', { name: 'Import 0 users' })).toBeDisabled();
    expect(screen.getByText('No valid users are available to import.')).toBeInTheDocument();
  });

  it('prevents a second import request while the first is pending', async () => {
    const user = userEvent.setup();
    let resolveImport: ((value: ImportResult) => void) | undefined;
    importMock.mockReturnValue(new Promise((resolve) => {
      resolveImport = resolve;
    }));
    render(<App />);
    await reachPreview(user);

    const button = screen.getByRole('button', { name: 'Import 1 users' });
    await user.click(button);
    await user.click(button);

    expect(importMock).toHaveBeenCalledOnce();
    expect(screen.getByRole('button', { name: 'Importing…' })).toBeDisabled();
    resolveImport?.(importResult);
    expect(await screen.findByRole('heading', { name: 'Import complete' })).toBeInTheDocument();
  });

  it('keeps preview visible and shows an import API error', async () => {
    const user = userEvent.setup();
    importMock.mockRejectedValue(new ApiError('Database is unavailable.', 503, 'database_unavailable'));
    render(<App />);
    await reachPreview(user);

    await user.click(screen.getByRole('button', { name: 'Import 1 users' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Database is unavailable.');
    expect(screen.getByRole('heading', { name: 'Preview ready' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Import 1 users' })).toBeEnabled();
  });

  it('starts a new workflow after a successful import', async () => {
    const user = userEvent.setup();
    importMock.mockResolvedValue(importResult);
    render(<App />);
    await reachPreview(user);
    await user.click(screen.getByRole('button', { name: 'Import 1 users' }));
    await screen.findByRole('heading', { name: 'Import complete' });

    await user.click(screen.getByRole('button', { name: 'Start a new import' }));

    expect(screen.getByLabelText('CSV file')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Validate CSV' })).toBeDisabled();
    expect(screen.queryByRole('heading', { name: 'Import complete' })).not.toBeInTheDocument();
  });
});

describe('App CSV validation workflow', () => {
  it('does not allow validation before a file is selected', () => {
    render(<App />);

    expect(screen.getByRole('heading', { name: 'User Import' })).toBeInTheDocument();
    expect(screen.getByLabelText('CSV file')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Validate CSV' })).toBeDisabled();
  });

  it('provides a logical keyboard focus order', async () => {
    const user = userEvent.setup();
    render(<App />);

    await user.tab();
    expect(screen.getByLabelText('CSV file')).toHaveFocus();
    await user.upload(screen.getByLabelText('CSV file'), new File(['csv'], 'users.csv'));
    await user.tab();
    expect(screen.getByRole('button', { name: 'Validate CSV' })).toHaveFocus();
    await user.tab();
    expect(screen.getByRole('button', { name: 'Start over' })).toHaveFocus();
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
    expect(screen.getByRole('heading', { name: 'Preview ready' })).toHaveFocus();
    const summary = screen.getByLabelText('CSV validation summary');
    expect(within(summary).getByText('Total')).toBeInTheDocument();
    expect(within(summary).getByText('2')).toBeInTheDocument();
    expect(screen.getByText('No user records were found in this CSV file.')).toBeInTheDocument();
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
