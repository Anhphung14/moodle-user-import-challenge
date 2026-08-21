import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import App from './App';

describe('App', () => {
  it('renders the user import placeholder', () => {
    render(<App />);

    expect(
      screen.getByRole('heading', { name: 'User Import' }),
    ).toBeInTheDocument();
    expect(
      screen.getByText('CSV upload workflow coming next.'),
    ).toBeInTheDocument();
  });
});
