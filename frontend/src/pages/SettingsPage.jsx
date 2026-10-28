import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { toast } from "sonner";
import { Lock, Monitor, Palette, ShieldCheck, Trash2 } from "lucide-react";
import {
  listDevices,
  revokeDevice,
  revokeAllDevices,
  resendVerificationEmail,
} from "../api/auth.js";
import { useAuth } from "../hooks/useAuth.js";
import { useLanguage } from "../hooks/useLanguage.js";
import PageHeader from "../components/ui/PageHeader.jsx";
import Card from "../components/ui/Card.jsx";
import Badge from "../components/ui/Badge.jsx";
import Button from "../components/ui/Button.jsx";
import ConfirmDialog from "../components/ui/ConfirmDialog.jsx";
import Tabs from "../components/ui/Tabs.jsx";
import { CardSkeleton } from "../components/ui/Skeleton.jsx";
import { EmptyState, ErrorState } from "../components/ui/State.jsx";
import NotificationPreferencesPanel from "../components/communication/NotificationPreferencesPanel.jsx";
import TelegramSettingsPanel from "../components/communication/TelegramSettingsPanel.jsx";
import TwoFactorSettingsPanel from "../components/security/TwoFactorSettingsPanel.jsx";
import PasswordChangePanel from "../components/security/PasswordChangePanel.jsx";
import EmailChangePanel from "../components/security/EmailChangePanel.jsx";
import SecurityActivityPanel from "../components/security/SecurityActivityPanel.jsx";
import AppearanceSettingsPanel from "../components/settings/AppearanceSettingsPanel.jsx";
import ActiveSessionsPanel from "../components/security/ActiveSessionsPanel.jsx";
import LocaleTimezonePanel from "../components/settings/LocaleTimezonePanel.jsx";
import PrivacyDataPanel from "../components/settings/PrivacyDataPanel.jsx";
import ProfileSettingsPanel from "../components/settings/ProfileSettingsPanel.jsx";
import { formatDateTime } from "../utils/format.js";

export default function SettingsPage() {
  const { user, can } = useAuth();
  const { t } = useLanguage();
  const [tab, setTab] = useState(() =>
    user?.must_change_password ? "security" : "profile",
  );
  const [revokingAll, setRevokingAll] = useState(false);
  const [showAllDevices, setShowAllDevices] = useState(false);

  const devices = useQuery({
    queryKey: ["devices"],
    queryFn: listDevices,
    enabled: tab === "security",
  });

  const trustedDevices = devices.data ?? [];
  const otherDeviceCount = trustedDevices.filter(
    (device) => !device.is_current,
  ).length;

  const handleRevokeAll = async () => {
    setRevokingAll(true);
    try {
      await revokeAllDevices();
      toast.success(t("All trusted devices revoked."));
      devices.refetch();
    } catch (err) {
      toast.error(err.message);
    } finally {
      setRevokingAll(false);
    }
  };

  return (
    <div>
      <PageHeader
        title={t("Settings")}
        description={t("Your profile and security preferences.")}
        breadcrumb={[{ label: t("Settings") }]}
      />

      <Tabs
        className="mb-5"
        value={tab}
        onChange={setTab}
        tabs={[
          { value: "profile", label: "Profile" },
          { value: "appearance", label: "Appearance", icon: Palette },
          { value: "security", label: "Security" },
          ...(can("notifications.view") || can("telegram.connect")
            ? [{ value: "communication", label: "Communication" }]
            : []),
          { value: "privacy", label: "Privacy & Data", icon: Lock },
        ]}
      />

      {tab === "profile" ? (
        <div className="space-y-6">
          <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <ProfileSettingsPanel />
            <Card title={t("Security settings")} className="lg:col-span-2">
              <div className="flex items-start gap-3">
                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-soft text-brand">
                  <ShieldCheck className="size-5" aria-hidden />
                </span>
                <div>
                  <p className="text-sm font-medium text-ink">
                    {t("Password, email & two-factor")}
                  </p>
                  <p className="mt-1 text-sm text-ink-muted">
                    {t(
                      "Change your password, update your email address, and manage two-factor authentication and trusted devices from the Security tab.",
                    )}
                  </p>
                  <Button
                    className="mt-3"
                    variant="outline"
                    size="sm"
                    onClick={() => setTab("security")}
                  >
                    {t("Go to Security")}
                  </Button>
                </div>
              </div>
            </Card>
          </div>
          <Card
            title={t("Account details")}
            subtitle={t("Roles, verification and permissions.")}
          >
            <dl className="grid gap-3 sm:grid-cols-2 text-sm">
              <div className="flex items-center justify-between rounded-lg border border-line px-3 py-2">
                <dt className="text-ink-muted">{t("Roles")}</dt>
                <dd className="flex gap-1">
                  {(user?.roles ?? []).map((role) => {
                    const name = typeof role === "string" ? role : role?.name;
                    return name ? (
                      <Badge key={name} tone="brand">
                        {t(name)}
                      </Badge>
                    ) : null;
                  })}
                </dd>
              </div>
              <div className="flex items-center justify-between rounded-lg border border-line px-3 py-2">
                <dt className="text-ink-muted">{t("Email verified")}</dt>
                <dd className="flex items-center gap-2">
                  {user?.email_verified ? (
                    <Badge tone="green">{t("Verified")}</Badge>
                  ) : (
                    <>
                      <Badge tone="amber">{t("Unverified")}</Badge>
                      <button
                        type="button"
                        className="text-xs font-semibold text-brand hover:text-brand-dark"
                        onClick={async () => {
                          try {
                            await resendVerificationEmail();
                            toast.success(t("Verification email sent."));
                          } catch (err) {
                            toast.error(err.message);
                          }
                        }}
                      >
                        {t("Resend link")}
                      </button>
                    </>
                  )}
                </dd>
              </div>
              <div className="flex items-center justify-between rounded-lg border border-line px-3 py-2 sm:col-span-2">
                <dt className="text-ink-muted">{t("Permissions")}</dt>
                <dd className="font-semibold text-ink">
                  {user?.permissions?.length ?? 0}
                </dd>
              </div>
            </dl>
          </Card>
          <LocaleTimezonePanel />
        </div>
      ) : null}

      {tab === "security" ? (
        <div className="space-y-6">
          <PasswordChangePanel />
          <EmailChangePanel />
          <TwoFactorSettingsPanel />
          <ActiveSessionsPanel />
          <Card
            title={t("Trusted devices")}
            subtitle={t(
              "Devices you've allowed to skip email verification for 30 days.",
            )}
            actions={
              trustedDevices.length ? (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setRevokingAll(true)}
                  loading={revokingAll}
                >
                  <Trash2 className="size-4" aria-hidden /> {t("Revoke all")}
                </Button>
              ) : null
            }
          >
            {devices.isLoading ? (
              <CardSkeleton rows={3} />
            ) : devices.isError ? (
              <ErrorState
                message={devices.error.message}
                onRetry={() => devices.refetch()}
              />
            ) : trustedDevices.length === 0 ? (
              <EmptyState
                icon={Monitor}
                title={t("No trusted devices")}
                message={t(
                  "Devices you mark as trusted while verifying your email will appear here.",
                )}
              />
            ) : (
              <div className="space-y-4">
                {/* Summary view */}
                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <span className="font-semibold text-ink">
                        {trustedDevices.length === 1
                          ? t("{count} trusted device", {
                              count: trustedDevices.length,
                            })
                          : t("{count} trusted devices", {
                              count: trustedDevices.length,
                            })}
                      </span>
                      {otherDeviceCount > 0 ? (
                        <span className="text-xs text-ink-muted">
                          {otherDeviceCount === 1
                            ? t("(includes {count} other device)", {
                                count: otherDeviceCount,
                              })
                            : t("(includes {count} other devices)", {
                                count: otherDeviceCount,
                              })}
                        </span>
                      ) : null}
                    </div>
                    {trustedDevices.length > 3 && (
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setShowAllDevices(!showAllDevices)}
                      >
                        {showAllDevices ? t("Show less") : t("Show all")}
                      </Button>
                    )}
                  </div>
                  {showAllDevices || trustedDevices.length <= 3 ? (
                    <ul className="divide-y divide-line">
                      {trustedDevices.map((device) => (
                        <li
                          key={device.id}
                          className="flex items-center gap-3 py-3"
                        >
                          <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-canvas text-ink-muted">
                            <Monitor className="size-5" aria-hidden />
                          </span>
                          <div className="min-w-0 flex-1">
                            <p className="flex items-center gap-2 text-sm font-medium text-ink">
                              <span className="truncate">
                                {device.name ?? t("Trusted device")}
                              </span>
                              {device.is_current ? (
                                <Badge tone="green">{t("This device")}</Badge>
                              ) : null}
                            </p>
                            <p className="text-xs text-ink-muted">
                              {t("{ip} · last used {date}", {
                                ip: device.ip_address ?? t("Unknown IP"),
                                date: formatDateTime(device.last_used_at),
                              })}
                              {device.expires_at
                                ? ` · ${t("expires {date}", { date: formatDateTime(device.expires_at) })}`
                                : ""}
                            </p>
                          </div>
                          {!device.is_current ? (
                            <button
                              type="button"
                              aria-label={t("Revoke {name}", {
                                name: device.name ?? t("Trusted device"),
                              })}
                              className="rounded-lg p-2 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                              onClick={async () => {
                                try {
                                  await revokeDevice(device.id);
                                  toast.success(t("Device revoked."));
                                  devices.refetch();
                                } catch (err) {
                                  toast.error(err.message);
                                }
                              }}
                            >
                              <Trash2 className="size-4" aria-hidden />
                            </button>
                          ) : null}
                        </li>
                      ))}
                    </ul>
                  ) : (
                    <div className="space-y-2">
                      {trustedDevices.slice(0, 3).map((device) => (
                        <div
                          key={device.id}
                          className="flex items-center gap-3 rounded-lg border border-line p-3"
                        >
                          <span className="grid size-8 shrink-0 place-items-center rounded-xl bg-canvas text-ink-muted">
                            <Monitor className="size-4" aria-hidden />
                          </span>
                          <div className="min-w-0 flex-1">
                            <p className="flex items-center gap-2 text-sm font-medium text-ink">
                              <span className="truncate">
                                {device.name ?? t("Trusted device")}
                              </span>
                              {device.is_current ? (
                                <Badge tone="green">{t("This device")}</Badge>
                              ) : null}
                            </p>
                            <p className="text-xs text-ink-muted">
                              {t("{ip} · last used {date}", {
                                ip: device.ip_address ?? t("Unknown IP"),
                                date: formatDateTime(device.last_used_at),
                              })}
                            </p>
                          </div>
                          {!device.is_current ? (
                            <button
                              type="button"
                              aria-label={t("Revoke {name}", {
                                name: device.name ?? t("Trusted device"),
                              })}
                              className="rounded-lg p-2 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                              onClick={async () => {
                                try {
                                  await revokeDevice(device.id);
                                  toast.success(t("Device revoked."));
                                  devices.refetch();
                                } catch (err) {
                                  toast.error(err.message);
                                }
                              }}
                            >
                              <Trash2 className="size-4" aria-hidden />
                            </button>
                          ) : null}
                        </div>
                      ))}
                      {trustedDevices.length > 3 && (
                        <div className="text-center">
                          <p className="text-sm text-ink-muted">
                            {trustedDevices.length - 3 === 1
                              ? t("+ {count} more device", {
                                  count: trustedDevices.length - 3,
                                })
                              : t("+ {count} more devices", {
                                  count: trustedDevices.length - 3,
                                })}
                          </p>
                        </div>
                      )}
                    </div>
                  )}
                </div>
              </div>
            )}
          </Card>
          <SecurityActivityPanel />
        </div>
      ) : null}

      {tab === "appearance" ? (
        <div className="space-y-6">
          <AppearanceSettingsPanel />
        </div>
      ) : null}

      {tab === "communication" ? (
        <div className="space-y-6">
          {can("notifications.view") ? <NotificationPreferencesPanel /> : null}
          {can("telegram.connect") ? <TelegramSettingsPanel /> : null}
        </div>
      ) : null}

      {tab === "privacy" ? (
        <div className="space-y-6">
          <PrivacyDataPanel />
        </div>
      ) : null}

      <ConfirmDialog
        open={revokingAll}
        onClose={() => setRevokingAll(false)}
        onConfirm={handleRevokeAll}
        title={t("Revoke all trusted devices?")}
        message={t(
          "All devices will need email verification again on their next sign-in.",
        )}
        confirmLabel={t("Revoke all")}
        loading={revokingAll}
      />
    </div>
  );
}
