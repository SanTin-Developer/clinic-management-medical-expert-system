import { useEffect, useMemo, useRef, useState } from "react";
import { Link, NavLink, useLocation } from "react-router-dom";
import { ChevronDown, Search, Settings, X } from "lucide-react";
import { cn } from "../../utils/cn.js";
import { APP_NAME } from "../../constants/index.js";
import { filterNavigationSections } from "../../constants/navigation.js";
import { useAuth } from "../../hooks/useAuth.js";
import { useChatUnread } from "../../hooks/useChatUnread.js";
import { useLanguage } from "../../hooks/useLanguage.js";

function initials(name = "") {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? "")
    .join("");
}

function isItemActive(item, pathname) {
  if (item.end) return pathname === item.to;
  return pathname === item.to || pathname.startsWith(`${item.to}/`);
}

export default function Sidebar({ open, onClose, collapsed = false }) {
  const { user } = useAuth();
  const { t } = useLanguage();
  const chatUnread = useChatUnread();
  const { pathname } = useLocation();
  const isCollapsed = collapsed;

  const [query, setQuery] = useState("");
  const [toggledGroups, setToggledGroups] = useState({});
  const searchRef = useRef(null);

  const sections = useMemo(
    () =>
      filterNavigationSections(user?.permissions ?? []).map((section) => ({
        ...section,
        label: t(section.label),
        items: section.items.map((item) => ({
          ...item,
          label: t(item.label),
        })),
      })),
    [user, t],
  );

  const search = query.trim().toLowerCase();

  // While searching, show a flat result list across every group.
  const visibleSections = useMemo(() => {
    if (!search) return sections;
    return sections
      .map((section) => ({
        ...section,
        items: section.items.filter(
          (item) =>
            item.label.toLowerCase().includes(search) ||
            item.to.toLowerCase().includes(search),
        ),
      }))
      .filter((section) => section.items.length > 0);
  }, [sections, search]);

  const activeSectionId = useMemo(
    () =>
      sections.find((section) =>
        section.items.some((item) => isItemActive(item, pathname)),
      )?.id ?? null,
    [sections, pathname],
  );

  // The drawer used to remount on every open/close, which gave it a clean
  // search box and a collapsed group list as a side effect. That remount also
  // destroyed the slide animation, so the resets now happen explicitly.
  useEffect(() => {
    if (open) {
      if (window.matchMedia("(max-width: 767px)").matches) {
        searchRef.current?.focus();
      }
      return undefined;
    }
    // Returning the current reference when already clean lets React bail out,
    // so closing a drawer you never typed in costs no extra render.
    setQuery((current) => (current ? "" : current));
    setToggledGroups((current) =>
      Object.keys(current).length ? {} : current,
    );
    return undefined;
  }, [open]);

  // Escape closes the drawer.
  useEffect(() => {
    if (!open) return undefined;

    const onKeyDown = (event) => {
      if (event.key === "Escape") {
        event.stopPropagation();
        onClose?.();
      }
    };

    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, [open, onClose]);

  const isGroupOpen = (section) => {
    if (search) return true;
    if (Object.prototype.hasOwnProperty.call(toggledGroups, section.id)) {
      return toggledGroups[section.id];
    }
    return section.id === activeSectionId;
  };

  // Toggling has to read the *effective* state, not just the stored one: a
  // group opened by default (the active section) has no entry yet, and `!undefined`
  // would leave it open — so the first tap would appear to do nothing.
  const toggleGroup = (sectionId) =>
    setToggledGroups((current) => {
      const nowOpen = Object.prototype.hasOwnProperty.call(current, sectionId)
        ? current[sectionId]
        : sectionId === activeSectionId;
      return { ...current, [sectionId]: !nowOpen };
    });

  const role = user?.roles?.[0] ?? "";
  const showLabels = !(isCollapsed && !open);

  return (
    <>
      {/* The backdrop stays mounted and animates opacity so both opening and
          closing fade; `pointer-events-none` keeps it inert while hidden. */}
      <div
        className={cn(
          "fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-[1px] transition-opacity duration-200 ease-out md:hidden",
          open ? "opacity-100" : "pointer-events-none opacity-0",
        )}
        onClick={open ? onClose : undefined}
        aria-hidden
      />
      <aside
        id="primary-navigation"
        className={cn(
          // Tailwind's translate-x-* sets the standalone `translate` property,
          // not `transform` — transitioning `transform` alone made the drawer
          // snap. `width` is kept for the desktop rail collapse.
          "fixed inset-y-0 left-0 z-50 flex h-[100dvh] w-[min(20rem,86vw)] shrink-0 flex-col bg-brand-darker text-white transition-[width,translate] duration-200 ease-out md:static md:h-screen md:w-64 md:translate-x-0 print:hidden dark:border-r dark:border-white/5 dark:bg-gradient-to-b dark:from-[#111f39] dark:to-[#0a1120]",
          open ? "translate-x-0 shadow-2xl" : "-translate-x-full md:static",
          isCollapsed ? "md:w-[80px]" : "md:w-64",
        )}
        aria-label={t("Primary navigation")}
      >
        <div
          className={cn(
            "flex h-16 shrink-0 items-center gap-2.5 px-4 pt-[env(safe-area-inset-top)]",
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
              className="pointer-events-none size-9 object-contain"
            />
          </span>
          <div
            className={cn(
              "min-w-0 flex-1 leading-tight",
              isCollapsed && "md:hidden",
            )}
          >
            <p className="truncate text-base font-bold tracking-tight text-white">
              {APP_NAME}
            </p>
            <p className="truncate text-[10px] font-semibold uppercase tracking-wider text-blue-200/90">
              {t("Medical & Expert System")}
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label={t("Close navigation")}
            className="grid size-10 shrink-0 place-items-center rounded-lg text-blue-100/80 transition-colors hover:bg-white/10 hover:text-white md:hidden"
          >
            <X className="size-5" aria-hidden />
          </button>
        </div>

        <div className="shrink-0 px-3 pb-2 md:hidden">
          <label className="relative block">
            <span className="sr-only">{t("Search menu")}</span>
            <Search
              className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-blue-200/60"
              aria-hidden
            />
            <input
              ref={searchRef}
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder={t("Search menu…")}
              className="h-10 w-full rounded-xl border border-white/15 bg-white/10 pl-9 pr-9 text-sm text-white placeholder:text-blue-200/60 focus-visible:border-white/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/40"
            />
            {query ? (
              <button
                type="button"
                onClick={() => setQuery("")}
                aria-label={t("Clear search")}
                className="absolute right-1 top-1/2 grid size-8 -translate-y-1/2 place-items-center rounded-lg text-blue-200/70 transition-colors hover:bg-white/10 hover:text-white"
              >
                <X className="size-4" aria-hidden />
              </button>
            ) : null}
          </label>
        </div>

        <nav className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-2">
          {visibleSections.length === 0 ? (
            <p className="px-3 py-6 text-center text-sm text-blue-200/70">
              {t("No menu items match “{query}”.", { query })}
            </p>
          ) : null}
          {visibleSections.map((section, index) => {
            const groupOpen = isGroupOpen(section);
            return (
              <div
                key={section.id}
                className={cn(
                  index > 0 && "mt-1 md:mt-5",
                  index > 0 &&
                    isCollapsed &&
                    !open &&
                    "md:mt-3 md:border-t md:border-white/10 md:pt-3",
                )}
              >
                <button
                  type="button"
                  onClick={() => toggleGroup(section.id)}
                  aria-expanded={groupOpen}
                  aria-controls={`nav-group-${section.id}`}
                  className={cn(
                    "flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-blue-200/70 transition-colors hover:bg-white/5 hover:text-blue-100",
                    "md:hidden",
                  )}
                >
                  <span className="truncate">{section.label}</span>
                  <ChevronDown
                    className={cn(
                      "size-4 shrink-0 transition-transform duration-200 ease-out",
                      groupOpen && "rotate-180",
                    )}
                    aria-hidden
                  />
                </button>
                <p
                  className={cn(
                    "hidden px-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-blue-200/50 md:block",
                    isCollapsed && !open && "md:hidden",
                  )}
                >
                  {section.label}
                </p>
                <div
                  id={`nav-group-${section.id}`}
                  className={cn(
                    // Height animates 0fr -> 1fr so the group slides open
                    // instead of popping. `visibility` is in the transition so
                    // the collapsed group also leaves the tab order and the
                    // accessibility tree.
                    "grid transition-[grid-template-rows,visibility] duration-200 ease-out",
                    groupOpen
                      ? "grid-rows-[1fr] visible"
                      : "grid-rows-[0fr] invisible",
                    // The desktop rail always shows every group's links; only
                    // the labels collapse.
                    "md:grid-rows-[1fr] md:visible",
                  )}
                >
                  <div className="space-y-1 overflow-hidden">
                    {section.items.map((item) => (
                    <NavLink
                      key={item.to}
                      to={item.to}
                      end={item.end}
                      onClick={onClose}
                      title={
                        item.comingSoon
                          ? t("{label} (coming soon)", { label: item.label })
                          : item.label
                      }
                      className={({ isActive }) =>
                        cn(
                          "flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors md:min-h-0",
                          isActive
                            ? "bg-white text-brand-dark shadow-sm dark:bg-brand dark:text-white"
                            : item.comingSoon
                              ? "text-blue-100/55 hover:bg-white/10 hover:text-white"
                              : "text-blue-100/80 hover:bg-white/10 hover:text-white",
                          isCollapsed && !open
                            ? "md:justify-center md:px-0"
                            : "",
                        )
                      }
                    >
                      <item.icon className="size-5 shrink-0" aria-hidden />
                      <span
                        className={cn(
                          "flex-1 truncate",
                          !showLabels && "md:hidden",
                        )}
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
                          {t("Soon")}
                        </span>
                      ) : null}
                    </NavLink>
                  ))}
                  </div>
                </div>
              </div>
            );
          })}
        </nav>

        <div className="mt-auto shrink-0 border-t border-white/10 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] md:pb-3">
          <Link
            to="/settings"
            onClick={onClose}
            title={user?.name ?? t("Account settings")}
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
                {user?.name ?? t("Account")}
              </span>
              <span className="block truncate text-[11px] text-blue-200/80">
                {role ? t(role) : t("View settings")}
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
