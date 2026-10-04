// Remote configuration served by GET {base}/api/v1/mobile/config (X-App-Key).
// Edited by administrators at /hkp/admin/mobile. Pure helpers live here so they can be unit tested.

export type FeatureName =
  | "learning"
  | "knowledge"
  | "assessments"
  | "assistant"
  | "qr"
  | "notifications"
  | "team"
  | "performance"
  | "demo";

export interface PlatformRule {
  min_version: string;
  latest_version: string;
  store_url: string;
}
export interface RemoteConfig {
  version: number;
  updated_at: string | null;
  api_base_url: string;
  branding: {
    app_name: string;
    app_name_ar: string;
    primary_color: string;
    accent_color: string;
    background_color: string;
    logo_url: string;
    splash_url: string;
  };
  features: Record<FeatureName, boolean>;
  default_language: "en" | "ar" | "device";
  support: { email: string; phone: string; url: string };
  platforms: { ios: PlatformRule; android: PlatformRule };
  maintenance: { enabled: boolean; message_en: string; message_ar: string };
}
export interface CachedConfig {
  config: RemoteConfig;
  etag: string | null;
  fetchedAt: number;
  serverId: string;
}
export interface ServerSetup {
  base: string;
  appKey: string;
}

/** Bind the public cache to a server and key prefix without storing the key secret. */
export function cacheServerId(setup: ServerSetup): string {
  return `${setup.base.replace(/\/+$/, "")}|${setup.appKey.slice(0, 18)}`;
}
export function cacheMatchesServer(cache: CachedConfig, setup: ServerSetup | null): boolean {
  return !!setup && cache.serverId === cacheServerId(setup) && isRemoteConfig(cache.config);
}


/** Numeric dotted comparison: "1.10.0" > "1.9.9". Missing parts count as 0. */
export function compareVersions(a: string, b: string): number {
  const pa = String(a).split(".").map((n) => parseInt(n, 10) || 0);
  const pb = String(b).split(".").map((n) => parseInt(n, 10) || 0);
  for (let i = 0; i < Math.max(pa.length, pb.length); i++) {
    const d = (pa[i] || 0) - (pb[i] || 0);
    if (d !== 0) return d > 0 ? 1 : -1;
  }
  return 0;
}

export type UpdateStatus = "ok" | "suggested" | "required";
export function updateStatus(
  config: RemoteConfig | null,
  platform: string,
  appVersion: string,
): UpdateStatus {
  const rule =
    config && (platform === "ios" || platform === "android")
      ? config.platforms[platform]
      : null;
  if (!rule) return "ok";
  if (rule.min_version && compareVersions(appVersion, rule.min_version) < 0)
    return "required";
  if (
    rule.latest_version &&
    compareVersions(appVersion, rule.latest_version) < 0
  )
    return "suggested";
  return "ok";
}

/** Disabled only when the admin explicitly turned it off; unknown/missing config keeps everything on. */
export function featureEnabled(
  config: RemoteConfig | null,
  name: FeatureName,
): boolean {
  return !config || config.features?.[name] !== false;
}

/** Screen module (domain/screens.ts) or screen id → admin feature toggle. */
export function featureForScreen(
  module: string,
  id: string,
  kind: string,
): FeatureName | null {
  if (kind === "qr") return "qr";
  if (id.startsWith("notification")) return "notifications";
  if (id === "assistant" || module === "Assistant") return "assistant";
  if (module === "Assessment" || kind === "quiz") return "assessments";
  if (module === "Learning") return "learning";
  if (module === "Knowledge") return "knowledge";
  if (module === "Performance") return "performance";
  if (module === "Management") return "team";
  return null;
}

/** Minimal shape check so a broken server response never replaces a good cache. */
export function isRemoteConfig(value: unknown): value is RemoteConfig {
  const record = (item: unknown): item is Record<string, unknown> => !!item && typeof item === "object" && !Array.isArray(item);
  if (!record(value) || !Number.isInteger(value.version) || Number(value.version) < 0 || typeof value.api_base_url !== "string") return false;
  if (!record(value.branding) || !record(value.features) || !record(value.support) || !record(value.platforms) || !record(value.maintenance)) return false;
  for (const key of ["app_name", "app_name_ar", "primary_color", "accent_color", "background_color", "logo_url", "splash_url"]) {
    if (typeof value.branding[key] !== "string") return false;
  }
  for (const key of ["learning", "knowledge", "assessments", "assistant", "qr", "notifications", "team", "performance", "demo"]) {
    if (typeof value.features[key] !== "boolean") return false;
  }
  for (const key of ["email", "phone", "url"]) if (typeof value.support[key] !== "string") return false;
  for (const platform of ["ios", "android"]) {
    const rule = value.platforms[platform];
    if (!record(rule) || ["min_version", "latest_version", "store_url"].some(key => typeof rule[key] !== "string")) return false;
  }
  return ["en", "ar", "device"].includes(String(value.default_language))
    && typeof value.maintenance.enabled === "boolean"
    && typeof value.maintenance.message_en === "string" && typeof value.maintenance.message_ar === "string";
}

export type FetchResult =
  | { kind: "fresh"; config: RemoteConfig; etag: string | null }
  | { kind: "unchanged" }
  | { kind: "unauthorized" }
  | { kind: "error"; message: string };

export async function fetchRemoteConfig(
  base: string,
  appKey: string,
  etag: string | null,
  fetcher: typeof fetch = fetch,
): Promise<FetchResult> {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 10000);
  try {
    const res = await fetcher(`${base}/api/v1/mobile/config`, {
      method: "GET",
      signal: controller.signal,
      headers: {
        Accept: "application/json",
        "X-App-Key": appKey,
        ...(etag ? { "If-None-Match": etag } : {}),
      },
    });
    if (res.status === 304) return { kind: "unchanged" };
    if (res.status === 401) return { kind: "unauthorized" };
    const body = await res.json().catch(() => null);
    if (!res.ok || !body?.success || !isRemoteConfig(body.data))
      return {
        kind: "error",
        message: body?.message || `Configuration request failed (${res.status}).`,
      };
    return { kind: "fresh", config: body.data, etag: res.headers.get("ETag") };
  } catch {
    return { kind: "error", message: "Could not reach the platform." };
  } finally {
    clearTimeout(timer);
  }
}
