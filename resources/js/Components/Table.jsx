export default function Table({ columns, rows, keyField='id' }) {
  return (
    <div className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
      <table className="min-w-full divide-y divide-gray-100">
        <thead className="bg-gray-50">
          <tr>
            {columns.map(c => (
              <th key={c.key} className="px-4 py-3 text-left text-xs font-semibold text-gray-600">{c.label}</th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100">
          {rows.map(r => (
            <tr key={r[keyField]} className="hover:bg-gray-50">
              {columns.map(c => (
                <td key={c.key} className="px-4 py-3 text-sm text-gray-800">
                  {typeof c.render === 'function' ? c.render(r) : r[c.key]}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
