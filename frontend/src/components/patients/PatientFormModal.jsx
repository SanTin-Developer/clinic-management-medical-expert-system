import { useState } from "react";
import { toast } from "sonner";
import Modal from "../ui/Modal.jsx";
import Button from "../ui/Button.jsx";
import Field from "../ui/Field.jsx";
import { useLanguage } from "../../hooks/useLanguage.js";
import Input from "../ui/Input.jsx";
import Select from "../ui/Select.jsx";
import Textarea from "../ui/Textarea.jsx";
import { patientApi } from "../../api/patients.js";
import { GENDER_OPTIONS, BLOOD_TYPES } from "../../utils/status.js";

const BASE_FORM = {
  first_name: "",
  last_name: "",
  gender: "",
  date_of_birth: "",
  phone_number: "",
  email: "",
  address: "",
  blood_type: "",
  allergies: "",
  emergency_contact_name: "",
  emergency_contact_phone: "",
  notes: "",
};

export default function PatientFormModal({ open, onClose, initial, onSaved }) {
  const editing = Boolean(initial?.id);
  const [form, setForm] = useState(
    initial ? { ...BASE_FORM, ...initial } : BASE_FORM,
  );
  const { t } = useLanguage();
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);

  const today = new Date().toISOString().slice(0, 10);

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }));

  const handleSubmit = async (event) => {
    event.preventDefault();
    const next = {};
    if (!form.first_name.trim()) next.first_name = "Required.";
    if (!form.last_name.trim()) next.last_name = "Required.";
    if (!form.gender) next.gender = "Select a gender.";
    if (!form.date_of_birth) next.date_of_birth = "Required.";
    else if (form.date_of_birth >= today)
      next.date_of_birth = "Must be before today.";
    if (!form.phone_number.trim()) next.phone_number = "Required.";
    if (form.email && !/^\S+@\S+\.\S+$/.test(form.email.trim()))
      next.email = "Invalid email.";
    setErrors(next);
    if (Object.keys(next).length) return;

    setSaving(true);
    try {
      const payload = { ...form };
      for (const key of [
        "email",
        "address",
        "blood_type",
        "allergies",
        "emergency_contact_name",
        "emergency_contact_phone",
        "notes",
      ]) {
        if (!payload[key]) payload[key] = null;
      }
      if (editing) {
        await patientApi.update(initial.id, payload);
        toast.success("Patient updated.");
      } else {
        await patientApi.create(payload);
        toast.success("Patient created.");
      }
      onSaved?.();
      onClose();
    } catch (err) {
      setErrors({ form: err.message });
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={editing ? t("Edit patient") : t("Register new patient")}
      description={t("This information forms the patient's base record.")}
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            {t("Cancel")}
          </Button>
          <Button type="submit" form="patient-form" loading={saving}>
            {editing ? t("Save changes") : t("Create patient")}
          </Button>
        </>
      }
    >
      <form id="patient-form" onSubmit={handleSubmit} noValidate>
        {errors.form ? (
          <div
            role="alert"
            className="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700"
          >
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field
            label={t("First name")}
            htmlFor="p-first"
            required
            error={errors.first_name}
          >
            <Input
              id="p-first"
              value={form.first_name}
              onChange={(e) => set("first_name")(e.target.value)}
              invalid={Boolean(errors.first_name)}
            />
          </Field>
          <Field
            label={t("Last name")}
            htmlFor="p-last"
            required
            error={errors.last_name}
          >
            <Input
              id="p-last"
              value={form.last_name}
              onChange={(e) => set("last_name")(e.target.value)}
              invalid={Boolean(errors.last_name)}
            />
          </Field>
          <Field
            label={t("Gender")}
            htmlFor="p-gender"
            required
            error={errors.gender}
          >
            <Select
              id="p-gender"
              value={form.gender}
              onChange={(e) => set("gender")(e.target.value)}
              invalid={Boolean(errors.gender)}
            >
              <option value="">{t("Select...")}</option>
              {GENDER_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </Select>
          </Field>
          <Field
            label={t("Date of birth")}
            htmlFor="p-dob"
            required
            error={errors.date_of_birth}
          >
            <Input
              id="p-dob"
              type="date"
              max={today}
              value={form.date_of_birth}
              onChange={(e) => set("date_of_birth")(e.target.value)}
              invalid={Boolean(errors.date_of_birth)}
            />
          </Field>
          <Field
            label={t("Phone number")}
            htmlFor="p-phone"
            required
            error={errors.phone_number}
          >
            <Input
              id="p-phone"
              value={form.phone_number}
              onChange={(e) => set("phone_number")(e.target.value)}
              invalid={Boolean(errors.phone_number)}
            />
          </Field>
          <Field label={t("Email")} htmlFor="p-email" error={errors.email}>
            <Input
              id="p-email"
              type="email"
              value={form.email}
              onChange={(e) => set("email")(e.target.value)}
              invalid={Boolean(errors.email)}
            />
          </Field>
          <Field label={t("Blood type")} htmlFor="p-blood">
            <Select
              id="p-blood"
              value={form.blood_type}
              onChange={(e) => set("blood_type")(e.target.value)}
            >
              <option value="">{t("Unknow")}</option>
              {BLOOD_TYPES.map((b) => (
                <option key={b} value={b}>
                  {b}
                </option>
              ))}
            </Select>
          </Field>
          <Field label={t("Address")} htmlFor="p-address">
            <Input
              id="p-address"
              value={form.address}
              onChange={(e) => set("address")(e.target.value)}
            />
          </Field>
          <Field label={t("Emergency contact name")} htmlFor="p-ecn">
            <Input
              id="p-ecn"
              value={form.emergency_contact_name}
              onChange={(e) => set("emergency_contact_name")(e.target.value)}
            />
          </Field>
          <Field label={t("Emergency contact phone")} htmlFor="p-ecp">
            <Input
              id="p-ecp"
              value={form.emergency_contact_phone}
              onChange={(e) => set("emergency_contact_phone")(e.target.value)}
            />
          </Field>
          <Field
            label={t("Allergies")}
            htmlFor="p-allergies"
            className="sm:col-span-2"
          >
            <Textarea
              id="p-allergies"
              rows={2}
              value={form.allergies}
              onChange={(e) => set("allergies")(e.target.value)}
              placeholder={t("List any known allergies, or leave blank.")}
            />
          </Field>
          <Field label={t("Notes")} htmlFor="p-notes" className="sm:col-span-2">
            <Textarea
              id="p-notes"
              rows={2}
              value={form.notes}
              onChange={(e) => set("notes")(e.target.value)}
            />
          </Field>
        </div>
      </form>
    </Modal>
  );
}
