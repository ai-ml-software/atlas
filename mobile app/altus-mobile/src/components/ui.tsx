import React, { ReactNode } from "react";
import {
  Text as NativeText,
  TextProps,
  View,
  Pressable,
  TextInput,
  TextInputProps,
  StyleSheet,
  Image,
  ActivityIndicator,
  ViewStyle,
  StyleProp,
} from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { useApp } from "../state/AppProvider";
import { assets } from "../domain/demo";
import { Course } from "../domain/models";
import { router } from "expo-router";

export const palette = {
  cream: "#F7F5F1",
  ivory: "#FAF7F2",
  copper: "#C45B2F",
  sand: "#D9C6A3",
  slate: "#5B6775",
  charcoal: "#2A2F35",
  emerald: "#2E7D5A",
};
export function useTheme() {
  const { state } = useApp();
  return {
    bg: state.dark ? "#1C2227" : palette.cream,
    surface: state.dark ? "#293137" : "#FFFFFF",
    text: state.dark ? "#F7F5F1" : palette.charcoal,
    muted: state.dark ? "#BDC4CA" : palette.slate,
    border: state.dark ? "#414A50" : "#E7E1D8",
    soft: state.dark ? "#353C40" : "#F0E9DE",
    accent: state.dark ? "#E08B63" : state.accent,
    green: state.dark ? "#83C6A3" : palette.emerald,
  };
}
export function T({
  variant = "body",
  style,
  ...props
}: TextProps & {
  variant?: "body" | "small" | "label" | "title" | "display" | "number";
}) {
  const { state, rtl } = useApp();
  const c = useTheme();
  const sizes = {
    body: 14,
    small: 12,
    label: 10,
    title: 21,
    display: 34,
    number: 29,
  };
  return (
    <NativeText
      {...props}
      style={[
        {
          color: c.text,
          fontSize: sizes[variant] * (state.largeText ? 1.12 : 1),
          lineHeight:
            sizes[variant] *
            (variant === "display" ? 1.15 : 1.5) *
            (state.largeText ? 1.12 : 1),
          fontFamily:
            state.locale === "ar"
              ? "Arabic"
              : variant === "display"
                ? "Display"
                : "Body",
          fontWeight: variant === "label" ? "600" : "400",
          letterSpacing: variant === "label" && state.locale !== "ar" ? 1.4 : 0,
          textAlign: rtl ? "right" : "left",
          writingDirection: rtl ? "rtl" : "ltr",
        },
        style,
      ]}
    />
  );
}
export function Row({
  children,
  style,
}: {
  children: ReactNode;
  style?: StyleProp<ViewStyle>;
}) {
  const { rtl } = useApp();
  return (
    <View
      style={[
        {
          flexDirection: rtl ? "row-reverse" : "row",
          alignItems: "center",
          gap: 12,
        },
        style,
      ]}
    >
      {children}
    </View>
  );
}
export function Icon({
  name = "arrow-forward",
  size = 20,
  color,
}: {
  name?: React.ComponentProps<typeof Ionicons>["name"];
  size?: number;
  color?: string;
}) {
  const c = useTheme();
  return <Ionicons name={name} size={size} color={color || c.text} />;
}
export function IconButton({
  name,
  onPress,
  label,
}: {
  name: React.ComponentProps<typeof Ionicons>["name"];
  onPress: () => void;
  label: string;
}) {
  const c = useTheme();
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label}
      onPress={onPress}
      style={({ pressed }) => ({
        height: 46,
        width: 46,
        alignItems: "center",
        justifyContent: "center",
        borderRadius: 24,
        borderWidth: 1,
        borderColor: c.border,
        backgroundColor: c.surface,
        opacity: pressed ? 0.7 : 1,
      })}
    >
      <Icon name={name} />
    </Pressable>
  );
}
export function Button({
  label,
  onPress,
  secondary = false,
  disabled = false,
  busy = false,
  icon,
}: {
  label: string;
  onPress: () => void;
  secondary?: boolean;
  disabled?: boolean;
  busy?: boolean;
  icon?: React.ComponentProps<typeof Ionicons>["name"];
}) {
  const c = useTheme();
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label}
      accessibilityState={{ disabled: disabled || busy, busy }}
      onPress={onPress}
      disabled={disabled || busy}
      style={({ pressed }) => ({
        minHeight: 50,
        paddingHorizontal: 19,
        paddingVertical: 13,
        borderRadius: 12,
        backgroundColor: secondary ? c.surface : c.accent,
        borderWidth: secondary ? 1 : 0,
        borderColor: c.border,
        opacity: disabled || busy ? 0.5 : pressed ? 0.8 : 1,
      })}
    >
      <Row style={{ justifyContent: "center", gap: 9 }}>
        {busy ? (
          <ActivityIndicator color={secondary ? c.text : "#FFFFFF"} />
        ) : (
          <>
            {icon && (
              <Icon
                name={icon}
                size={18}
                color={secondary ? c.text : "#FFFFFF"}
              />
            )}
            <T
              style={{
                color: secondary ? c.text : "#FFFFFF",
                fontWeight: "600",
              }}
            >
              {label}
            </T>
          </>
        )}
      </Row>
    </Pressable>
  );
}
export function Card({
  children,
  style,
}: {
  children: ReactNode;
  style?: StyleProp<ViewStyle>;
}) {
  const c = useTheme();
  return (
    <View
      style={[
        {
          padding: 18,
          backgroundColor: c.surface,
          borderWidth: 1,
          borderColor: c.border,
          borderRadius: 17,
          gap: 12,
        },
        style,
      ]}
    >
      {children}
    </View>
  );
}
export function Badge({
  label,
  green = false,
}: {
  label: string;
  green?: boolean;
}) {
  const c = useTheme();
  return (
    <View
      style={{
        alignSelf: "flex-start",
        borderRadius: 6,
        paddingHorizontal: 8,
        paddingVertical: 4,
        backgroundColor: green ? "#E6F1EA" : c.soft,
      }}
    >
      <T
        variant="label"
        style={{
          color: green ? palette.emerald : c.muted,
          fontSize: 9,
          letterSpacing: 0.8,
        }}
      >
        {label}
      </T>
    </View>
  );
}
export function Bar({ value, color }: { value: number; color?: string }) {
  const c = useTheme();
  const { rtl } = useApp();
  return (
    <View
      accessibilityRole="progressbar"
      accessibilityValue={{ min: 0, max: 100, now: value }}
      style={{
        height: 5,
        backgroundColor: c.soft,
        borderRadius: 3,
        overflow: "hidden",
        flexDirection: rtl ? "row-reverse" : "row",
      }}
    >
      <View
        style={{
          height: 5,
          width: `${Math.max(0, Math.min(100, value))}%`,
          backgroundColor: color || c.accent,
        }}
      />
    </View>
  );
}
export function Field({ label, ...props }: TextInputProps & { label: string }) {
  const c = useTheme();
  const { rtl } = useApp();
  return (
    <View style={{ gap: 7 }}>
      <T variant="small" style={{ fontWeight: "600" }}>
        {label}
      </T>
      <TextInput
        accessibilityLabel={label}
        placeholderTextColor={c.muted}
        {...props}
        style={[
          {
            minHeight: 52,
            backgroundColor: c.surface,
            borderWidth: 1,
            borderColor: c.border,
            borderRadius: 12,
            padding: 14,
            color: c.text,
            textAlign: rtl ? "right" : "left",
            fontFamily: "Body",
            fontSize: 14,
          },
          props.style,
        ]}
      />
    </View>
  );
}
export function Search({
  value,
  onChange,
}: {
  value: string;
  onChange: (value: string) => void;
}) {
  const { t } = useApp();
  const c = useTheme();
  return (
    <Row
      style={{
        backgroundColor: c.surface,
        borderWidth: 1,
        borderColor: c.border,
        borderRadius: 12,
        paddingHorizontal: 13,
        minHeight: 50,
      }}
    >
      <Icon name="search-outline" color={c.muted} />
      <TextInput
        accessibilityLabel={t("search")}
        placeholder={t("searchPlaceholder")}
        placeholderTextColor={c.muted}
        value={value}
        onChangeText={onChange}
        style={{
          flex: 1,
          minHeight: 48,
          color: c.text,
          fontFamily: "Body",
          fontSize: 13,
        }}
      />
      {!!value && (
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={t("clear")}
          onPress={() => onChange("")}
          style={{ padding: 12 }}
        >
          <Icon name="close-outline" />
        </Pressable>
      )}
    </Row>
  );
}
export function Section({
  title,
  action,
  onPress,
}: {
  title: string;
  action?: string;
  onPress?: () => void;
}) {
  const c = useTheme();
  return (
    <Row style={{ justifyContent: "space-between", marginTop: 5 }}>
      <T variant="title" style={{ flex: 1, fontSize: 19 }}>
        {title}
      </T>
      {action && onPress && (
        <Pressable
          onPress={onPress}
          accessibilityRole="button"
          style={{ minHeight: 44, justifyContent: "center" }}
        >
          <T variant="small" style={{ color: c.accent, fontWeight: "600" }}>
            {action}
          </T>
        </Pressable>
      )}
    </Row>
  );
}
export function ListRow({
  title,
  subtitle,
  onPress,
  icon = "chevron-forward",
  trailing,
}: {
  title: string;
  subtitle?: string;
  onPress?: () => void;
  icon?: React.ComponentProps<typeof Ionicons>["name"];
  trailing?: ReactNode;
}) {
  const c = useTheme();
  return (
    <Pressable
      onPress={onPress}
      disabled={!onPress}
      accessibilityRole={onPress ? "button" : undefined}
      style={({ pressed }) => ({
        minHeight: 68,
        paddingVertical: 13,
        opacity: pressed ? 0.65 : 1,
        borderBottomWidth: 1,
        borderBottomColor: c.border,
      })}
    >
      <Row>
        <View style={{ flex: 1, gap: 3 }}>
          <T style={{ fontWeight: "600" }}>{title}</T>
          {subtitle && (
            <T variant="small" style={{ color: c.muted }}>
              {subtitle}
            </T>
          )}
        </View>
        {trailing ||
          (onPress && <Icon name={icon} size={17} color={c.muted} />)}
      </Row>
    </Pressable>
  );
}
export function StateView({
  type = "empty",
  detail,
  onRetry,
}: {
  type?: "empty" | "error" | "loading" | "restricted" | "offline";
  detail?: string;
  onRetry?: () => void;
}) {
  const { t } = useApp();
  const c = useTheme();
  return (
    <View style={{ padding: 24, gap: 16, alignItems: "center" }}>
      {type === "loading" ? (
        <ActivityIndicator color={c.accent} />
      ) : (
        <Icon
          name={
            type === "restricted"
              ? "lock-closed-outline"
              : type === "error"
                ? "refresh-outline"
                : type === "offline"
                  ? "cloud-offline-outline"
                  : "file-tray-outline"
          }
          size={34}
          color={c.muted}
        />
      )}
      <T variant="title">
        {t(
          type === "empty"
            ? "empty"
            : type === "loading"
              ? "loading"
              : type === "restricted"
                ? "restricted"
                : type === "offline"
                  ? "offline"
                  : "error",
        )}
      </T>
      <T style={{ color: c.muted, textAlign: "center" }}>
        {detail || t("emptyBody")}
      </T>
      {onRetry && <Button label={t("retry")} onPress={onRetry} />}
    </View>
  );
}
export function Logo({ light = false }: { light?: boolean }) {
  return (
    <Image
      source={light ? assets.logoLight : assets.logo}
      accessibilityLabel="ALTUS Gulf"
      style={{ width: 117, height: 38, resizeMode: "contain" }}
    />
  );
}
export function CourseCard({
  course,
  compact = false,
}: {
  course: Course;
  compact?: boolean;
}) {
  const { b, t } = useApp();
  const c = useTheme();
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={b(course.title)}
      onPress={() => go("course", course.id)}
      style={({ pressed }) => ({
        backgroundColor: c.surface,
        borderRadius: 17,
        borderWidth: 1,
        borderColor: c.border,
        overflow: "hidden",
        opacity: pressed ? 0.8 : 1,
      })}
    >
      {!compact && (
        <Image
          source={assets[course.image]}
          style={{ width: "100%", height: 146 }}
        />
      )}
      <View style={{ padding: 16, gap: 9 }}>
        <Row style={{ justifyContent: "space-between" }}>
          <T variant="label" style={{ color: c.muted, flex: 1 }}>
            {b(course.category)}
          </T>
          {course.mandatory && <Badge label={t("required")} />}
        </Row>
        <T variant="title" style={{ fontSize: 20 }}>
          {b(course.title)}
        </T>
        <Row>
          <T variant="small" style={{ color: c.muted }}>
            {course.minutes} {t("minutes")} · {course.lessons} {t("lessons")}
          </T>
          <T variant="small" style={{ marginLeft: "auto", color: c.accent }}>
            {course.progress}%
          </T>
        </Row>
        <Bar value={course.progress} />
      </View>
    </Pressable>
  );
}
export function go(route: string, id?: string) {
  router.push({
    pathname: "/[route]",
    params: { route, ...(id ? { id } : {}) },
  });
}
export const styles = StyleSheet.create({
  stack: { gap: 17 },
  grid: { flexDirection: "row", flexWrap: "wrap", gap: 10 },
  divider: { height: 1, backgroundColor: "#E7E1D8" },
  hero: { height: 250, borderRadius: 19, overflow: "hidden" },
  heroImage: { width: "100%", height: "100%" },
  heroOverlay: {
    position: "absolute",
    top: 0,
    right: 0,
    bottom: 0,
    left: 0,
    backgroundColor: "rgba(20,27,28,0.35)",
  },
  heroText: { position: "absolute", left: 21, right: 21, bottom: 22, gap: 10 },
});
