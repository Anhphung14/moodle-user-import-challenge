import type { ChangeEvent } from 'react';

interface CsvFilePickerProps {
  file: File | null;
  disabled?: boolean;
  inputKey: number;
  onFileChange: (file: File | null) => void;
}

export function CsvFilePicker({
  file,
  disabled = false,
  inputKey,
  onFileChange,
}: CsvFilePickerProps) {
  const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
    onFileChange(event.target.files?.[0] ?? null);
  };

  return (
    <div className="file-picker">
      <label htmlFor="csv-file">CSV file</label>
      <input
        key={inputKey}
        id="csv-file"
        name="file"
        type="file"
        accept=".csv,text/csv"
        aria-describedby="csv-file-hint csv-selected-file"
        disabled={disabled}
        onChange={handleChange}
      />
      <p id="csv-file-hint" className="file-hint">
        Required columns: name, surname, email. Maximum size: 5 MiB.
      </p>
      <p id="csv-selected-file" className="selected-file" aria-live="polite">
        {file === null ? 'No file selected.' : `Selected: ${file.name}`}
      </p>
    </div>
  );
}
