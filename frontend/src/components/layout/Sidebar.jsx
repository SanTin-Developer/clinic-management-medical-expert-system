import { useMemo } from "react";
import { Link, NavLink } from "react-router-dom";
import { Settings } from "lucide-react";
import { cn } from "../../utils/cn.js";
import { APP_NAME } from "../../constants/index.js";
import { NAV_SECTIONS } from "../../constants/navigation.js";
import { useAuth } from "../../hooks/useAuth.js";
import { useChatUnread } from "../../hooks/useChatUnread.js";

function initials(name = "") {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? "")
    .join("");
}

export default function Sidebar({ open, onClose, collapsed = false }) {
  const { user } = useAuth();
  const chatUnread = useChatUnread();
  const isCollapsed = collapsed;

  const sections = useMemo(() => {
    const perms = user?.permissions ?? [];
    const canSee = (item) =>
      item.permissions.length === 0 ||
      item.permissions.some((p) => perms.includes(p));

    return NAV_SECTIONS.map((section) => ({
      ...section,
      items: section.items.filter(canSee),
    })).filter((section) => section.items.length > 0);
  }, [user]);

  const role = user?.roles?.[0] ?? "";
  const showLabels = !(isCollapsed && !open);

  return (
    <>
      {open ? (
        <div
          className="fixed inset-0 z-40 bg-slate-950/50 md:hidden"
          onClick={onClose}
          aria-hidden
        />
      ) : null}
      <aside
        className={cn(
          "fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-brand-darker text-white transition-[width,transform] duration-200 md:static md:translate-x-0 print:hidden dark:border-r dark:border-white/5 dark:bg-gradient-to-b dark:from-[#111f39] dark:to-[#0a1120]",
          open ? "translate-x-0" : "-translate-x-full md:static",
          isCollapsed ? "md:w-[80px]" : "md:w-64",
        )}
        aria-label="Primary navigation"
      >
        <div
          className={cn(
            "flex h-16 items-center gap-2.5 px-4",
            isCollapsed && !open && "md:justify-center md:px-0",
          )}
        >
          <span
            className="grid size-10 shrink-0 select-none place-items-center rounded-xl bg-white shadow-md ring-1 ring-white/30"
            onContextMenu={(event) => event.preventDefault()}
            onCopy={(event) => event.preventDefault()}
            onCut={(event) => event.preventDefault()}
          >
            <img
              src="/royalcare-logo.png"
              alt={APP_NAME}
              draggable={false}
              style={{ WebkitUserDrag: "none", userSelect: "none" }}
              className="pointer-events-none size-7 object-contain"
            />
          </span>
          <div
            className={cn("min-w-0 leading-tight", isCollapsed && "md:hidden")}
          >
            <p className="truncate text-base font-bold tracking-tight text-white">
              {APP_NAME}
            </p>
            <p className="truncate text-[10px] font-semibold uppercase tracking-wider text-blue-200/90">
              Medical &amp; Expert System
            </p>
          </div>
        </div>

        <nav className="flex-1 overflow-y-auto px-3 py-2">
          {sections.map((section, index) => (
            <div
              key={section.id}
              className={cn(
                index > 0 && "mt-5",
                index > 0 &&
                  isCollapsed &&
                  !open &&
                  "md:mt-3 md:border-t md:border-white/10 md:pt-3",
              )}
            >
              <p
                className={cn(
                  "px-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-blue-200/50",
                  isCollapsed && !open && "md:hidden",
                )}
              >
                {section.label}
              </p>
              <div className="space-y-1">
                {section.items.map((item) => (
                  <NavLink
                    key={item.to}
                    to={item.to}
                    end={item.end}
                    onClick={onClose}
                    title={
                      item.comingSoon
                        ? `${item.label} (coming soon)`
                        : item.label
                    }
                    className={({ isActive }) =>
                      cn(
                        "flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors",
                        isActive
                          ? "bg-white text-brand-dark shadow-sm dark:bg-brand dark:text-white"
                          : item.comingSoon
                            ? "text-blue-100/55 hover:bg-white/10 hover:text-white"
                            : "text-blue-100/80 hover:bg-white/10 hover:text-white",
                        isCollapsed && !open ? "md:justify-center md:px-0" : "",
                      )
                    }
                  >
                    <item.icon className="size-5 shrink-0" aria-hidden />
                    <span
                      className={cn("flex-1 truncate", !showLabels && "md:hidden")}
                    >
                      {item.label}
                    </span>
                    {item.to === "/chat" && chatUnread > 0 ? (
                      <span
                        className={cn(
                          "grid min-w-5 place-items-center rounded-full bg-brand-soft px-1.5 text-[10px] font-bold leading-5 text-brand-dark",
                          !showLabels && "md:hidden",
                        )}
                      >
                        {chatUnread > 9 ? "9+" : chatUnread}
                      </span>
                    ) : null}
                    {item.comingSoon ? (
                      <span
                        className={cn(
                          "rounded-full bg-gradient-to-r from-amber-400 to-orange-500 px-1.5 py-0.5 text-[9px] font-bold uppercase leading-none tracking-wide text-white shadow-sm",
                          !showLabels && "md:hidden",
                        )}
                      >
                        Soon
                      </span>
                    ) : null}
                  </NavLink>
                ))}
              </div>
            </div>
          ))}
        </nav>

        <div className="border-t border-white/10 p-3">
          <Link
            to="/settings"
            onClick={onClose}
            title={user?.name ?? "Account settings"}
            className={cn(
              "flex items-center gap-3 rounded-xl bg-white/5 px-3 py-2.5 transition-colors hover:bg-white/10",
              isCollapsed && !open && "md:justify-center md:px-0",
            )}
          >
            <span className="grid size-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand to-brand-dark text-xs font-bold text-white ring-2 ring-white/20">
              {initials(user?.name)}
            </span>
            <span className={cn("min-w-0 flex-1", !showLabels && "md:hidden")}>
              <span className="block truncate text-sm font-semibold text-white">
                {user?.name ?? "Account"}
              </span>
              <span className="block truncate text-[11px] text-blue-200/80">
                {role || "View settings"}
              </span>
            </span>
            <Settings
              className={cn(
                "size-4 shrink-0 text-blue-200/70",
                !showLabels && "md:hidden",
              )}
              aria-hidden
            />
          </Link>
        </div>
      </aside>
    </>
  );
}
