export type PullRequest = {
    id: number;
    repoId: number;
    number: number;
    title: string;
    status: "open" | "merged" | "closed";
    headSha: string;
    updatedAt: string;
};