import {
  BarChart3,
  Boxes,
  BrainCircuit,
  CalendarCheck,
  CalendarClock,
  CalendarDays,
  CalendarOff,
  FolderHeart,
  LayoutDashboard,
  ListOrdered,
  MailPlus,
  Megaphone,
  MessageSquare,
  Newspaper,
  PackageSearch,
  Pill,
  QrCode,
  Receipt,
  ScrollText,
  Settings,
  ShieldCheck,
  UserCheck,
  Users,
} from "lucide-react";

export const NAV_SECTIONS = [
  {
    id: "main",
    label: "Main Menu",
    items: [
      {
        to: "/",
        label: "Dashboard",
        icon: LayoutDashboard,
        end: true,
        permissions: [],
      },
      {
        to: "/patients",
        label: "Patients",
        icon: Users,
        permissions: ["patients.view"],
      },
      {
        to: "/appointments",
        label: "Appointments",
        icon: CalendarDays,
        permissions: ["appointments.view"],
      },
      {
        to: "/queue",
        label: "Queue Management",
        icon: ListOrdered,
        permissions: ["queue.view"],
      },
      {
        to: "/news",
        label: "News & Updates",
        icon: Newspaper,
        permissions: ["announcements.view"],
      },
    ],
  },
  {
    id: "clinical",
    label: "Clinical",
    items: [
      {
        to: "/medical-records",
        label: "Medical Records",
        icon: FolderHeart,
        permissions: ["medical-records.view"],
      },
      {
        to: "/prescriptions",
        label: "Prescriptions",
        icon: Pill,
        permissions: ["prescriptions.view"],
      },
      {
        to: "/wards",
        label: "Wards & Beds",
        icon: LayoutDashboard,
        permissions: ["wards.view", "beds.view"],
      },
      {
        to: "/admissions",
        label: "Admissions",
        icon: UserCheck,
        permissions: ["admissions.view"],
      },
      {
        to: "/nursing-tasks",
        label: "Nursing Tasks",
        icon: CalendarCheck,
        permissions: ["nursing-tasks.view"],
      },
      {
        to: "/mar",
        label: "MAR",
        icon: Pill,
        permissions: ["medications.view"],
      },
      {
        to: "/assignments",
        label: "Nurse Assignments",
        icon: Users,
        permissions: ["assignments.view"],
      },
      {
        to: "/expert-system",
        label: "Expert System",
        icon: BrainCircuit,
        permissions: ["expert-system.view", "expert-system.use"],
      },
    ],
  },
  {
    id: "operations",
    label: "Operations",
    items: [
      {
        to: "/chat",
        label: "Team Chat",
        icon: MessageSquare,
        permissions: ["chat.view"],
      },
      {
        to: "/pharmacy",
        label: "Pharmacy",
        icon: PackageSearch,
        end: true,
        permissions: ["medicines.view"],
      },
      {
        to: "/pharmacy/dashboard",
        label: "Pharmacy Dashboard",
        icon: BarChart3,
        end: true,
        permissions: ["medicines.view", "inventory.view"],
      },
      {
        to: "/pharmacy/pos",
        label: "Pharmacy POS",
        icon: Receipt,
        permissions: ["inventory.manage"],
      },
      {
        to: "/inventory",
        label: "Inventory & Supplies",
        icon: Boxes,
        permissions: ["inventory.view"],
      },
      {
        to: "/billing",
        label: "Billing & Payments",
        icon: Receipt,
        permissions: ["billing.view"],
      },
      {
        to: "/reports",
        label: "Reports",
        icon: BarChart3,
        permissions: ["reports.view"],
      },
    ],
  },
  {
    id: "staff",
    label: "Staff Management",
    items: [
      {
        to: "/staff",
        label: "Staff Directory",
        icon: Users,
        permissions: ["staff.view"],
      },
      {
        to: "/attendance/scan",
        label: "Scan QR",
        icon: QrCode,
        permissions: ["attendance.clock"],
      },
      {
        to: "/attendance",
        label: "Staff Attendance",
        icon: CalendarCheck,
        end: true,
        permissions: ["attendance.view"],
      },
      {
        to: "/scheduling",
        label: "Staff Scheduling",
        icon: CalendarClock,
        permissions: ["scheduling.view"],
      },
      {
        to: "/leave",
        label: "Leave Management",
        icon: CalendarOff,
        permissions: ["leave.view"],
      },
      {
        to: "/announcements",
        label: "Announcements",
        icon: Megaphone,
        permissions: ["announcements.create", "announcements.update", "announcements.publish"],
      },
    ],
  },
  {
    id: "administration",
    label: "Administration",
    items: [
      {
        to: "/users",
        label: "Users & Roles",
        icon: ShieldCheck,
        permissions: ["users.view"],
      },
      {
        to: "/invitations",
        label: "Staff Invitations",
        icon: MailPlus,
        permissions: ["users.invite"],
      },
      {
        to: "/approvals",
        label: "Pending Approvals",
        icon: UserCheck,
        permissions: ["users.approve"],
      },
      {
        to: "/audit-logs",
        label: "Audit Logs",
        icon: ScrollText,
        permissions: ["audit-logs.view"],
      },
      { to: "/settings", label: "Settings", icon: Settings, permissions: [] },
    ],
  },
];

export const NAV_ITEMS = NAV_SECTIONS.flatMap((section) => section.items);

export const COMING_SOON_ITEMS = NAV_ITEMS.filter((item) => item.comingSoon);
