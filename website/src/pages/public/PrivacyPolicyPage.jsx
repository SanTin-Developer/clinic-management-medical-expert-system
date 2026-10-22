import LegalDocumentPage from "./LegalDocumentPage.jsx";

export default function PrivacyPolicyPage() {
  return (
    <LegalDocumentPage
      slug="privacy-policy"
      documentName="Privacy Policy"
      fallbackIntroduction="How RoyalCare Clinic collects, uses, and protects your personal and health information."
      useStructuredPolicyContent
    />
  );
}
