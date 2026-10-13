import { Outlet } from 'react-router-dom'
import SplitLayout from './SplitLayout.jsx'

export default function AuthLayout() {
  return (
    <SplitLayout>
      <Outlet />
    </SplitLayout>
  )
}
