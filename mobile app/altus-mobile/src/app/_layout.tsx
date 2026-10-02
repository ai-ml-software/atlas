import React from "react";
import { Stack } from "expo-router";
import { SafeAreaProvider } from "react-native-safe-area-context";
import { useFonts } from "expo-font";
import { Inter_400Regular } from "@expo-google-fonts/inter";
import { Fraunces_400Regular } from "@expo-google-fonts/fraunces";
import { IBMPlexSansArabic_400Regular } from "@expo-google-fonts/ibm-plex-sans-arabic";
import { AppProvider } from "../state/AppProvider";
import { StateView } from "../components/ui";
export default function Layout() {
  const [loaded, error] = useFonts({
    Body: Inter_400Regular,
    Display: Fraunces_400Regular,
    Arabic: IBMPlexSansArabic_400Regular,
  });
  return (
    <SafeAreaProvider>
      <AppProvider>
        {loaded || error ? (
          <Stack screenOptions={{ headerShown: false, animation: "none" }} />
        ) : (
          <StateView type="loading" />
        )}
      </AppProvider>
    </SafeAreaProvider>
  );
}
