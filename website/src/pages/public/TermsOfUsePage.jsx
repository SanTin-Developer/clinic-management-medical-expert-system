import LegalDocumentPage from "./LegalDocumentPage.jsx";

export default function TermsOfUsePage() {
  return (
    <LegalDocumentPage
      slug="terms-of-use"
      documentName="Terms of Use"
      fallbackIntroduction="The terms and conditions for using the RoyalCare Clinic website and services."
      useStructuredPolicyContent
    />
  );
}
