import type { UserRecord } from '../types';
import { StatusBadge } from './StatusBadge';

interface UserPreviewTableProps {
  records: UserRecord[];
}

export function UserPreviewTable({ records }: UserPreviewTableProps) {
  if (records.length === 0) {
    return <p className="empty-preview">No user records were found in this CSV file.</p>;
  }

  return (
    <div className="table-scroll" tabIndex={0} aria-label="User preview table, scroll horizontally if needed">
      <table className="preview-table">
        <thead>
          <tr>
            <th scope="col">Row</th>
            <th scope="col">Name</th>
            <th scope="col">Surname</th>
            <th scope="col">Email</th>
            <th scope="col">Status</th>
            <th scope="col">Errors</th>
          </tr>
        </thead>
        <tbody>
          {records.map((record) => (
            <tr key={record.rowNumber}>
              <td>{record.rowNumber}</td>
              <td>{record.name}</td>
              <td>{record.surname}</td>
              <td>{record.email}</td>
              <td><StatusBadge valid={record.valid} /></td>
              <td>
                {record.errors.length === 0 ? (
                  <span className="no-errors">None</span>
                ) : (
                  <ul className="record-errors">
                    {record.errors.map((error, index) => (
                      <li key={`${error.field}-${error.code}-${index}`}>
                        <strong>{error.field}:</strong> {error.message}
                      </li>
                    ))}
                  </ul>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
