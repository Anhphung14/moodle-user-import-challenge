import { type FormEvent, useEffect, useRef, useState } from 'react';
import { ApiError, importCsv, previewCsv } from './api';
import { CsvFilePicker } from './components/CsvFilePicker';
import { ImportSummary } from './components/ImportSummary';
import { ImportActions } from './components/ImportActions';
import { ImportResultPanel } from './components/ImportResultPanel';
import { UserPreviewTable } from './components/UserPreviewTable';
import type { ImportPreview, ImportResult } from './types';

function App() {
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<ImportPreview | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [isImporting, setIsImporting] = useState(false);
  const [result, setResult] = useState<ImportResult | null>(null);
  const [inputKey, setInputKey] = useState(0);
  const previewTitleRef = useRef<HTMLHeadingElement>(null);

  useEffect(() => {
    if (preview !== null) {
      previewTitleRef.current?.focus();
    }
  }, [preview]);

  const handleFileChange = (selectedFile: File | null) => {
    setPreview(null);
    setResult(null);
    setError(null);

    if (selectedFile !== null && !selectedFile.name.toLowerCase().endsWith('.csv')) {
      setFile(null);
      setError('Please choose a file with a .csv extension.');
      setInputKey((key) => key + 1);
      return;
    }

    setFile(selectedFile);
  };

  const handleImport = async () => {
    if (file === null || preview === null || preview.valid === 0 || isImporting) {
      return;
    }

    setIsImporting(true);
    setError(null);

    try {
      setResult(await importCsv(file));
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? caught.message
          : 'The users could not be imported. Please try again.',
      );
    } finally {
      setIsImporting(false);
    }
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
    setResult(null);
    setError(null);
    setIsImporting(false);
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
        {result !== null ? (
          <ImportResultPanel result={result} onStartOver={resetWorkflow} />
        ) : (
          <>
            <form className="upload-form" onSubmit={handleSubmit}>
              <CsvFilePicker
                file={file}
                disabled={isLoading || isImporting}
                inputKey={inputKey}
                onFileChange={handleFileChange}
              />
              <div className="form-actions">
                <button type="submit" disabled={file === null || isLoading || isImporting}>
                  {isLoading ? 'Validating…' : 'Validate CSV'}
                </button>
                {(file !== null || preview !== null || error !== null) && (
                  <button
                    type="button"
                    className="button-secondary"
                    disabled={isLoading || isImporting}
                    onClick={resetWorkflow}
                  >
                    Start over
                  </button>
                )}
              </div>
            </form>

            {isLoading && (
              <p className="notice notice-loading" role="status" aria-live="polite">
                <span className="loading-spinner" aria-hidden="true" />
                Uploading and validating CSV…
              </p>
            )}
            {error !== null && (
              <p className="notice notice-error" role="alert" aria-live="assertive" aria-atomic="true">
                {error}
              </p>
            )}
            {preview !== null && (
              <section className="preview-section" aria-labelledby="preview-title">
                <h2 ref={previewTitleRef} id="preview-title" tabIndex={-1}>Preview ready</h2>
                <ImportSummary preview={preview} />
                <UserPreviewTable records={preview.records} />
                <ImportActions
                  validCount={preview.valid}
                  isImporting={isImporting}
                  onImport={handleImport}
                />
              </section>
            )}
          </>
        )}
      </section>
    </main>
  );
}

export default App;
