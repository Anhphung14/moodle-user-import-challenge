interface ImportActionsProps {
  validCount: number;
  isImporting: boolean;
  onImport: () => void;
}

export function ImportActions({ validCount, isImporting, onImport }: ImportActionsProps) {
  return (
    <div className="import-actions">
      <button
        type="button"
        disabled={validCount === 0 || isImporting}
        onClick={onImport}
      >
        {isImporting ? 'Importing…' : `Import ${validCount} users`}
      </button>
      {validCount === 0 && (
        <p>No valid users are available to import.</p>
      )}
    </div>
  );
}
