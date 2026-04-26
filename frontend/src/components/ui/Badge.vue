<script setup lang="ts">
export type BadgeVariant =
  | "open" | "draft" | "approved" | "changes" | "review" | "merged" | "closed" | "stale"
  | "ci-pass" | "ci-fail" | "ci-pending"
  | "ai-clean" | "ai-issues" | "ai-pending"
  | "success" | "warning" | "error" | "info";

interface Props {
  variant?: BadgeVariant;
  dot?: boolean;
}
withDefaults(defineProps<Props>(), { variant: "open", dot: false });
</script>

<template>
  <span :class="['badge', `badge--${variant}`]">
    <span v-if="dot" class="badge__dot" aria-hidden="true" />
    <slot />
  </span>
</template>

<style scoped>
.badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 2px 8px;
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  border-radius: var(--radius-full);
  line-height: 1.4;
  white-space: nowrap;
}

.badge__dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.badge--open       { background: var(--badge-open-bg);       color: var(--badge-open-text); }
.badge--draft      { background: var(--badge-draft-bg);      color: var(--badge-draft-text); }
.badge--approved   { background: var(--badge-approved-bg);   color: var(--badge-approved-text); }
.badge--changes    { background: var(--badge-changes-bg);    color: var(--badge-changes-text); }
.badge--review     { background: var(--badge-review-bg);     color: var(--badge-review-text); }
.badge--merged     { background: var(--badge-merged-bg);     color: var(--badge-merged-text); }
.badge--closed     { background: var(--badge-closed-bg);     color: var(--badge-closed-text); }
.badge--stale      { background: var(--badge-stale-bg);      color: var(--badge-stale-text); }
.badge--ci-pass    { background: var(--badge-ci-pass-bg);    color: var(--badge-ci-pass-text); }
.badge--ci-fail    { background: var(--badge-ci-fail-bg);    color: var(--badge-ci-fail-text); }
.badge--ci-pending { background: var(--badge-ci-pending-bg); color: var(--badge-ci-pending-text); }
.badge--ai-clean   { background: var(--badge-ai-clean-bg);   color: var(--badge-ai-clean-text); }
.badge--ai-issues  { background: var(--badge-ai-issues-bg);  color: var(--badge-ai-issues-text); }
.badge--ai-pending { background: var(--badge-ai-pending-bg); color: var(--badge-ai-pending-text); }
.badge--success    { background: var(--success-bg);          color: var(--success); }
.badge--warning    { background: var(--warning-bg);          color: var(--warning); }
.badge--error      { background: var(--error-bg);            color: var(--error); }
.badge--info       { background: var(--info-bg);             color: var(--info); }
</style>
