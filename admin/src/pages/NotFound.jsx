import { useNavigate } from 'react-router-dom'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../components/ui/Card'
import Button from '../components/ui/Button'

export default function NotFound() {
  const navigate = useNavigate()

  return (
    <div className="flex min-h-[60vh] items-center justify-center p-4">
      <Card className="w-full max-w-md text-center">
        <CardHeader>
          <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary text-3xl font-extrabold text-primary-foreground">
            404
          </div>
          <CardTitle>الصفحة غير موجودة</CardTitle>
          <CardDescription>لم نتمكن من العثور على الصفحة المطلوبة.</CardDescription>
        </CardHeader>
        <CardContent>
          <Button variant="primary" onClick={() => navigate('/')}>
            العودة للرئيسية
          </Button>
        </CardContent>
      </Card>
    </div>
  )
}
