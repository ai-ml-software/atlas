export interface Bi {
  en: string;
  ar: string;
}
export interface Course {
  id: string;
  title: Bi;
  category: Bi;
  description: Bi;
  minutes: number;
  lessons: number;
  progress: number;
  mandatory: boolean;
  image: "lobby" | "training" | "operations";
  /** HTTPS course thumbnail from the platform, when one is published. */
  image_url?: string | null;
}
export interface Document {
  id: string;
  title: Bi;
  category: Bi;
  code: string;
  version: string;
  date: string;
  type: string;
  purpose: Bi;
  steps: Bi[];
  safety: Bi;
}
export interface Entry {
  id: string;
  title: Bi;
  subtitle: Bi;
  status?: Bi;
  destination?: string;
}
export interface LocalPost {
  id: string;
  title: string;
  body: string;
  createdAt: string;
  status: string;
  attachment?: string;
}
export interface Answer {
  question: string;
  text: string;
  sourceId?: string;
  sources?: {
    ref: string;
    title: string;
    version?: string;
    type: string;
    id: string;
  }[];
  time: string;
}
export interface LiveIdentity {
  id: number;
  name: string;
  email: string;
  roles: string[];
  permissions: string[];
  profile: Record<string, unknown> | null;
}
export type JsonRecord = Record<string, unknown>;
export const bi = (en: string, ar: string): Bi => ({ en, ar });
