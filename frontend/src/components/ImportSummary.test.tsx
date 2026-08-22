import { cleanup, render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import type { ImportPreview } from '../types';
import { ImportSummary } from './ImportSummary';

afterEach(cleanup);

describe('ImportSummary', () => {
  it('displays total valid and invalid counts', () => {
    const preview: ImportPreview = { total: 7, valid: 5, invalid: 2, records: [] };
    render(<ImportSummary preview={preview} />);
    const summary = screen.getByLabelText('CSV validation summary');

    expect(within(summary).getByText('Total')).toBeInTheDocument();
    expect(within(summary).getByText('7')).toBeInTheDocument();
    expect(within(summary).getByText('Valid')).toBeInTheDocument();
    expect(within(summary).getByText('5')).toBeInTheDocument();
    expect(within(summary).getByText('Invalid')).toBeInTheDocument();
    expect(within(summary).getByText('2')).toBeInTheDocument();
  });
});
