import { PullRequest } from "@/types/pr";

export const mockPRs: PullRequest[] = [
    {
        id: 101,
        repoId: 1,
        number: 12,
        title: "Add OAuth login",
        status: "open",
        headSha: "a1b2c3d",
        updatedAt: "2026-02-11T16:05:00Z",
    },
    {
        id: 102,
        repoId: 1,
        number: 11,
        title: "Fix N+1 in UserRepository",
        status: "merged",
        headSha: "d4e5f6g",
        updatedAt: "2026-02-09T11:10:00Z",
    },
    {
        id: 201,
        repoId: 2,
        number: 3,
        title: "Legacy controller cleanup",
        status: "open",
        headSha: "z9y8x7w",
        updatedAt: "2026-02-10T08:20:00Z",
    },
];
