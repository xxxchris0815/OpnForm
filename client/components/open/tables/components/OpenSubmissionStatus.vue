<template>
  <UBadge
    :label="displayLabel"
    :color="badgeColor"
    variant="subtle"
    class="mx-auto"
    size="sm"
  />
</template>

<script setup>
const props = defineProps({
  value: {
    type: String,
    required: true,
  },
})

const displayLabel = computed(() => {
  if (props.value === 'partial') return 'In Progress'
  if (props.value === 'abandoned') return 'Abandoned'
  return 'Submitted'
})

const badgeColor = computed(() => {
  if (props.value === 'partial') return 'warning'
  if (props.value === 'abandoned') return 'neutral'
  return 'success'
})
</script>
