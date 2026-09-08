const TIER_ORDER = {
  free: 0,
  pro: 1,
  business: 2,
  enterprise: 3,
  self_hosted: 4,
}

export function integrationRequiresUpgrade({
  requiredTier = 'free',
  currentTier = 'free',
  isSelfHosted = false,
  grantedByWorkspace = false,
}) {
  if (isSelfHosted || grantedByWorkspace) {
    return false
  }

  const current = TIER_ORDER[currentTier] ?? TIER_ORDER.free
  return current < (TIER_ORDER[requiredTier] ?? TIER_ORDER.free)
}

export function workspaceGrantsIntegration(can, integrationKey) {
  if (can(`integrations.${integrationKey}`)) {
    return true
  }

  return integrationKey === 'partial_webhook'
    && (can('partial_submissions') || can('enable_partial_submissions'))
}
