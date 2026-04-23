const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";

export type OwnershipView =
  | "all"
  | "my_authored"
  | "requesting_my_review"
  | "i_approved"
  | "blocked_by_ci"
  | "unowned";

export type PrCommitPreview = {
  sha: string;
  shortSha: string;
  headline: string;
  body: string | null;
  authorLogin: string | null;
  authorName: string | null;
  authorAvatarUrl: string | null;
  committedAt: string | null;
  htmlUrl: string | null;
};

export type PrCheckRunPreview = {
  name: string;
  status: string;
  conclusion: string | null;
  detailsUrl: string | null;
  appName: string | null;
  startedAt: string | null;
  completedAt: string | null;
};

export type PrCheckSummary = {
  total: number;
  passing: number;
  pending: number;
  failing: number;
};

export type PrSnapshot = {
  id: number;
  prNumber: number;
  title: string;
  description: string | null;
  repoFullName: string;
  sourceBranch: string;
  targetBranch: string;
  authorLogin: string;
  authorAvatarUrl: string | null;
  status: string;
  isDraft: boolean;
  reviewStatus: string;
  ciStatus: string | null;
  aiReviewStatus: string;
  aiIssueCount: number;
  aiReviewSummary: string | null;
  commentCount: number;
  changedFiles: number;
  additions: number;
  deletions: number;
  assignedReviewers: { login: string; avatarUrl: string | null }[];
  completedReviews: { login: string; avatarUrl: string | null; state: string }[];
  labels: string[];
  isStale: boolean;
  githubUrl: string;
  openedAt: string;
  lastActivityAt: string;
  isAuthoredByMe: boolean;
  isRequestingMyReview: boolean;
  isApprovedByMe: boolean;
  isBlockedByCi: boolean;
  isUnowned: boolean;
  needsMyAttention: boolean;
  commitCount?: number;
  commits?: PrCommitPreview[];
  checkRuns?: PrCheckRunPreview[];
  checkSummary?: PrCheckSummary;
};

export type DashboardStats = {
  totalOpen: number;
  needsReview: number;
  stale: number;
  aiReviewed: number;
  ciFailing: number;
  myPRs?: number;
  needsMyReview?: number;
  views?: Record<OwnershipView, number>;
};

export type DashboardResponse = {
  data: {
    pullRequests: PrSnapshot[];
    stats: DashboardStats;
    groups: Record<string, PrSnapshot[]> | null;
    pagination: {
      total: number;
      page: number;
      perPage: number;
      totalPages: number;
    };
  };
};

export type ActivityEvent = {
  type: string;
  prNumber: number;
  title: string;
  repoFullName: string;
  authorLogin: string;
  authorAvatarUrl: string | null;
  status: string;
  aiReviewStatus: string;
  occurredAt: string;
};

export async function getTeamDashboard(params: Record<string, string | string[]> = {}): Promise<DashboardResponse> {
  const url = new URL(`${apiBaseUrl}/api/team-dashboard`);
  for (const [key, value] of Object.entries(params)) {
    if (Array.isArray(value)) {
      value.forEach((v) => url.searchParams.append(`${key}[]`, v));
    } else if (value) {
      url.searchParams.set(key, value);
    }
  }

  const res = await fetch(url.toString(), { credentials: "include" });
  if (!res.ok) throw new Error(`Failed to fetch team dashboard (${res.status})`);
  return res.json();
}

export async function getTeamDashboardStats(params: { view?: OwnershipView } = {}): Promise<{ data: DashboardStats }> {
  const url = new URL(`${apiBaseUrl}/api/team-dashboard/stats`);
  if (params.view && params.view !== "all") {
    url.searchParams.set("view", params.view);
  }

  const res = await fetch(url.toString(), { credentials: "include" });
  if (!res.ok) throw new Error(`Failed to fetch stats (${res.status})`);
  return res.json();
}

export async function getTeamDashboardActivity(): Promise<{ data: ActivityEvent[] }> {
  const res = await fetch(`${apiBaseUrl}/api/team-dashboard/activity`, { credentials: "include" });
  if (!res.ok) throw new Error(`Failed to fetch activity (${res.status})`);
  return res.json();
}

export async function getTeamDashboardPrDetail(repoFullName: string, number: number): Promise<{ data: PrSnapshot }> {
  const res = await fetch(
    `${apiBaseUrl}/api/team-dashboard/pr/${encodeURIComponent(repoFullName)}/${number}`,
    { credentials: "include" }
  );
  if (!res.ok) throw new Error(`Failed to fetch PR detail (${res.status})`);
  return res.json();
}

export async function refreshTeamDashboard(): Promise<{ status: string; updatedAt?: string; message?: string }> {
  const res = await fetch(`${apiBaseUrl}/api/team-dashboard/refresh`, {
    method: "POST",
    credentials: "include",
  });
  return res.json();
}
