<script setup lang="ts">
interface Props {
  padding?: "sm" | "md" | "lg";
  hoverable?: boolean;
}
withDefaults(defineProps<Props>(), { padding: "md", hoverable: false });
</script>

<template>
  <div :class="['card', `card--p-${padding}`, { 'card--hoverable': hoverable }]">
    <slot />
  </div>
</template>

<style scoped>
.card {
  background: var(--bg-surface);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  transition: background-color var(--transition-base), border-color var(--transition-base), box-shadow var(--transition-base), transform var(--transition-base);
}

.card--p-sm { padding: 12px; }
.card--p-md { padding: 16px; }
.card--p-lg { padding: 24px; }

.card--hoverable {
  cursor: pointer;
}
.card--hoverable:hover {
  border-color: var(--border-strong);
  box-shadow: var(--shadow-md);
}
</style>
