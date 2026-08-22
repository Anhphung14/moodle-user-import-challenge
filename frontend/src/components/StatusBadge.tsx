interface StatusBadgeProps {
  valid: boolean;
}

export function StatusBadge({ valid }: StatusBadgeProps) {
  return (
    <span className={`status-badge ${valid ? 'status-valid' : 'status-invalid'}`}>
      <span aria-hidden="true">{valid ? '✓' : '!'}</span>
      {valid ? 'Valid' : 'Invalid'}
    </span>
  );
}
