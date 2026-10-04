import React from "react";
import { Redirect } from "expo-router";
import { useApp } from "../state/AppProvider";
import { StateView } from "../components/ui";
/** Launch: splash → (RemoteGate: maintenance / forced update) → home when signed in, else sign-in. */
export default function Index() {
  const { ready, state } = useApp();
  if (!ready) return <StateView type="loading" />;
  return <Redirect href={state.mode === "guest" ? "/sign-in" : "/home"} />;
}
