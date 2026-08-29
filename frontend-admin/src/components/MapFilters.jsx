import { useSearchParams } from 'react-router-dom'
import Button from './ui/Button'

export default function MapFilters() {
  const [searchParams, setSearchParams] = useSearchParams()
  const layer = searchParams.get('layer') || 'all'
  const gridVisible = searchParams.get('grid') !== '0'
  const showPricedOnly = searchParams.get('priced') === '1'

  const setLayer = (value) => {
    setSearchParams((prev) => {
      prev.set('layer', value)
      return prev
    }, { replace: true })
  }

  const toggleGrid = () => {
    setSearchParams((prev) => {
      prev.set('grid', gridVisible ? '0' : '1')
      return prev
    }, { replace: true })
  }

  const togglePriced = () => {
    setSearchParams((prev) => {
      prev.set('priced', showPricedOnly ? '0' : '1')
      return prev
    }, { replace: true })
  }

  return (
    <div className="flex flex-wrap items-center gap-2">
      <span className="text-sm font-semibold text-foreground ms-2">الخريطة</span>
      <Button variant={layer === 'warehouses' ? 'primary' : 'secondary'} size="sm" onClick={() => setLayer('warehouses')}>المستودعات</Button>
      <Button variant={layer === 'branches' ? 'primary' : 'secondary'} size="sm" onClick={() => setLayer('branches')}>الفروع</Button>
      <Button variant={layer === 'delegates' ? 'primary' : 'secondary'} size="sm" onClick={() => setLayer('delegates')}>المناديب</Button>
      <Button variant={layer === 'zones' ? 'primary' : 'secondary'} size="sm" onClick={() => setLayer('zones')}>المناطق</Button>
      <Button variant={layer === 'all' ? 'primary' : 'secondary'} size="sm" onClick={() => setLayer('all')}>الكل</Button>
      <span className="hidden h-6 w-px bg-border sm:inline-block" />
      <Button variant={gridVisible ? 'primary' : 'secondary'} size="sm" onClick={toggleGrid}>
        {gridVisible ? 'إخفاء الشبكة' : 'إظهار الشبكة'}
      </Button>
      <Button variant={showPricedOnly ? 'primary' : 'secondary'} size="sm" onClick={togglePriced}>
        {showPricedOnly ? 'المسعّرة فقط' : 'كل الخلايا'}
      </Button>
    </div>
  )
}
