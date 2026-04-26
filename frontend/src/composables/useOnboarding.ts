import { ref, computed, readonly } from "vue";
import {
  getOnboarding,
  dismissOnboarding,
  completeOnboardingStep,
  type OnboardingState,
} from "@/api/onboarding";

const state = ref<OnboardingState | null>(null);
const isExpanded = ref(false);
const isLoading = ref(false);
let pollInterval: ReturnType<typeof setInterval> | null = null;

const isVisible = computed(() => {
  if (!state.value) return false;
  if (state.value.isDismissed) return false;
  if (state.value.isComplete) {
    // Show for 30 days after completion
    if (state.value.completedAt) {
      const completed = new Date(state.value.completedAt);
      const thirtyDaysAgo = new Date();
      thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
      if (completed < thirtyDaysAgo) return false;
    }
  }
  return true;
});

const completedCount = computed(() => state.value?.completedCount ?? 0);
const totalSteps = computed(() => state.value?.totalSteps ?? 5);
const isComplete = computed(() => state.value?.isComplete ?? false);
const steps = computed(() => state.value?.steps ?? []);

async function fetchState() {
  try {
    isLoading.value = true;
    const res = await getOnboarding();
    state.value = res.data;
  } catch {
    // silently fail
  } finally {
    isLoading.value = false;
  }
}

async function markStepComplete(stepId: string) {
  if (!state.value) return;
  // Optimistic update
  const step = state.value.steps.find((s) => s.id === stepId);
  if (step && !step.isComplete) {
    step.isComplete = true;
    state.value.completedCount++;
  }
  try {
    const res = await completeOnboardingStep(stepId);
    state.value = res.data;
  } catch {
    await fetchState(); // revert on failure
  }
}

async function dismiss() {
  try {
    const res = await dismissOnboarding();
    state.value = res.data;
  } catch {
    // silently fail
  }
}

function startPolling() {
  if (pollInterval) return;
  pollInterval = setInterval(() => {
    if (state.value && !state.value.isComplete && !state.value.isDismissed) {
      void fetchState();
    }
  }, 30000);
}

function stopPolling() {
  if (pollInterval) {
    clearInterval(pollInterval);
    pollInterval = null;
  }
}

function toggleExpanded() {
  isExpanded.value = !isExpanded.value;
}

export function useOnboarding() {
  return {
    state: readonly(state),
    isVisible,
    isExpanded,
    isLoading: readonly(isLoading),
    isComplete,
    completedCount,
    totalSteps,
    steps,
    fetchState,
    markStepComplete,
    dismiss,
    startPolling,
    stopPolling,
    toggleExpanded,
  };
}
