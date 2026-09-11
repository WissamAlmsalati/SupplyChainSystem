import Modal from './Modal'
import Button from './ui/Button'

export default function ConfirmDialog({
  open,
  title = 'تأكيد الحذف',
  message = 'هل أنت متأكد؟ لا يمكن التراجع عن هذا الإجراء.',
  confirmLabel = 'حذف',
  cancelLabel = 'إلغاء',
  loading = false,
  onConfirm,
  onCancel,
}) {
  return (
    <Modal title={title} open={open} onClose={onCancel}>
      <p className="text-sm leading-relaxed text-foreground">{message}</p>
      <div className="mt-6 flex justify-end gap-2">
        <Button variant="secondary" onClick={onCancel} disabled={loading}>{cancelLabel}</Button>
        <Button variant="danger" onClick={onConfirm} disabled={loading}>
          {loading ? 'جاري الحذف...' : confirmLabel}
        </Button>
      </div>
    </Modal>
  )
}
