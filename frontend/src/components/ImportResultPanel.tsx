import { useEffect, useRef } from 'react';
import type { ImportResult } from '../types';

interface ImportResultPanelProps {
  result: ImportResult;
  onStartOver: () => void;
}

export function ImportResultPanel({ result, onStartOver }: ImportResultPanelProps) {
  const titleRef = useRef<HTMLHeadingElement>(null);

  useEffect(() => {
    titleRef.current?.focus();
  }, []);

  return (
    <section
      className="result-panel"
      aria-labelledby="result-title"
      aria-live="polite"
      aria-atomic="true"
    >
      <p className="result-icon" aria-hidden="true">✓</p>
      <h2 ref={titleRef} id="result-title" tabIndex={-1}>Import complete</h2>
      <p>{result.imported} users imported and {result.rejected} rejected.</p>
      <dl className="result-counts">
        <div>
          <dt>Total processed</dt>
          <dd>{result.total}</dd>
        </div>
        <div>
          <dt>Imported</dt>
          <dd>{result.imported}</dd>
        </div>
        <div>
          <dt>Rejected</dt>
          <dd>{result.rejected}</dd>
        </div>
      </dl>
      {result.errors.length > 0 && (
        <div className="result-errors">
          <h3>Rejected record errors</h3>
          <ul>
            {result.errors.map((error, index) => (
              <li key={`${error.rowNumber}-${error.field}-${error.code}-${index}`}>
                Row {error.rowNumber}, {error.field}: {error.message}
              </li>
            ))}
          </ul>
        </div>
      )}
      <button type="button" onClick={onStartOver}>Start a new import</button>
    </section>
  );
}
