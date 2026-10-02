import { useState, useEffect, useCallback } from "react";
import { useApp } from "../state/AppProvider";
import { ApiError } from "./api";
export function useResource<T>(endpoint: string | undefined) {
  const { api, state, online } = useApp();
  const [data, setData] = useState<T | null>(null),
    [loading, setLoading] = useState(false),
    [error, setError] = useState<ApiError | null>(null),
    [version, setVersion] = useState(0);
  useEffect(() => {
    let active = true;
    const run = async () => {
      await Promise.resolve();
      if (!active) return;
      setData(null);
      setError(null);
      if (!api || state.mode !== "live" || !endpoint) {
        setLoading(false);
        return;
      }
      setLoading(true);
      try {
        const value = await api.request<T>(endpoint);
        if (active) setData(value);
      } catch (e) {
        if (active)
          setError(
            e instanceof ApiError
              ? e
              : new ApiError(500, "Could not load this resource."),
          );
      } finally {
        if (active) setLoading(false);
      }
    };
    void run();
    return () => {
      active = false;
    };
  }, [api, state.mode, endpoint, version, online]);
  return {
    data,
    loading,
    error,
    retry: useCallback(() => setVersion((v) => v + 1), []),
  };
}
