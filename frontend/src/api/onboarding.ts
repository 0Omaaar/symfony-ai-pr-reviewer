const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";

export type OnboardingStep = {
  id: string;
  label: string;
  isComplete: boolean;
};

export type OnboardingState = {
  steps: OnboardingStep[];
  completedCount: number;
  totalSteps: number;
  isComplete: boolean;
  isDismissed: boolean;
  completedAt: string | null;
  dismissedAt: string | null;
  firstReviewReceivedAt: string | null;
};

export async function getOnboarding(): Promise<{ data: OnboardingState }> {
  const res = await fetch(`${apiBaseUrl}/api/onboarding`, { credentials: "include" });
  if (!res.ok) throw new Error(`Failed to fetch onboarding (${res.status})`);
  return res.json();
}

export async function dismissOnboarding(): Promise<{ data: OnboardingState }> {
  const res = await fetch(`${apiBaseUrl}/api/onboarding/dismiss`, {
    method: "POST",
    credentials: "include",
  });
  if (!res.ok) throw new Error(`Failed to dismiss onboarding (${res.status})`);
  return res.json();
}

export async function completeOnboardingStep(step: string): Promise<{ data: OnboardingState }> {
  const res = await fetch(`${apiBaseUrl}/api/onboarding/complete`, {
    method: "POST",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ step }),
  });
  if (!res.ok) throw new Error(`Failed to complete onboarding step (${res.status})`);
  return res.json();
}
