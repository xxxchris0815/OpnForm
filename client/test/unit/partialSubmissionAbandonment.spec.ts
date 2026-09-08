import { describe, expect, it } from 'vitest'
import {
  PARTIAL_SUBMISSION_ABANDONMENT_SHORTCUTS,
  applyPartialSubmissionAbandonmentShortcut,
  setPartialSubmissionAbandonmentEnabled,
  syncPartialSubmissionAbandonmentEnabled
} from '../../lib/forms/partialSubmissionAbandonment.js'

describe('partial submission abandonment settings', () => {
  it('sets a 30-minute default when abandonment is enabled without a timeout', () => {
    const form = {
      partial_submission_abandonment_value: null,
      partial_submission_abandonment_unit: null
    }

    setPartialSubmissionAbandonmentEnabled(form, true)

    expect(form).toMatchObject({
      partial_submission_abandonment_value: 30,
      partial_submission_abandonment_unit: 'minute'
    })
  })

  it('preserves an existing timeout when enabling abandonment', () => {
    const form = {
      partial_submission_abandonment_value: 2,
      partial_submission_abandonment_unit: 'hour'
    }

    setPartialSubmissionAbandonmentEnabled(form, true)

    expect(form).toMatchObject({
      partial_submission_abandonment_value: 2,
      partial_submission_abandonment_unit: 'hour'
    })
  })

  it('clears both persisted fields when abandonment is disabled', () => {
    const form = {
      partial_submission_abandonment_value: 1,
      partial_submission_abandonment_unit: 'day'
    }

    setPartialSubmissionAbandonmentEnabled(form, false)

    expect(form).toMatchObject({
      partial_submission_abandonment_value: null,
      partial_submission_abandonment_unit: null
    })
  })

  it.each(PARTIAL_SUBMISSION_ABANDONMENT_SHORTCUTS)(
    'applies the $label shortcut',
    (shortcut) => {
      const form = {
        partial_submission_abandonment_value: null,
        partial_submission_abandonment_unit: null
      }

      applyPartialSubmissionAbandonmentShortcut(form, shortcut)

      expect(form).toMatchObject({
        partial_submission_abandonment_value: shortcut.value,
        partial_submission_abandonment_unit: shortcut.unit
      })
    }
  )

  it('reflects timeout values restored by undo', () => {
    expect(syncPartialSubmissionAbandonmentEnabled(false, 15, 'minute')).toBe(true)
  })

  it('reflects timeout values cleared by undo', () => {
    expect(syncPartialSubmissionAbandonmentEnabled(true, null, null)).toBe(false)
  })
})
