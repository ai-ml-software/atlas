import React, { useState } from "react";
import { View, Image, Platform, Linking, Switch, FlatList } from "react-native";
import * as Notifications from "expo-notifications";
import { router } from "expo-router";
import { useApp } from "../state/AppProvider";
import {
  T,
  Card,
  Button,
  Field,
  Row,
  Logo,
  Search,
  ListRow,
  go,
  useTheme,
  styles,
  Icon,
  Badge,
} from "../components/ui";
import { assets } from "../domain/demo";
import { ScreenSpec, Role } from "../domain/screens";
import languages from "../i18n/languages.json";
import countries from "../i18n/countries.json";

export function Intro({ spec }: { spec: ScreenSpec }) {
  const { t, state } = useApp();
  return (
    <View style={styles.stack}>
      <View style={{ height: 340, borderRadius: 22, overflow: "hidden" }}>
        <Image source={assets.hero} style={styles.heroImage} />
        <View style={styles.heroOverlay} />
        <View style={{ position: "absolute", top: 25, left: 25 }}>
          <Logo light />
        </View>
        <View style={styles.heroText}>
          <T variant="label" style={{ color: "#FFFFFF" }}>
            {t("onboarding")}
          </T>
          <T variant="display" style={{ color: "#FFFFFF", fontSize: 37 }}>
            {state.locale === "ar" ? spec.ar : spec.title}
          </T>
        </View>
      </View>
      <T style={{ fontSize: 17 }}>
        {t(spec.id === "introduction" ? "introBody" : "welcomeBody")}
      </T>
      <Row>
        <Icon name="book-outline" />
        <T>{t("learn")}</T>
        <Icon name="shield-checkmark-outline" />
        <T>{t("knowledge")}</T>
        <Icon name="trending-up-outline" />
        <T>{t("progress")}</T>
      </Row>
      <Button
        label={t("continue")}
        onPress={() => go(spec.next || "language")}
        icon="arrow-forward"
      />
      <Button
        label={t("exploreDemo")}
        secondary
        onPress={() => {
          go("sign-in");
        }}
      />
    </View>
  );
}
export function Auth({ spec }: { spec: ScreenSpec }) {
  const { t, state, connect } = useApp();
  const [base, setBase] = useState(state.base),
    [key, setKey] = useState(""),
    [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  const c = useTheme();
  const submit = async () => {
    if (!base || !key) {
      setError(t("invalid"));
      return;
    }
    setBusy(true);
    setError("");
    try {
      await connect(base, key);
      router.replace("/home");
    } catch (e) {
      setError(e instanceof Error ? e.message : t("apiFailure"));
    } finally {
      setBusy(false);
    }
  };
  const accountPath =
    spec.id === "forgot-password"
      ? "login/forgot_password_request"
      : spec.id === "reset-password"
        ? "login/forgot_password_request"
        : spec.id === "otp"
          ? "login/two_factor"
          : spec.id === "security"
            ? "account_security"
            : "login";
  return (
    <View style={styles.stack}>
      <Card>
        <Icon name="lock-closed-outline" size={30} color={c.accent} />
        <T variant="title">
          {t(spec.id === "sign-in" ? "secureLogin" : "securityNote")}
        </T>
        <T style={{ color: c.muted }}>{t("demoScope")}</T>
      </Card>
      {spec.id === "sign-in" && (
        <>
          <Field
            label={t("apiUrl")}
            value={base}
            onChangeText={setBase}
            keyboardType="url"
            autoCapitalize="none"
          />
          <Field
            label={t("apiKey")}
            value={key}
            onChangeText={setKey}
            secureTextEntry
            autoCapitalize="none"
            autoCorrect={false}
          />
          {!!error && <T style={{ color: c.accent }}>{error}</T>}
          <Button label={t("signIn")} onPress={submit} busy={busy} />
          <Button
            label={t("exploreDemo")}
            secondary
            onPress={() => {
              go("first-setup");
            }}
          />
        </>
      )}
      <Button
        label={t("openAccount")}
        secondary
        onPress={() => void Linking.openURL(`${state.base}/${accountPath}`)}
      />
      {spec.id === "sign-in" && (
        <ListRow
          title={
            state.locale === "ar"
              ? "نسيت كلمة المرور؟"
              : "Forgot your password?"
          }
          onPress={() => go("forgot-password")}
        />
      )}
      <T variant="small" style={{ color: c.muted }}>
        {t("securityNote")}
      </T>
    </View>
  );
}
export function LocalePicker({ country = false }: { country?: boolean }) {
  const { state, patch, t } = useApp();
  const [query, setQuery] = useState("");
  const list = (country ? countries : languages).filter((x) =>
    (x.name + " " + x.code).toLowerCase().includes(query.toLowerCase()),
  );
  return (
    <View style={styles.stack}>
      <T>{t("fullCatalog")}</T>
      {!country && <Badge label={t("activeLanguages")} green />}
      <Search value={query} onChange={setQuery} />
      <FlatList
        data={list}
        scrollEnabled={false}
        initialNumToRender={12}
        keyExtractor={(x) => x.code}
        renderItem={({ item }) => (
          <ListRow
            title={`${item.name} · ${item.code.toUpperCase()}`}
            subtitle={
              !country && !["en", "ar"].includes(item.code)
                ? t("translationFallback")
                : undefined
            }
            onPress={() =>
              patch(country ? { country: item.code } : { locale: item.code })
            }
            trailing={
              (country ? state.country : state.locale) === item.code ? (
                <Icon name="checkmark-circle" />
              ) : undefined
            }
          />
        )}
      />
    </View>
  );
}
export function Selection({ spec }: { spec: ScreenSpec }) {
  const { state, patch, demo, t, identity, notify } = useApp();
  const [accepted, setAccepted] = useState(state.consent);
  if (spec.id === "language")
    return (
      <View style={styles.stack}>
        <Row>
          <Button label="English" onPress={() => patch({ locale: "en" })} />
          <Button label="العربية" onPress={() => patch({ locale: "ar" })} />
        </Row>
        <LocalePicker />
        <Button
          label={t("continue")}
          onPress={() =>
            state.mode === "guest" ? go("sign-in") : router.back()
          }
        />
      </View>
    );
  if (spec.id === "organization" || spec.id === "property")
    return (
      <View style={styles.stack}>
        <Image
          source={assets.hero}
          style={{ height: 170, width: "100%", borderRadius: 18 }}
        />
        <Card>
          <Badge label={state.mode === "live" ? t("live") : t("demo")} />
          <T variant="title">
            {spec.id === "organization" ? "ALTUS Gulf" : state.property}
          </T>
          <T>
            {state.mode === "live" ? t("propertyIdentity") : t("demoNotice")}
          </T>
        </Card>
        <Button label={t("continue")} onPress={() => go(spec.next || "home")} />
      </View>
    );
  if (spec.id === "role")
    return (
      <View style={styles.stack}>
        <T>{t("chooseRole")}</T>
        {(state.mode === "live"
          ? ([state.role] as Role[])
          : ([
              "learner",
              "supervisor",
              "manager",
              "instructor",
              "admin",
            ] as Role[])
        ).map((role) => (
          <ListRow
            key={role}
            title={t(role === "admin" ? "platformAdmin" : role)}
            subtitle={
              state.mode === "live"
                ? identity?.roles.join(", ")
                : t("demoNotice")
            }
            onPress={() => {
              if (state.mode !== "live") {
                demo();
                patch({ role });
              }
              go(spec.next || "home");
            }}
            trailing={
              <Icon
                name={
                  state.role === role ? "checkmark-circle" : "chevron-forward"
                }
              />
            }
          />
        ))}
      </View>
    );
  if (spec.id === "consent")
    return (
      <View style={styles.stack}>
        <T>{t("consentBody")}</T>
        <ListRow title={t("privacy")} onPress={() => go("privacy")} />
        <ListRow title={t("terms")} onPress={() => go("terms")} />
        <Row>
          <Switch value={accepted} onValueChange={setAccepted} />
          <T style={{ flex: 1 }}>{t("consent")}</T>
        </Row>
        <Button
          label={t("accept")}
          onPress={() => {
            if (!accepted) {
              notify("termsRequired");
              return;
            }
            patch({ consent: true });
            go(spec.next || "home");
          }}
        />
      </View>
    );
  return (
    <View style={styles.stack}>
      <Card>
        <Icon name="notifications-outline" size={40} />
        <T>{t("notificationsBrowser")}</T>
      </Card>
      <Button
        label={t("allowNotifications")}
        onPress={async () => {
          if (Platform.OS !== "web") {
            const r = await Notifications.requestPermissionsAsync();
            if (!r.granted) notify("notificationDenied");
          }
          go("setup-success");
        }}
      />
      <Button label={t("skip")} secondary onPress={() => go("setup-success")} />
    </View>
  );
}
