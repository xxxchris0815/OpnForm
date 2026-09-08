export const PARTIAL_SUBMISSION_ABANDONMENT_UNITS = [
  { name: 'Minutes', value: 'minute' },
  { name: 'Hours', value: 'hour' },
  { name: 'Days', value: 'day' }
]

export const PARTIAL_SUBMISSION_ABANDONMENT_SHORTCUTS = [
  { label: '15 minutes', value: 15, unit: 'minute' },
  { label: '30 minutes', value: 30, unit: 'minute' },
  { label: '1 hour', value: 1, unit: 'hour' },
  { label: '24 hours', value: 1, unit: 'day' }
]

export function setPartialSubmissionAbandonmentEnabled(form, enabled) {
  if (enabled) {
    if (!form.partial_submission_abandonment_value || !form.partial_submission_abandonment_unit) {
      form.partial_submission_abandonment_value = 30
      form.partial_submission_abandonment_unit = 'minute'
    }
    return
  }

  form.partial_submission_abandonment_value = null
  form.partial_submission_abandonment_unit = null
}

export function syncPartialSubmissionAbandonmentEnabled(currentState, value, unit) {
  if (value && unit) {
    return true
  }

  if (value == null && unit == null) {
    return false
  }

  return currentState
}

export function applyPartialSubmissionAbandonmentShortcut(form, shortcut) {
  form.partial_submission_abandonment_value = shortcut.value
  form.partial_submission_abandonment_unit = shortcut.unit
}
