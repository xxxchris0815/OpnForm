import { describe, expect, it } from 'vitest'
import {
  integrationRequiresUpgrade,
  workspaceGrantsIntegration,
} from '../../lib/forms/integrationAvailability.js'

describe('incomplete submission webhook availability', () => {
  it('locks the business webhook for a cloud pro workspace', () => {
    expect(integrationRequiresUpgrade({
      requiredTier: 'business',
      currentTier: 'pro',
      isSelfHosted: false,
      grantedByWorkspace: false,
    })).toBe(true)
  })

  it('unlocks the webhook on self-hosted community without a license', () => {
    expect(integrationRequiresUpgrade({
      requiredTier: 'business',
      currentTier: 'pro',
      isSelfHosted: true,
      grantedByWorkspace: false,
    })).toBe(false)
  })

  it('unlocks the webhook when the workspace already has the feature', () => {
    expect(integrationRequiresUpgrade({
      requiredTier: 'business',
      currentTier: 'free',
      isSelfHosted: false,
      grantedByWorkspace: true,
    })).toBe(false)
  })

  it('treats partial submissions as enough access for the incomplete webhook', () => {
    const can = (feature) => feature === 'partial_submissions'

    expect(workspaceGrantsIntegration(can, 'partial_webhook')).toBe(true)
    expect(workspaceGrantsIntegration(can, 'slack')).toBe(false)
  })
})
