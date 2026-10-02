import { JsonRecord, LiveIdentity } from "../domain/models";
export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
  ) {
    super(message);
  }
}
export function normalizeBase(value: string): string {
  const url = new URL(value.trim());
  if (
    url.protocol !== "https:" &&
    !(
      url.protocol === "http:" &&
      ["localhost", "127.0.0.1", "10.0.2.2"].includes(url.hostname)
    )
  )
    throw new Error("Use HTTPS for your platform URL.");
  if (url.username || url.password || url.search || url.hash)
    throw new Error(
      "Use a platform URL without credentials or query parameters.",
    );
  return url.toString().replace(/\/$/, "");
}
export class MobileApi {
  constructor(
    readonly base: string,
    private token: string,
    private onUnauthorized?: () => void,
  ) {}
  async request<T>(
    endpoint: string,
    method: "GET" | "POST" = "GET",
    body?: JsonRecord,
  ): Promise<T> {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 20000);
    try {
      const res = await fetch(`${this.base}/mobile_api/${endpoint}`, {
        method,
        signal: controller.signal,
        headers: {
          Accept: "application/json",
          Authorization: `Bearer ${this.token}`,
          ...(body ? { "Content-Type": "application/json" } : {}),
        },
        ...(body ? { body: JSON.stringify(body) } : {}),
      });
      let data: { success?: boolean; message?: string; data: T };
      try {
        data = await res.json();
      } catch {
        throw new ApiError(
          res.status,
          "The platform did not return a valid API response.",
        );
      }
      if (res.status === 401) this.onUnauthorized?.();
      if (!res.ok || data.success === false)
        throw new ApiError(
          res.status,
          data.message || "The request could not be completed.",
        );
      return data.data;
    } catch (error) {
      if (error instanceof ApiError) throw error;
      if (error instanceof Error && error.name === "AbortError")
        throw new ApiError(408, "The request timed out. Please retry.");
      throw new ApiError(
        0,
        "Could not reach the platform. Check your connection.",
      );
    } finally {
      clearTimeout(timer);
    }
  }
  me() {
    return this.request<LiveIdentity>("me");
  }
}
