export type Repository = {
    id: number;
    provider: "github" | "gitlab";
    fullName: string;
    policyPack: string;
    lastReviewAt: string | null;
};