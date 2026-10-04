import { JsonRecord, LiveIdentity } from "../domain/models";
export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
  ) {
    super(message);
  }
}
/** The platform is always reached over HTTPS. */
export function normalizeBase(value: string): string {
  const url = new URL(value.trim());
  if (url.protocol !== "https:") throw new Error("The platform must use HTTPS.");
  if (url.username || url.password || url.search || url.hash)
    throw new Error("Invalid platform address.");
  return url.toString().replace(/\/$/, "");
}

export type SignInResult =
  | { two_factor_required: true; challenge_type: "email" | "authenticator"; challenge: string; expires_in: number }
  | { two_factor_required: false; token: string; expires_at: string; scopes: string[] };

async function call<T>(
  url: string,
  method: "GET" | "POST",
  token: string | null,
  body?: JsonRecord,
  onUnauthorized?: () => void,
): Promise<T> {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 20000);
  try {
    const res = await fetch(url, {
      method,
      signal: controller.signal,
      headers: {
        Accept: "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(body ? { "Content-Type": "application/json" } : {}),
      },
      ...(body ? { body: JSON.stringify(body) } : {}),
    });
    let data: { success?: boolean; message?: string; data: T };
    try {
      data = await res.json();
    } catch {
      throw new ApiError(res.status, "The platform did not return a valid response.");
    }
    if (res.status === 401) onUnauthorized?.();
    if (!res.ok || data.success === false)
      throw new ApiError(res.status, data.message || "The request could not be completed.");
    return data.data;
  } catch (error) {
    if (error instanceof ApiError) throw error;
    if (error instanceof Error && error.name === "AbortError")
      throw new ApiError(408, "The request timed out. Please retry.");
    throw new ApiError(0, "Could not reach the platform. Check your connection.");
  } finally {
    clearTimeout(timer);
  }
}

/** Email + password sign-in. Returns a session token, or a 2FA challenge. */
export function signIn(base: string, email: string, password: string, device: string) {
  return call<SignInResult>(`${base}/mobile_api/login`, "POST", null, { email, password, device });
}
export function verifyTwoFactor(base: string, challenge: string, code: string, device: string) {
  return call<SignInResult>(`${base}/mobile_api/login_2fa`, "POST", null, { challenge, code, device });
}

export class MobileApi {
  constructor(
    readonly base: string,
    private token: string,
    private onUnauthorized?: () => void,
  ) {}
  request<T>(endpoint: string, method: "GET" | "POST" = "GET", body?: JsonRecord): Promise<T> {
    return call<T>(`${this.base}/mobile_api/${endpoint}`, method, this.token, body, this.onUnauthorized);
  }
  me() {
    return this.request<LiveIdentity>("me");
  }
  /** Revokes this device's session on the server. */
  logout() {
    return call<null>(`${this.base}/mobile_api/logout`, "POST", this.token);
  }
}
