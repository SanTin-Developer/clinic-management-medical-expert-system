import { useLanguage } from "../../hooks/useLanguage.js";
import LegalDocumentPage from "./LegalDocumentPage.jsx";

export default function PrivacyPolicyPage() {
  const { t } = useLanguage();
  return (
    <LegalDocumentPage
      slug="privacy-policy"
      documentName={t("Privacy Policy")}
      fallbackIntroduction={t(
        "How RoyalCare Clinic collects, uses, and protects your personal and health information.",
      )}
      useStructuredPolicyContent
    />
  );
}
