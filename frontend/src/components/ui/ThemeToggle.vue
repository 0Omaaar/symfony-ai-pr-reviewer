<script setup lang="ts">
import { computed } from "vue";
import { useTheme, type ThemePreference } from "@/composables/useTheme";

interface Props {
  /** `compact` = icon-only pill, `labeled` = larger with section heading */
  variant?: "compact" | "labeled";
}
const props = withDefaults(defineProps<Props>(), { variant: "compact" });

const { preference, resolved, setPreference } = useTheme();

const options: Array<{ value: ThemePreference; label: string; title: string }> = [
  { value: "dark",   label: "Dark",   title: "Dark mode" },
  { value: "system", label: "System", title: "Match system" },
  { value: "light",  label: "Light",  title: "Light mode" },
];

const currentLabel = computed(() =>
  preference.value === "system"
    ? `System (${resolved.value})`
    : preference.value.charAt(0).toUpperCase() + preference.value.slice(1)
);
</script>

<template>
  <div v-if="props.variant === 'labeled'" class="tt-labeled">
    <p class="tt-heading">Appearance</p>
    <div class="tt-group" role="radiogroup" aria-label="Theme preference">
      <button
        v-for="opt in options"
        :key="opt.value"
        type="button"
        role="radio"
        :aria-checked="preference === opt.value"
        :class="['tt-btn', { 'is-active': preference === opt.value }]"
        :title="opt.title"
        @click="setPreference(opt.value)"
      >
        <span class="tt-icon" aria-hidden="true">
          <!-- Moon -->
          <svg v-if="opt.value === 'dark'" viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
          </svg>
          <!-- Monitor -->
          <svg v-else-if="opt.value === 'system'" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="4" width="20" height="13" rx="2"/>
            <path d="M8 21h8M12 17v4"/>
          </svg>
          <!-- Sun -->
          <svg v-else viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="4"/>
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
          </svg>
        </span>
        <span class="tt-label">{{ opt.label }}</span>
      </button>
    </div>
    <p class="tt-current">Currently using: <strong>{{ currentLabel }}</strong></p>
  </div>

  <!-- compact -->
  <div v-else class="tt-group tt-group--compact" role="radiogroup" aria-label="Theme preference">
    <button
      v-for="opt in options"
      :key="opt.value"
      type="button"
      role="radio"
      :aria-checked="preference === opt.value"
      :class="['tt-btn tt-btn--icon', { 'is-active': preference === opt.value }]"
      :title="opt.title"
      @click="setPreference(opt.value)"
    >
      <svg v-if="opt.value === 'dark'" viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
      </svg>
      <svg v-else-if="opt.value === 'system'" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="2" y="4" width="20" height="13" rx="2"/>
        <path d="M8 21h8M12 17v4"/>
      </svg>
      <svg v-else viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="4"/>
        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
      </svg>
      <span class="sr-only">{{ opt.title }}</span>
    </button>
  </div>
</template>

<style scoped>
.tt-heading {
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--text-secondary);
  margin: 0 0 8px;
}

.tt-group {
  display: inline-flex;
  gap: 2px;
  padding: 3px;
  background: var(--bg-elevated);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-full);
}

.tt-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 0;
  background: transparent;
  padding: 6px 12px;
  border-radius: var(--radius-full);
  color: var(--text-secondary);
  font: inherit;
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color var(--transition-base), color var(--transition-base);
}

.tt-btn:hover { color: var(--text-primary); }
.tt-btn.is-active {
  background: var(--accent);
  color: var(--accent-foreground);
}
.tt-btn.is-active:hover { color: var(--accent-foreground); }

.tt-btn--icon {
  padding: 6px;
  width: 28px;
  height: 28px;
  justify-content: center;
}

.tt-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.tt-current {
  margin: 10px 0 0;
  font-size: 0.8125rem;
  color: var(--text-secondary);
}

.tt-current strong {
  color: var(--text-primary);
  font-weight: 600;
}

.sr-only {
  position: absolute;
  width: 1px; height: 1px;
  padding: 0; margin: -1px;
  overflow: hidden;
  clip: rect(0,0,0,0);
  white-space: nowrap;
  border: 0;
}

@media (max-width: 640px) {
  /* On small screens the compact version could be simplified further,
     but the three-way toggle still fits. Keep as-is for now. */
}
</style>
