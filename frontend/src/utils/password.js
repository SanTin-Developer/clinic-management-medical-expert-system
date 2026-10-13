export const PASSWORD_MIN_LENGTH = 8

export const PASSWORD_RULES = [
  { key: 'length', label: 'At least 8 characters', test: (value) => value.length >= PASSWORD_MIN_LENGTH },
  { key: 'upper', label: 'An uppercase letter (A–Z)', test: (value) => /[A-Z]/.test(value) },
  { key: 'lower', label: 'A lowercase letter (a–z)', test: (value) => /[a-z]/.test(value) },
  { key: 'number', label: 'A number (0–9)', test: (value) => /\d/.test(value) },
  {
    key: 'symbol',
    label: 'A special character (!@#$%^&*)',
    test: (value) => /[^A-Za-z0-9]/.test(value),
  },
]

export function passwordChecks(value) {
  return PASSWORD_RULES.map((rule) => ({ ...rule, met: rule.test(value) }))
}

export function passwordMeetsAll(value) {
  return PASSWORD_RULES.every((rule) => rule.test(value))
}

export function passwordError(value) {
  if (!value) return 'Password is required.'
  if (!passwordMeetsAll(value)) {
    return 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number and a special character.'
  }
  return null
}
