import React from "react";
import {
  ScrollView,
  View,
  Pressable,
  KeyboardAvoidingView,
  Platform,
  RefreshControl,
  useWindowDimensions,
  Linking,
} from "react-native";
import { useLocalSearchParams, router, Redirect } from "expo-router";
import { SafeAreaView } from "react-native-safe-area-context";
import { StatusBar } from "expo-status-bar";
import { useApp } from "../state/AppProvider";
import { byId, publicScreens, ScreenSpec } from "../domain/screens";
import {
  T,
  Logo,
  Row,
  Icon,
  IconButton,
  Button,
  StateView,
  go,
  useTheme,
} from "../components/ui";
import { Intro, Auth, Selection } from "../features/Onboarding";
import {
  Home,
  Courses,
  CourseView,
  Lesson,
  Quiz,
  Result,
  Progress,
} from "../features/Learning";
import { Knowledge, Sop, Assistant } from "../features/Knowledge";
import { Profile, Settings, Dashboard, Form } from "../features/Workspaces";
import { Collection, Detail } from "../features/Collections";
import { QR } from "../features/QR";
const primary = ["home", "learning", "knowledge", "assistant", "profile"];
const liveSupported = new Set([
  "home",
  "learning",
  "catalog",
  "mandatory",
  "assigned",
  "in-progress",
  "completed",
  "overdue",
  "recommended",
  "course-history",
  "course",
  "lesson",
  "quiz",
  "quiz-result",
  "learner-detail",
  "certificate",
  "notification-detail",
  "action-detail",
  "conversation-history",
  "knowledge",
  "search",
  "sop",
  "assistant",
  "progress",
  "competencies",
  "readiness",
  "actions",
  "certificates",
  "notifications",
  "team",
  "gaps",
  "reports",
  "management",
  "profile",
  "settings",
  "notification-settings",
  "language",
  "downloads",
  "download-detail",
  "security",
  "privacy",
  "terms",
  "about",
  "certificate-verify",
  "assign",
]);
export default function Screen() {
  const params = useLocalSearchParams<{ route: string; id?: string }>();
  const key = params.route || "home";
  const spec = byId[key] || byId["not-found"];
  const { state, t, ready, rtl, online, has } = useApp();
  const c = useTheme();
  const { width } = useWindowDimensions();
  if (!ready) return <StateView type="loading" />;
  if (state.mode === "guest" && !publicScreens.has(key))
    return <Redirect href="/sign-in" />;
  const denied =
    spec.roles &&
    (state.mode === "demo"
      ? !spec.roles.includes(state.role)
      : !has(spec.module === "Administration" ? "cms.view" : "learners.view"));
  const root = primary.includes(key);
  const showTabs =
    state.mode !== "guest" && !["Access", "System"].includes(spec.module);
  const title = state.locale === "ar" ? spec.ar : spec.title;
  const page = denied ? (
    <StateView type="restricted" />
  ) : state.mode === "live" &&
    !liveSupported.has(key) &&
    !publicScreens.has(key) ? (
    <StateView detail={t("liveUnavailable")} />
  ) : (
    render(spec, params.id)
  );
  return (
    <SafeAreaView
      edges={["top", "bottom"]}
      // Rows and text mirror from the selected app language, independently of device locale.
      style={{ flex: 1, backgroundColor: c.bg, direction: "ltr" }}
    >
      <StatusBar style={state.dark ? "light" : "dark"} />
      <KeyboardAvoidingView
        behavior={Platform.OS === "ios" ? "padding" : undefined}
        style={{ flex: 1 }}
      >
        <View
          style={{
            flex: 1,
            width: "100%",
            maxWidth: width > 700 ? 700 : 520,
            alignSelf: "center",
          }}
        >
          <View
            style={{
              paddingHorizontal: 23,
              paddingTop: 12,
              paddingBottom: 13,
              borderBottomWidth: 1,
              borderBottomColor: c.border,
            }}
          >
            <Row style={{ justifyContent: "space-between" }}>
              {!root && key !== "welcome" ? (
                <IconButton
                  name={rtl ? "arrow-forward" : "arrow-back"}
                  label={t("back")}
                  onPress={() =>
                    router.canGoBack()
                      ? router.back()
                      : router.replace(
                          state.mode === "guest" ? "/welcome" : "/home",
                        )
                  }
                />
              ) : (
                <Logo light={state.dark} />
              )}
              <View style={{ flex: 1, alignItems: "center" }}>
                {root && (
                  <T variant="label" style={{ fontSize: 8, color: c.muted }}>
                    KNOWLEDGE & PERFORMANCE
                  </T>
                )}
              </View>
              {state.mode !== "guest" ? (
                <IconButton
                  name="notifications-outline"
                  label={t("notifications")}
                  onPress={() => go("notifications")}
                />
              ) : (
                <IconButton
                  name="globe-outline"
                  label={t("language")}
                  onPress={() => go("language")}
                />
              )}
            </Row>
          </View>
          {state.mode !== "guest" && (
            <Row
              style={{
                paddingHorizontal: 24,
                paddingVertical: 8,
                backgroundColor: c.soft,
                gap: 7,
              }}
            >
              <View
                style={{
                  width: 5,
                  height: 5,
                  borderRadius: 3,
                  backgroundColor: c.green,
                }}
              />
              <T
                variant="small"
                style={{ fontSize: 9, color: c.muted, flex: 1 }}
              >
                {state.mode === "demo" ? t("demoNotice") : state.property}
              </T>
              <T variant="label" style={{ fontSize: 7, color: c.muted }}>
                {t(state.mode === "demo" ? "demo" : "live")}
              </T>
            </Row>
          )}
          {!online && (
            <View style={{ padding: 9, backgroundColor: c.soft }}>
              <T variant="small">{t("offline")}</T>
            </View>
          )}
          {!["en", "ar"].includes(state.locale) && (
            <View style={{ padding: 12, backgroundColor: c.soft }}>
              <T variant="small">{t("translationFallback")}</T>
            </View>
          )}
          <ScrollView
            key={key + (params.id || "")}
            contentContainerStyle={{ padding: 23, paddingBottom: 35, gap: 20 }}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}
            refreshControl={
              state.mode === "demo" ? undefined : (
                <RefreshControl
                  refreshing={false}
                  onRefresh={() =>
                    router.replace({
                      pathname: "/[route]",
                      params: {
                        route: key,
                        ...(params.id ? { id: params.id } : {}),
                      },
                    })
                  }
                />
              )
            }
          >
            {!["home", "welcome", "introduction", "splash", "course"].includes(
              key,
            ) && (
              <View style={{ gap: 8 }}>
                <T variant="label" style={{ color: c.accent }}>
                  {state.locale === "ar"
                    ? moduleArabic[spec.module] || spec.module
                    : spec.module.toUpperCase()}
                </T>
                <T variant="display" style={{ fontSize: 33 }}>
                  {title}
                </T>
              </View>
            )}
            {page}
          </ScrollView>
          {showTabs && (
            <Row
              style={{
                paddingTop: 10,
                paddingBottom: 8,
                borderTopWidth: 1,
                borderTopColor: c.border,
                backgroundColor: c.surface,
                gap: 0,
              }}
            >
              {[
                ["home", "home-outline", "home"],
                ["learning", "book-outline", "learn"],
                ["knowledge", "library-outline", "knowledge"],
                ["assistant", "sparkles-outline", "ai"],
                ["profile", "person-outline", "profile"],
              ].map(([id, icon, label]) => (
                <Pressable
                  key={id}
                  accessibilityRole="tab"
                  accessibilityLabel={t(label)}
                  accessibilityState={{ selected: key === id }}
                  onPress={() =>
                    router.replace({
                      pathname: "/[route]",
                      params: { route: id },
                    })
                  }
                  style={{
                    flex: 1,
                    alignItems: "center",
                    gap: 5,
                    minHeight: 48,
                  }}
                >
                  <View
                    style={{
                      paddingHorizontal: 15,
                      paddingVertical: 4,
                      borderRadius: 13,
                      backgroundColor: key === id ? c.soft : "transparent",
                    }}
                  >
                    <Icon
                      name={icon as "home-outline"}
                      color={key === id ? c.accent : c.muted}
                      size={21}
                    />
                  </View>
                  <T
                    variant="small"
                    style={{
                      fontSize: 9,
                      color: key === id ? c.accent : c.muted,
                    }}
                  >
                    {t(label)}
                  </T>
                </Pressable>
              ))}
            </Row>
          )}
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}
const moduleArabic: Record<string, string> = {
  Access: "الوصول",
  Learner: "المتعلّم",
  Learning: "التعلّم",
  Assessment: "التقييم",
  Knowledge: "المعرفة",
  Assistant: "المساعد",
  Performance: "الأداء",
  Personal: "شخصي",
  Collaboration: "التعاون",
  Management: "الإدارة",
  Administration: "إدارة المنصة",
  Support: "الدعم",
  System: "النظام",
};
function render(spec: ScreenSpec, id?: string) {
  switch (spec.kind) {
    case "intro":
      return <Intro spec={spec} />;
    case "auth":
      return <Auth spec={spec} />;
    case "select":
      return <Selection spec={spec} />;
    case "home":
      return <Home />;
    case "courses":
      return <Courses spec={spec} />;
    case "course":
      return <CourseView id={id} />;
    case "lesson":
      return <Lesson id={id} />;
    case "quiz":
      return <Quiz id={id} />;
    case "result":
      return <Result id={id} />;
    case "knowledge":
      return <Knowledge spec={spec} />;
    case "sop":
      return <Sop id={id} />;
    case "assistant":
      return <Assistant />;
    case "progress":
      return <Progress />;
    case "profile":
      return <Profile />;
    case "settings":
      return <Settings spec={spec} />;
    case "dashboard":
      return <Dashboard spec={spec} />;
    case "form":
      return <Form spec={spec} />;
    case "list":
      return <Collection spec={spec} />;
    case "qr":
      return <QR />;
    case "detail":
      return spec.id === "download-detail" ? (
        <Sop id={id} offline />
      ) : (
        <Detail spec={spec} id={id} />
      );
    default:
      return <SystemState spec={spec} />;
  }
}
function SystemState({ spec }: { spec: ScreenSpec }) {
  const { t, state, demo } = useApp();
  if (spec.id === "setup-success")
    return (
      <View style={{ gap: 20, paddingVertical: 30 }}>
        <Icon name="checkmark-circle-outline" size={70} color="#2E7D5A" />
        <T variant="title">{t("success")}</T>
        <Button
          label={t("continue")}
          onPress={() => {
            if (state.mode === "guest") demo();
            router.replace("/home");
          }}
        />
      </View>
    );
  return (
    <View style={{ gap: 20 }}>
      <StateView
        type={
          spec.id === "permission-denied"
            ? "restricted"
            : spec.id === "offline"
              ? "offline"
              : "error"
        }
        detail={t(
          spec.id === "offline" ? "downloadNotice" : "sourceUnavailable",
        )}
      />
      <Button
        label={t(spec.id === "session-expired" ? "signIn" : "continue")}
        onPress={() =>
          router.replace(spec.id === "session-expired" ? "/sign-in" : "/home")
        }
      />
      {spec.id === "update-required" && (
        <Button
          label={t("openWebsite")}
          secondary
          onPress={() => void Linking.openURL(state.base)}
        />
      )}
    </View>
  );
}
