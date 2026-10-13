import { createBrowserRouter } from 'react-router-dom'
import { lazy, Suspense } from 'react'
import AppLayout from '../components/layout/AppLayout.jsx'
import AuthLayout from '../components/layout/AuthLayout.jsx'
import ProtectedRoute from './ProtectedRoute.jsx'
import Guard from './Guard.jsx'
import { COMING_SOON_ITEMS } from '../constants/navigation.js'
import { Spinner } from '../components/ui/Skeleton.jsx'

const LoginPage = lazy(() => import('../pages/LoginPage.jsx'))
const OtpVerifyPage = lazy(() => import('../pages/OtpVerifyPage.jsx'))
const TwoFactorVerifyPage = lazy(() => import('../pages/TwoFactorVerifyPage.jsx'))
const AcceptInvitationPage = lazy(() => import('../pages/AcceptInvitationPage.jsx'))
const ForgotPasswordPage = lazy(() => import('../pages/ForgotPasswordPage.jsx'))
const ResetPasswordPage = lazy(() => import('../pages/ResetPasswordPage.jsx'))
const NewPasswordPage = lazy(() => import('../pages/NewPasswordPage.jsx'))
const VerifyEmailPage = lazy(() => import('../pages/VerifyEmailPage.jsx'))
const DashboardPage = lazy(() => import('../pages/DashboardPage.jsx'))
const PatientListPage = lazy(() => import('../pages/PatientListPage.jsx'))
const PatientDetailPage = lazy(() => import('../pages/PatientDetailPage.jsx'))
const AppointmentListPage = lazy(() => import('../pages/AppointmentListPage.jsx'))
const AppointmentDetailPage = lazy(() => import('../pages/AppointmentDetailPage.jsx'))
const MedicalRecordsPage = lazy(() => import('../pages/MedicalRecordsPage.jsx'))
const PrescriptionListPage = lazy(() => import('../pages/PrescriptionListPage.jsx'))
const PrescriptionDetailPage = lazy(() => import('../pages/PrescriptionDetailPage.jsx'))
const PharmacyPage = lazy(() => import('../pages/PharmacyPage.jsx'))
const InvoiceListPage = lazy(() => import('../pages/InvoiceListPage.jsx'))
const InvoiceDetailPage = lazy(() => import('../pages/InvoiceDetailPage.jsx'))
const ReportsPage = lazy(() => import('../pages/ReportsPage.jsx'))
const UsersPage = lazy(() => import('../pages/UsersPage.jsx'))
const StaffInvitationsPage = lazy(() => import('../pages/StaffInvitationsPage.jsx'))
const SettingsPage = lazy(() => import('../pages/SettingsPage.jsx'))
const ExpertSystemPage = lazy(() => import('../pages/ExpertSystemPage.jsx'))
const ChatPage = lazy(() => import('../pages/ChatPage.jsx'))
const ComingSoonPage = lazy(() => import('../pages/ComingSoonPage.jsx'))
const NotFoundPage = lazy(() => import('../pages/NotFoundPage.jsx'))
const ForbiddenPage = lazy(() => import('../pages/ForbiddenPage.jsx'))

function withSuspense(element) {
  return (
    <Suspense
      fallback={
        <div className="grid min-h-screen place-items-center bg-canvas">
          <Spinner label="Loading page…" />
        </div>
      }
    >
      {element}                                                                                                                 
    </Suspense>
  )
}

export const router = createBrowserRouter([
  {
    element: <AuthLayout />,
    children: [
      { path: 'login', element: withSuspense(<LoginPage />) },
      { path: 'login/verify', element: withSuspense(<OtpVerifyPage />) },
      { path: 'login/2fa', element: withSuspense(<TwoFactorVerifyPage />) },
      { path: 'accept-invitation', element: withSuspense(<AcceptInvitationPage />) },
      { path: 'forgot-password', element: withSuspense(<ForgotPasswordPage />) },
      { path: 'reset-password', element: withSuspense(<ResetPasswordPage />) },
      { path: 'reset-password/new', element: withSuspense(<NewPasswordPage />) },
      { path: 'verify-email', element: withSuspense(<VerifyEmailPage />) },
    ],
  },
  {
    path: '/',
    element: (
      <ProtectedRoute>
        <AppLayout />
      </ProtectedRoute>
    ),
    children: [
      { index: true, element: withSuspense(<DashboardPage />) },
      {
        path: 'patients',
        element: withSuspense(
          <Guard permission="patients.view">
            <PatientListPage />
          </Guard>,
        ),
      },
      {
        path: 'patients/:id',
        element: withSuspense(
          <Guard permission="patients.view">
            <PatientDetailPage />
          </Guard>,
        ),
      },
      {
        path: 'appointments',
        element: withSuspense(
          <Guard permission="appointments.view">
            <AppointmentListPage />
          </Guard>,
        ),
      },
      {
        path: 'appointments/:id',
        element: withSuspense(
          <Guard permission="appointments.view">
            <AppointmentDetailPage />
          </Guard>,
        ),
      },
      {
        path: 'medical-records',
        element: withSuspense(
          <Guard permission="medical-records.view">
            <MedicalRecordsPage />
          </Guard>,
        ),
      },
      {
        path: 'prescriptions',
        element: withSuspense(
          <Guard permission="prescriptions.view">
            <PrescriptionListPage />
          </Guard>,
        ),
      },
      {
        path: 'prescriptions/:id',
        element: withSuspense(
          <Guard permission="prescriptions.view">
            <PrescriptionDetailPage />
          </Guard>,
        ),
      },
      {
        path: 'pharmacy',
        element: withSuspense(
          <Guard any={['medicines.view', 'inventory.view']}>
            <PharmacyPage />
          </Guard>,
        ),
      },
      {
        path: 'billing',
        element: withSuspense(
          <Guard permission="billing.view">
            <InvoiceListPage />
          </Guard>,
        ),
      },
      {
        path: 'billing/:id',
        element: withSuspense(
          <Guard permission="billing.view">
            <InvoiceDetailPage />
          </Guard>,
        ),
      },
      {
        path: 'reports',
        element: withSuspense(
          <Guard permission="reports.view">
            <ReportsPage />
          </Guard>,
        ),
      },
      {
        path: 'users',
        element: withSuspense(
          <Guard permission="users.view">
            <UsersPage />
          </Guard>,
        ),
      },
      {
        path: 'invitations',
        element: withSuspense(
          <Guard permission="users.invite">
            <StaffInvitationsPage />
          </Guard>,
        ),
      },
      { path: 'settings', element: withSuspense(<SettingsPage />) },
      {
        path: 'chat',
        element: withSuspense(
          <Guard permission="chat.view">
            <ChatPage />
          </Guard>,
        ),
      },
      {
        path: 'chat/:id',
        element: withSuspense(
          <Guard permission="chat.view">
            <ChatPage />
          </Guard>,
        ),
      },
      {
        path: 'expert-system',
        element: withSuspense(
          <Guard any={['expert-system.view', 'expert-system.use']}>
            <ExpertSystemPage />
          </Guard>,
        ),
      },
      ...COMING_SOON_ITEMS.map((item) => ({
        path: item.to.replace(/^\//, ''),
        element: withSuspense(
          <ComingSoonPage title={item.label} description={item.description} Icon={item.icon} />,
        ),
      })),
      { path: 'forbidden', element: withSuspense(<ForbiddenPage />) },
    ],
  },
  { path: '*', element: withSuspense(<NotFoundPage />) },
])

export default router