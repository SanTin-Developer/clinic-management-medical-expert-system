import { useLanguage } from "../../hooks/useLanguage.js";
import LegalDocumentPage from "./LegalDocumentPage.jsx";

export default function TermsOfUsePage() {
  const { t } = useLanguage();
  return (
    <LegalDocumentPage
      slug="terms-of-use"
      documentName={t("Terms of Use")}
      fallbackIntroduction={t(
        "The terms and conditions for using the RoyalCare Clinic website and services.",
      )}
      useStructuredPolicyContent
    />
  );
}
