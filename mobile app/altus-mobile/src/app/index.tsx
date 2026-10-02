import React from "react";
import { Redirect } from "expo-router";
import { useApp } from "../state/AppProvider";
import { StateView } from "../components/ui";
export default function Index() {
  const { ready, state } = useApp();
  if (!ready) return <StateView type="loading" />;
  return <Redirect href={state.mode === "guest" ? "/welcome" : "/home"} />;
}
