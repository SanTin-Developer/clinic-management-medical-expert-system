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
  PackageSearch,
  Pill,
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
        permissions: [],
        comingSoon: true,
        description:
          "Smart patient queue with live status tracking and a call-next workflow.",
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
        permissions: ["medicines.view", "inventory.view"],
      },
      {
        to: "/inventory",
        label: "Inventory & Supplies",
        icon: Boxes,
        permissions: [],
        comingSoon: true,
        description:
          "Stock levels, suppliers, purchase orders, and low-stock alerts in one place.",
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
        to: "/attendance",
        label: "Staff Attendance",
        icon: CalendarCheck,
        permissions: [],
        comingSoon: true,
        description:
          "Clock in and out, track shifts, and generate attendance reports.",
      },
      {
        to: "/scheduling",
        label: "Staff Scheduling",
        icon: CalendarClock,
        permissions: [],
        comingSoon: true,
        description: "Build rosters, assign shifts, and prevent coverage gaps.",
      },
      {
        to: "/leave",
        label: "Leave Management",
        icon: CalendarOff,
        permissions: [],
        comingSoon: true,
        description:
          "Submit leave requests, approve them, and keep balances accurate.",
      },
      {
        to: "/announcements",
        label: "Announcements",
        icon: Megaphone,
        permissions: [],
        comingSoon: true,
        description: "Broadcast important updates to teams and departments.",
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
        permissions: [],
        comingSoon: true,
        description:
          "A complete trail of system activity for security and compliance.",
      },
      { to: "/settings", label: "Settings", icon: Settings, permissions: [] },
    ],
  },
];

export const NAV_ITEMS = NAV_SECTIONS.flatMap((section) => section.items);

export const COMING_SOON_ITEMS = NAV_ITEMS.filter((item) => item.comingSoon);
