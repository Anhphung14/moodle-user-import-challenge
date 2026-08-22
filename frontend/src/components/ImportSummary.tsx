import type { ImportPreview } from '../types';

interface ImportSummaryProps {
  preview: ImportPreview;
}

export function ImportSummary({ preview }: ImportSummaryProps) {
  return (
    <dl className="import-summary" aria-label="CSV validation summary">
      <div className="summary-card">
        <dt>Total</dt>
        <dd>{preview.total}</dd>
      </div>
      <div className="summary-card summary-card-valid">
        <dt>Valid</dt>
        <dd>{preview.valid}</dd>
      </div>
      <div className="summary-card summary-card-invalid">
        <dt>Invalid</dt>
        <dd>{preview.invalid}</dd>
      </div>
    </dl>
  );
}
