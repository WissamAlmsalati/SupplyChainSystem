import { useAuth } from '../context/AuthContext'

export function useModulePermission(module) {
  const { hasPermission } = useAuth()

  return {
    canView: hasPermission(`${module}_VIEW`),
    canCreate: hasPermission(`${module}_CREATE`),
    canEdit: hasPermission(`${module}_EDIT`),
    canDelete: hasPermission(`${module}_DELETE`),
  }
}
