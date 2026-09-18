import Button from './ui/Button'
import { Table, Thead, Tbody, Tr, Th, Td } from './ui/Table'
import { SkeletonTable } from './ui/Skeleton'

const cell = (col, row) => (col.render ? col.render(row) : row[col.key] ?? '-')

/**
 * A row's worth of data on a phone, where a table would force the reader to
 * scroll sideways through columns. Columns can say where they belong with
 * `mobile: 'title' | 'subtitle' | 'hide'`; without a hint the first column
 * titles the card and the rest become labelled fields.
 */
function Cards({ columns, rows, actions, onRowClick, emptyText }) {
  const title = columns.find((c) => c.mobile === 'title') ?? columns[0]
  const subtitle = columns.find((c) => c.mobile === 'subtitle')
  const fields = columns.filter((c) => c !== title && c !== subtitle && c.mobile !== 'hide')

  if (rows.length === 0) {
    return <div className="rounded-xl border border-border bg-surface p-8 text-center text-muted shadow-sm">{emptyText || 'لا توجد بيانات.'}</div>
  }

  return (
    <ul className="space-y-3">
      {rows.map((row) => (
        <li
          key={row.id}
          onClick={() => onRowClick?.(row)}
          className={`rounded-xl border border-border bg-surface p-4 shadow-sm ${onRowClick ? 'cursor-pointer active:bg-background' : ''}`}
        >
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0 flex-1">
              <div className="font-bold break-words text-foreground">{cell(title, row)}</div>
              {subtitle && <div className="mt-0.5 text-sm break-words text-muted">{cell(subtitle, row)}</div>}
            </div>
            {actions && (
              <div className="shrink-0" onClick={(e) => e.stopPropagation()}>
                {actions(row)}
              </div>
            )}
          </div>
          {fields.length > 0 && (
            <dl className="mt-3 grid grid-cols-2 gap-x-3 gap-y-2.5 border-t border-border pt-3 text-sm">
              {fields.map((col) => (
                <div key={col.key} className="min-w-0">
                  <dt className="text-xs text-muted">{col.label}</dt>
                  <dd className="mt-0.5 break-words text-foreground">{cell(col, row)}</dd>
                </div>
              ))}
            </dl>
          )}
        </li>
      ))}
    </ul>
  )
}

export default function DataTable({ columns, rows, loading, emptyText, actions, pagination, onPageChange, onRowClick }) {
  if (loading) return <SkeletonTable columns={columns.length + (actions ? 1 : 0)} />

  const showPagination = pagination && pagination.last_page > 1

  return (
    <div className="space-y-3">
      <div className="md:hidden">
        <Cards columns={columns} rows={rows} actions={actions} onRowClick={onRowClick} emptyText={emptyText} />
      </div>

      <div className="hidden md:block">
        <Table>
          <Thead>
            <Tr>
              {columns.map((col) => (
                <Th key={col.key}>{col.label}</Th>
              ))}
              {actions && <Th className="w-px whitespace-nowrap">إجراءات</Th>}
            </Tr>
          </Thead>
          <Tbody>
            {rows.length === 0 ? (
              <Tr>
                <Td colSpan={columns.length + (actions ? 1 : 0)} className="py-8 text-center text-muted">
                  {emptyText || 'لا توجد بيانات.'}
                </Td>
              </Tr>
            ) : (
              rows.map((row) => (
                <Tr
                  key={row.id}
                  onClick={() => onRowClick?.(row)}
                  className={onRowClick ? 'cursor-pointer hover:bg-background/60' : ''}
                >
                  {columns.map((col) => (
                    <Td key={col.key}>{cell(col, row)}</Td>
                  ))}
                  {actions && (
                    <Td onClick={(e) => e.stopPropagation()}>
                      <div className="flex items-center gap-2">{actions(row)}</div>
                    </Td>
                  )}
                </Tr>
              ))
            )}
          </Tbody>
        </Table>
      </div>

      {showPagination && (
        <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
          <div className="text-sm text-muted">
            صفحة {pagination.current_page} من {pagination.last_page} — الإجمالي: {pagination.total}
          </div>
          <div className="flex w-full items-center gap-2 sm:w-auto">
            <Button variant="secondary" size="sm" className="flex-1 sm:flex-none" onClick={() => onPageChange(pagination.current_page - 1)} disabled={pagination.current_page <= 1}>
              السابق
            </Button>
            <Button variant="secondary" size="sm" className="flex-1 sm:flex-none" onClick={() => onPageChange(pagination.current_page + 1)} disabled={pagination.current_page >= pagination.last_page}>
              التالي
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
