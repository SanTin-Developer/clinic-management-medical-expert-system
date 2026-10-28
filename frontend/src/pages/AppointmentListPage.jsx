import { useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { toast } from "sonner";
import {
  CalendarPlus,
  Calendar,
  CalendarClock,
  CheckCircle2,
  Clock,
  Pencil,
  Trash2,
  X,
} from "lucide-react";
import StatCard from "../components/ui/StatCard.jsx";
import { appointmentApi } from "../api/appointments.js";
import { usePaginated } from "../hooks/usePaginated.js";
import { useAuth } from "../hooks/useAuth.js";
import PageHeader from "../components/ui/PageHeader.jsx";
import Button from "../components/ui/Button.jsx";
import Card from "../components/ui/Card.jsx";
import Badge from "../components/ui/Badge.jsx";
import { useLanguage } from "../hooks/useLanguage.js";
import ConfirmDialog from "../components/ui/ConfirmDialog.jsx";
import Tabs from "../components/ui/Tabs.jsx";
import Table, { THead, TBody, Tr, Th, Td } from "../components/ui/Table.jsx";
import Pagination from "../components/ui/Pagination.jsx";
import { RowSkeleton } from "../components/ui/Skeleton.jsx";
import { EmptyState } from "../components/ui/State.jsx";
import AppointmentFormModal from "../components/appointments/AppointmentFormModal.jsx";
import Field from "../components/ui/Field.jsx";
import Input from "../components/ui/Input.jsx";
import { formatDate, formatTime, patientFullName } from "../utils/format.js";
import { statusBadgeFor, APPOINTMENT_STATUS } from "../utils/status.js";

export default function AppointmentListPage() {
  const { t } = useLanguage();
  const { can } = useAuth();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState("all");
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(null);

  const date = searchParams.get("date") ?? "";

  const query = usePaginated(
    ["appointments"],
    (params) => appointmentApi.list(params),
    {
      page,
      params: { date: date || undefined },
    },
  );

  const updateDate = (value) => {
    const next = new URLSearchParams(searchParams);
    if (value) {
      next.set("date", value);
    } else {
      next.delete("date");
    }
    setSearchParams(next, { replace: true });
    setPage(1);
  };

  const all = query.data?.data ?? [];
  const stats = query.data?.stats ?? {};
  const items = status === "all" ? all : all.filter((a) => a.status === status);
  const counts = {
    all: all.length,
    scheduled: all.filter((a) => a.status === "scheduled").length,
    confirmed: all.filter((a) => a.status === "confirmed").length,
    in_progress: all.filter((a) => a.status === "in_progress").length,
    completed: all.filter((a) => a.status === "completed").length,
  };

  const confirmDelete = async () => {
    if (!deleting) return;
    try {
      await appointmentApi.remove(deleting.id);
      toast.success("Appointment deleted.");
      setDeleting(null);
      query.refetch();
    } catch (err) {
      toast.error(err.message);
    }
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title={t("Appointments")}
        description={t("Schedule, reschedule and track doctor appointments.")}
        breadcrumb={[{ label: "Appointments" }]}
        actions={
          can("appointments.create") ? (
            <Button
              onClick={() => {
                setEditing(null);
                setFormOpen(true);
              }}
            >
              <CalendarPlus className="size-4" aria-hidden /> New appointment
            </Button>
          ) : null
        }
      />

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <StatCard
          label={t("Total Appointments")}
          value={stats.total ?? query.data?.total ?? 0}
          icon={Calendar}
          tone="brand"
          loading={query.isLoading}
        />
        <StatCard
          label={t("Today")}
          value={stats.today ?? 0}
          icon={CalendarClock}
          tone="blue"
          loading={query.isLoading}
        />
        <StatCard
          label={t("This Month")}
          value={stats.this_month ?? 0}
          icon={CalendarPlus}
          tone="green"
          loading={query.isLoading}
        />
        <StatCard
          label={t("Scheduled")}
          value={stats.scheduled ?? 0}
          icon={Clock}
          tone="amber"
          loading={query.isLoading}
        />
        <StatCard
          label={t("Completed")}
          value={stats.completed ?? 0}
          icon={CheckCircle2}
          tone="green"
          loading={query.isLoading}
        />
      </div>

      <Card className="px-0 pb-0">
        <div className="px-5 pt-4">
          <div className="flex flex-wrap items-end gap-3">
            <Field
              label={t("Date")}
              htmlFor="appointment-date"
              className="space-y-1"
            >
              <Input
                id="appointment-date"
                type="date"
                value={date}
                onChange={(event) => updateDate(event.target.value)}
                className="w-44"
              />
            </Field>
            {date ? (
              <button
                type="button"
                onClick={() => updateDate("")}
                className="mb-0.5 inline-flex h-10 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
              >
                <X className="size-4" aria-hidden /> Clear date
              </button>
            ) : null}
          </div>
          <Tabs
            value={status}
            onChange={(v) => {
              setStatus(v);
              setPage(1);
            }}
            className="mt-3 border-b-0"
            tabs={[
              { value: "all", label: "All", count: counts.all },
              {
                value: "scheduled",
                label: "Scheduled",
                count: counts.scheduled,
              },
              {
                value: "confirmed",
                label: "Confirmed",
                count: counts.confirmed,
              },
              {
                value: "in_progress",
                label: "In progress",
                count: counts.in_progress,
              },
              {
                value: "completed",
                label: "Completed",
                count: counts.completed,
              },
            ]}
          />
        </div>

        {query.isLoading ? (
          <div className="p-5">
            <RowSkeleton rows={6} cols={5} />
          </div>
        ) : query.isError ? (
          <p className="py-6 text-center text-sm text-red-600">
            {query.error.message}
          </p>
        ) : all.length === 0 ? (
          <EmptyState
            title={
              date ? "No appointments on this date" : "No appointments yet"
            }
            message={
              date
                ? `Nothing is scheduled for ${formatDate(date)}.`
                : "Create an appointment to get started."
            }
            action={
              date ? (
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => updateDate("")}
                >
                  Show all appointments
                </Button>
              ) : can("appointments.create") ? (
                <Button
                  size="sm"
                  onClick={() => {
                    setEditing(null);
                    setFormOpen(true);
                  }}
                >
                  New appointment
                </Button>
              ) : null
            }
          />
        ) : items.length === 0 ? (
          <EmptyState
            title="Nothing here"
            message="No appointments match the selected status."
          />
        ) : (
          <Table minWidth="min-w-[820px]">
            <THead>
              <Tr>
                <Th>Appointment</Th>
                <Th>Patient</Th>
                <Th>Doctor</Th>
                <Th>When</Th>
                <Th>Status</Th>
                <Th>
                  <span className="sr-only">Actions</span>
                </Th>
              </Tr>
            </THead>
            <TBody>
              {items.map((apt) => {
                const status = statusBadgeFor(APPOINTMENT_STATUS, apt.status);
                return (
                  <Tr
                    key={apt.id}
                    onClick={() => navigate(`/appointments/${apt.id}`)}
                  >
                    <Td className="font-mono text-xs text-ink-muted">
                      {apt.appointment_number}
                    </Td>
                    <Td className="font-medium text-ink">
                      {patientFullName(apt.patient)}
                    </Td>
                    <Td>{apt.doctor?.name ?? "—"}</Td>
                    <Td className="text-ink-muted">
                      {formatDate(apt.appointment_date)} ·{" "}
                      {formatTime(apt.appointment_time)}
                    </Td>
                    <Td>
                      <Badge tone={status.tone}>{status.label}</Badge>
                    </Td>
                    <Td onClick={(e) => e.stopPropagation()}>
                      <div className="flex justify-end gap-1">
                        {can("appointments.update") ? (
                          <button
                            type="button"
                            aria-label={`Edit appointment ${apt.appointment_number}`}
                            className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                            onClick={() => {
                              setEditing(apt);
                              setFormOpen(true);
                            }}
                          >
                            <Pencil className="size-4" aria-hidden />
                          </button>
                        ) : null}
                        {can("appointments.delete") ? (
                          <button
                            type="button"
                            aria-label={`Delete appointment ${apt.appointment_number}`}
                            className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                            onClick={() => setDeleting(apt)}
                          >
                            <Trash2 className="size-4" aria-hidden />
                          </button>
                        ) : null}
                      </div>
                    </Td>
                  </Tr>
                );
              })}
            </TBody>
          </Table>
        )}

        <Pagination
          page={query.data?.current_page ?? 1}
          lastPage={query.data?.last_page ?? 1}
          total={query.data?.total}
          onChange={setPage}
        />
      </Card>

      <AppointmentFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        initial={editing}
        onSaved={() => query.refetch()}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete appointment?"
        message={`This will remove appointment ${deleting?.appointment_number ?? ""} permanently.`}
        confirmLabel="Delete"
      />
    </div>
  );
}
