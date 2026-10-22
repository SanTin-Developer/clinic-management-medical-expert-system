import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { ChevronRight, Clock3 } from "lucide-react";
import { publicWebsiteApi } from "../../api/website.js";
import { Spinner } from "../../components/ui/Skeleton.jsx";
import { ErrorState } from "../../components/ui/State.jsx";
import { Section } from "../../components/public/PublicUI.jsx";

const instructionPattern =
  /^Edit this page from the Website Management section/i;

const sectionId = (slug, index) => `${slug}-section-${index + 1}`;

function formatUpdated(value) {
  if (!value) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return null;
  return new Intl.DateTimeFormat("en-US", { dateStyle: "long" }).format(date);
}

function isPublicSection(section) {
  const heading = typeof section?.heading === "string" ? section.heading.trim() : "";
  const body = typeof section?.body === "string" ? section.body.trim() : "";
  const hidden = [false, 0, "0", "false"].includes(section?.visible);
  return !hidden && heading && body;
}

function sectionsFromBody(html, fallbackHeading) {
  if (!html) return [];
  if (typeof DOMParser === "undefined") {
    const body = String(html).replace(/<[^>]+>/g, "").trim();
    return body && !instructionPattern.test(body)
      ? [{ heading: fallbackHeading, body }]
      : [];
  }

  const document = new DOMParser().parseFromString(html, "text/html");
  const contentElements = document.querySelectorAll("h2, h3, p, li");
  if (!contentElements.length) {
    const body = document.body.textContent?.trim() ?? "";
    return body && !instructionPattern.test(body)
      ? [{ heading: fallbackHeading, body }]
      : [];
  }
  const sections = [];
  const introductoryText = [];
  let current = null;

  for (const element of contentElements) {
    const text = element.textContent?.trim() ?? "";
    if (!text || instructionPattern.test(text)) continue;

    if (element.matches("h2, h3")) {
      if (current?.body.length) {
        sections.push({ heading: current.heading, body: current.body.join("\n\n") });
      }
      current = { heading: text, body: [] };
    } else if (current) {
      current.body.push(text);
    } else {
      introductoryText.push(text);
    }
  }

  if (current?.body.length) {
    sections.push({ heading: current.heading, body: current.body.join("\n\n") });
  }
  if (introductoryText.length) {
    sections.unshift({
      heading: fallbackHeading,
      body: introductoryText.join("\n\n"),
    });
  }
  return sections;
}

/** Shared public presentation for CMS-managed legal documents. */
export default function LegalDocumentPage({
  slug,
  documentName,
  fallbackIntroduction,
  useStructuredPolicyContent = false,
}) {
  const [activeSection, setActiveSection] = useState("");
  const query = useQuery({
    queryKey: ["website", "page", slug],
    queryFn: () => publicWebsiteApi.getPage(slug),
  });
  const page = query.data?.data;
  const structuredContent = useStructuredPolicyContent
    ? page?.policy_content?.en
    : null;

  const sections = useMemo(() => {
    const saved = (structuredContent?.sections ?? [])
      .filter(isPublicSection)
      .map((section) => ({
        heading: section.heading.trim(),
        body: section.body.trim(),
        order: Number(section.order) || 0,
      }))
      .sort((a, b) => a.order - b.order);
    if (saved.length) return saved;
    return sectionsFromBody(page?.body, "Introduction");
  }, [page?.body, structuredContent?.sections]);

  const hasNavigation = sections.length > 1;

  useEffect(() => {
    if (!hasNavigation) return undefined;
    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries.find((entry) => entry.isIntersecting);
        if (visible) setActiveSection(visible.target.id);
      },
      { rootMargin: "-22% 0px -65% 0px", threshold: 0 },
    );
    const targets = sections
      .map((_, index) => document.getElementById(sectionId(slug, index)))
      .filter(Boolean);
    targets.forEach((target) => observer.observe(target));
    return () => observer.disconnect();
  }, [hasNavigation, sections, slug]);

  if (query.isLoading) {
    return (
      <div className="grid min-h-[60vh] place-items-center">
        <Spinner label={`Loading ${documentName.toLowerCase()}…`} />
      </div>
    );
  }
  if (query.isError || !page) {
    return (
      <Section className="py-16">
        <ErrorState onRetry={query.refetch} />
      </Section>
    );
  }

  const title = structuredContent?.title?.trim() || page.title || documentName;
  const introduction =
    structuredContent?.introduction?.trim() ||
    page.excerpt?.trim() ||
    fallbackIntroduction;
  const updated = formatUpdated(page.updated_at);

  return (
    <main className="animate-fade-in bg-canvas pb-16 pt-28 sm:pt-32">
      <Section reveal={false} className="max-w-6xl px-4 sm:px-6">
        <nav
          aria-label="Breadcrumb"
          className="flex items-center gap-1.5 text-sm text-ink-muted"
        >
          <Link
            to="/"
            className="rounded focus:outline-none focus:ring-2 focus:ring-brand/30 hover:text-azure-700"
          >
            Home
          </Link>
          <ChevronRight className="size-4" aria-hidden />
          <span aria-current="page">{documentName}</span>
        </nav>

        <header className="mt-7 border-b border-line pb-8">
          <div className="max-w-3xl">
            <p className="text-sm font-semibold uppercase tracking-[0.16em] text-azure-700">
              RoyalCare Clinic
            </p>
            <h1 className="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink sm:text-5xl">
              {title}
            </h1>
            <p className="mt-4 text-base leading-8 text-ink-muted sm:text-lg">
              {introduction}
            </p>
            {updated ? (
              <p className="mt-5 inline-flex items-center gap-2 text-sm text-ink-muted">
                <Clock3 className="size-4" aria-hidden />
                Last updated: {updated}
              </p>
            ) : null}
          </div>
        </header>

        <div
          className={`mt-10 ${hasNavigation ? "grid items-start gap-10 lg:grid-cols-[minmax(15rem,17.5rem)_minmax(0,1fr)] lg:gap-12" : ""}`}
        >
          {hasNavigation ? (
            <aside className="min-w-0 lg:sticky lg:top-24">
              <nav
                aria-label={`${documentName} table of contents`}
                className="border-l-2 border-azure-100 pl-5"
              >
                <h2 className="text-sm font-bold uppercase tracking-[0.14em] text-ink">
                  On this page
                </h2>
                <ol className="mt-4 space-y-1.5 text-sm">
                  {sections.map((section, index) => {
                    const id = sectionId(slug, index);
                    return (
                      <li key={`${section.heading}-${index}`}>
                        <a
                          href={`#${id}`}
                          aria-current={
                            activeSection === id ? "location" : undefined
                          }
                          className={`block rounded-lg px-3 py-2 transition-colors focus:outline-none focus:ring-2 focus:ring-brand/30 ${activeSection === id ? "bg-azure-50 font-semibold text-azure-700" : "text-ink-muted hover:bg-canvas hover:text-azure-700"}`}
                        >
                          {index + 1}. {section.heading}
                        </a>
                      </li>
                    );
                  })}
                </ol>
              </nav>
            </aside>
          ) : null}

          <article
            className={`min-w-0 max-w-3xl ${hasNavigation ? "" : "mx-auto w-full"}`}
          >
            {sections.length ? (
              sections.map((section, index) => (
                <section
                  id={sectionId(slug, index)}
                  key={`${section.heading}-${index}`}
                  className="scroll-mt-28 border-b border-line py-9 first:pt-0 last:border-0 last:pb-0"
                >
                  <h2 className="font-display text-2xl font-bold tracking-tight text-ink sm:text-[1.7rem]">
                    {section.heading}
                  </h2>
                  <div className="mt-4 whitespace-pre-line text-base leading-[1.7] text-ink-muted sm:text-[17px]">
                    {section.body}
                  </div>
                </section>
              ))
            ) : (
              <div
                role="status"
                className="rounded-2xl border border-dashed border-line bg-surface px-6 py-10 text-center text-base leading-7 text-ink-muted"
              >
                This {documentName.toLowerCase()} is being updated. Please check
                back soon.
              </div>
            )}
          </article>
        </div>
      </Section>
    </main>
  );
}
