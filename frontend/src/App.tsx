import { type FormEvent, useState } from 'react';
import { ApiError, previewCsv } from './api';
import { CsvFilePicker } from './components/CsvFilePicker';
import type { ImportPreview } from './types';

function App() {
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<ImportPreview | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [inputKey, setInputKey] = useState(0);

  const handleFileChange = (selectedFile: File | null) => {
    setPreview(null);
    setError(null);

    if (selectedFile !== null && !selectedFile.name.toLowerCase().endsWith('.csv')) {
      setFile(null);
      setError('Please choose a file with a .csv extension.');
      setInputKey((key) => key + 1);
      return;
    }

    setFile(selectedFile);
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (file === null || isLoading) {
      return;
    }

    setIsLoading(true);
    setError(null);
    setPreview(null);

    try {
      setPreview(await previewCsv(file));
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? caught.message
          : 'The CSV file could not be validated. Please try again.',
      );
    } finally {
      setIsLoading(false);
    }
  };

  const resetWorkflow = () => {
    setFile(null);
    setPreview(null);
    setError(null);
    setInputKey((key) => key + 1);
  };

  return (
    <main className="app-shell">
      <section className="import-panel" aria-labelledby="page-title">
        <p className="eyebrow">Moodle Platform</p>
        <h1 id="page-title">User Import</h1>
        <p>
          Upload, validate, preview, and import users from a CSV file.
        </p>
        <form className="upload-form" onSubmit={handleSubmit}>
          <CsvFilePicker
            file={file}
            disabled={isLoading}
            inputKey={inputKey}
            onFileChange={handleFileChange}
          />
          <div className="form-actions">
            <button type="submit" disabled={file === null || isLoading}>
              {isLoading ? 'Validating…' : 'Validate CSV'}
            </button>
            {(file !== null || preview !== null || error !== null) && (
              <button type="button" className="button-secondary" disabled={isLoading} onClick={resetWorkflow}>
                Start over
              </button>
            )}
          </div>
        </form>

        {isLoading && <p className="notice" role="status">Uploading and validating CSV…</p>}
        {error !== null && <p className="notice notice-error" role="alert">{error}</p>}
        {preview !== null && (
          <section className="preview-placeholder" aria-labelledby="preview-title">
            <h2 id="preview-title">Preview ready</h2>
            <p>
              {preview.total} records found: {preview.valid} valid and {preview.invalid} invalid.
            </p>
            <p>The detailed record preview will appear here next.</p>
          </section>
        )}
      </section>
    </main>
  );
}

export default App;
