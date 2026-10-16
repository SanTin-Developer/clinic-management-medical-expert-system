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
const WardListPage = lazy(() => import('../pages/WardListPage.jsx'))
const AdmissionListPage = lazy(() => import('../pages/AdmissionListPage.jsx'))
const NursingTaskListPage = lazy(() => import('../pages/NursingTaskListPage.jsx'))
const MarPage = lazy(() => import('../pages/MarPage.jsx'))
const AssignmentListPage = lazy(() => import('../pages/AssignmentListPage.jsx'))
const PrescriptionListPage = lazy(() => import('../pages/PrescriptionListPage.jsx'))
const PrescriptionDetailPage = lazy(() => import('../pages/PrescriptionDetailPage.jsx'))
const PharmacyPage = lazy(() => import('../pages/PharmacyPage.jsx'))
const PharmacyDashboardPage = lazy(() => import('../pages/PharmacyDashboardPage.jsx'))
const PharmacyPosPage = lazy(() => import('../pages/PharmacyPosPage.jsx'))
const InvoiceListPage = lazy(() => import('../pages/InvoiceListPage.jsx'))
const InvoiceDetailPage = lazy(() => import('../pages/InvoiceDetailPage.jsx'))
const ReportsPage = lazy(() => import('../pages/ReportsPage.jsx'))
const UsersPage = lazy(() => import('../pages/UsersPage.jsx'))
const StaffListPage = lazy(() => import('../pages/StaffListPage.jsx'))
const StaffDetailPage = lazy(() => import('../pages/StaffDetailPage.jsx'))
const StaffInvitationsPage = lazy(() => import('../pages/StaffInvitationsPage.jsx'))
const PendingApprovalsPage = lazy(() => import('../pages/PendingApprovalsPage.jsx'))
const AuditLogListPage = lazy(() => import('../pages/AuditLogListPage.jsx'))
const AnnouncementListPage = lazy(() => import('../pages/AnnouncementListPage.jsx'))
const NewsPage = lazy(() => import('../pages/NewsPage.jsx'))
const LeaveListPage = lazy(() => import('../pages/LeaveListPage.jsx'))
const QueueListPage = lazy(() => import('../pages/QueueListPage.jsx'))
const AttendancePage = lazy(() => import('../pages/AttendancePage.jsx'))
const AttendanceScanPage = lazy(() => import('../pages/AttendanceScanPage.jsx'))
const AttendanceKioskPage = lazy(() => import('../pages/AttendanceKioskPage.jsx'))
const SchedulingPage = lazy(() => import('../pages/SchedulingPage.jsx'))
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
        path: 'wards',
        element: withSuspense(
          <Guard any={['wards.view', 'beds.view']}>
            <WardListPage />
          </Guard>,
        ),
      },
      {
        path: 'admissions',
        element: withSuspense(
          <Guard permission="admissions.view">
            <AdmissionListPage />
          </Guard>,
        ),
      },
      {
        path: 'nursing-tasks',
        element: withSuspense(
          <Guard permission="nursing-tasks.view">
            <NursingTaskListPage />
          </Guard>,
        ),
      },
      {
        path: 'mar',
        element: withSuspense(
          <Guard permission="medications.view">
            <MarPage />
          </Guard>,
        ),
      },
      {
        path: 'assignments',
        element: withSuspense(
          <Guard permission="assignments.view">
            <AssignmentListPage />
          </Guard>,
        ),
      },
      {
        path: 'pharmacy',
        element: withSuspense(
          <Guard permission="medicines.view">
            <PharmacyPage />
          </Guard>,
        ),
      },
      {
        path: 'pharmacy/dashboard',
        element: withSuspense(
          <Guard any={['medicines.view', 'inventory.view']}>
            <PharmacyDashboardPage />
          </Guard>,
        ),
      },
      {
        path: 'pharmacy/pos',
        element: withSuspense(
          <Guard any={['medicines.view', 'inventory.view']}>
            <PharmacyPosPage />
          </Guard>,
        ),
      },
      {
        path: 'inventory',
        element: withSuspense(
          <Guard permission="inventory.view">
            <PharmacyPage initialTab="inventory" inventoryOnly />
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
        path: 'staff',
        element: withSuspense(
          <Guard permission="staff.view">
            <StaffListPage />
          </Guard>,
        ),
      },
      {
        path: 'staff/:id',
        element: withSuspense(
          <Guard permission="staff.view">
            <StaffDetailPage />
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
      {
        path: 'approvals',
        element: withSuspense(
          <Guard permission="users.approve">
            <PendingApprovalsPage />
          </Guard>,
        ),
      },
      {
        path: 'audit-logs',
        element: withSuspense(
          <Guard permission="audit-logs.view">
            <AuditLogListPage />
          </Guard>,
        ),
      },
      {
        path: 'announcements',
        element: withSuspense(
          <Guard any={['announcements.create', 'announcements.update', 'announcements.publish']}>
            <AnnouncementListPage />
          </Guard>,
        ),
      },
      {
        path: 'news',
        element: withSuspense(
          <Guard permission="announcements.view">
            <NewsPage />
          </Guard>,
        ),
      },
      {
        path: 'leave',
        element: withSuspense(
          <Guard permission="leave.view">
            <LeaveListPage />
          </Guard>,
        ),
      },
      {
        path: 'queue',
        element: withSuspense(
          <Guard permission="queue.view">
            <QueueListPage />
          </Guard>,
        ),
      },
      {
        path: 'attendance',
        element: withSuspense(
          <Guard permission="attendance.view">
            <AttendancePage />
          </Guard>,
        ),
      },
      {
        path: 'attendance/scan',
        element: withSuspense(
          <Guard permission="attendance.clock">
            <AttendanceScanPage />
          </Guard>,
        ),
      },
      {
        path: 'scheduling',
        element: withSuspense(
          <Guard permission="scheduling.view">
            <SchedulingPage />
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
  {
    path: '/attendance/kiosk/:sessionId',
    element: (
      <ProtectedRoute>
        {withSuspense(
          <Guard permission="attendance.session">
            <AttendanceKioskPage />
          </Guard>,
        )}
      </ProtectedRoute>
    ),
  },
  { path: '*', element: withSuspense(<NotFoundPage />) },
])

export default router
