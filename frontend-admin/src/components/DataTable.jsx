import Button from './ui/Button'
import { Table, Thead, Tbody, Tr, Th, Td } from './ui/Table'
import { SkeletonTable } from './ui/Skeleton'

export default function DataTable({ columns, rows, loading, emptyText, actions, pagination, onPageChange, onRowClick }) {
  if (loading) return <SkeletonTable columns={columns.length + (actions ? 1 : 0)} />

  const showPagination = pagination && pagination.last_page > 1

  return (
    <div className="space-y-3">
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
              <Td
                colSpan={columns.length + (actions ? 1 : 0)}
                className="py-8 text-center text-muted"
              >
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
                  <Td key={col.key}>
                    {col.render ? col.render(row) : row[col.key] ?? '-'}
                  </Td>
                ))}
                {actions && (
                  <Td onClick={(e) => e.stopPropagation()}>
                    <div className="flex items-center gap-2">
                      {actions(row)}
                    </div>
                  </Td>
                )}
              </Tr>
            ))
          )}
        </Tbody>
      </Table>

      {showPagination && (
        <div className="flex items-center justify-between">
          <div className="text-sm text-muted">
            صفحة {pagination.current_page} من {pagination.last_page} — الإجمالي: {pagination.total}
          </div>
          <div className="flex items-center gap-2">
            <Button
              variant="secondary"
              size="sm"
              onClick={() => onPageChange(pagination.current_page - 1)}
              disabled={pagination.current_page <= 1}
            >
              السابق
            </Button>
            <Button
              variant="secondary"
              size="sm"
              onClick={() => onPageChange(pagination.current_page + 1)}
              disabled={pagination.current_page >= pagination.last_page}
            >
              التالي
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
