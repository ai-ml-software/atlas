import React, { useState } from "react";
import { View, Image, Pressable, Share } from "react-native";
import { useApp } from "../state/AppProvider";
import {
  T,
  Card,
  Button,
  Row,
  Section,
  Bar,
  Badge,
  Search,
  CourseCard,
  ListRow,
  Icon,
  go,
  useTheme,
  styles,
  StateView,
} from "../components/ui";
import { assets, courses, questions } from "../domain/demo";
import { Course, JsonRecord } from "../domain/models";
import { useResource } from "../services/useResource";
import { ScreenSpec } from "../domain/screens";
import { LessonMedia, Media } from "../components/LessonMedia";
import { LiveAssessment, LiveResult } from "./LiveAssessment";

export function Home() {
  const { state, t, b } = useApp();
  const c = useTheme();
  const live = useResource<Course[]>("plan?locale=" + state.locale);
  const demoCourses = courses.map((x) => ({
    ...x,
    progress: state.completed.includes(x.id) ? 100 : x.progress,
  }));
  const list = state.mode === "live" ? live.data || [] : demoCourses;
  const next = list.find((x) => x.progress < 100) || list[0];
  const progress = list.length
    ? Math.round(list.reduce((s, x) => s + x.progress, 0) / list.length)
    : 0;
  return (
    <View style={styles.stack}>
      <Row style={{ justifyContent: "space-between" }}>
        <View>
          <T variant="small" style={{ color: c.muted }}>
            {t("goodMorning")}
          </T>
          <T variant="display" style={{ fontSize: 31 }}>
            {state.name.split(" ")[0]}.
          </T>
        </View>
        <Pressable
          onPress={() => go("profile")}
          accessibilityRole="button"
          accessibilityLabel={t("profile")}
          style={{
            width: 48,
            height: 48,
            borderRadius: 25,
            backgroundColor: c.soft,
            alignItems: "center",
            justifyContent: "center",
          }}
        >
          <T variant="title">{state.name.slice(0, 1)}</T>
        </Pressable>
      </Row>
      <T style={{ color: c.muted, marginTop: -8 }}>{t("greeting")}</T>
      <View style={{ height: 205, borderRadius: 19, overflow: "hidden" }}>
        <Image source={assets.hero} style={styles.heroImage} />
        <View style={styles.heroOverlay} />
        <View style={styles.heroText}>
          <T variant="label" style={{ color: "#F7F5F1" }}>
            PEOPLE. KNOWLEDGE. PERFORMANCE.
          </T>
          <T variant="display" style={{ color: "#FFFFFF", fontSize: 29 }}>
            {state.locale === "ar"
              ? "ضيافة أفضل.\nتبدأ بك."
              : "Better hospitality.\nStarts with you."}
          </T>
        </View>
      </View>
      <Section
        title={t("continueLearning")}
        action={t("viewAll")}
        onPress={() => go("learning")}
      />
      {state.mode === "live" && live.loading ? (
        <StateView type="loading" />
      ) : state.mode === "live" && live.error ? (
        <StateView
          type="error"
          detail={live.error.message}
          onRetry={live.retry}
        />
      ) : next ? (
        <Card>
          <Row>
            <View
              style={{
                width: 4,
                height: 65,
                backgroundColor: c.accent,
                borderRadius: 3,
              }}
            />
            <View style={{ flex: 1, gap: 5 }}>
              <T variant="label" style={{ color: c.accent }}>
                {t("yourNextStep")}
              </T>
              <T variant="title" style={{ fontSize: 21 }}>
                {b(next.title)}
              </T>
              <T variant="small" style={{ color: c.muted }}>
                {next.minutes} {t("minutes")} · {next.lessons} {t("lessons")}
              </T>
            </View>
            <Icon name="play-circle-outline" size={35} color={c.accent} />
          </Row>
          <Bar value={next.progress} />
          <Button
            label={t("resumeCourse")}
            onPress={() => go("course", next.id)}
          />
        </Card>
      ) : (
        <StateView />
      )}
      <Row>
        <Card style={{ flex: 1, padding: 15 }}>
          <T variant="number" style={{ color: c.accent }}>
            {progress}%
          </T>
          <T variant="small">{t("learningPlan")}</T>
        </Card>
        <Card style={{ flex: 1, padding: 15 }}>
          <Icon name="ribbon-outline" color={c.green} />
          <T variant="small">{t("certificates")}</T>
          <Pressable
            onPress={() => go("certificates")}
            accessibilityRole="button"
            style={{ minHeight: 32 }}
          >
            <T variant="small" style={{ color: c.green }}>
              {t("viewAll")} →
            </T>
          </Pressable>
        </Card>
      </Row>
      <Section title={t("quickAccess")} />
      <Row>
        {[
          ["knowledge", "book-outline", "sops"],
          ["assistant", "sparkles-outline", "ai"],
          ["progress", "stats-chart-outline", "progress"],
          ["calendar", "calendar-outline", "calendar"],
        ].map(([id, icon, label]) => (
          <Pressable
            key={id}
            onPress={() => go(id)}
            accessibilityRole="button"
            accessibilityLabel={t(label)}
            style={{
              flex: 1,
              alignItems: "center",
              gap: 9,
              paddingVertical: 14,
            }}
          >
            <View
              style={{
                backgroundColor: c.soft,
                width: 48,
                height: 48,
                borderRadius: 14,
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <Icon name={icon as "book-outline"} color={c.accent} />
            </View>
            <T variant="small" style={{ textAlign: "center", fontSize: 10 }}>
              {t(label)}
            </T>
          </Pressable>
        ))}
      </Row>
      {state.mode === "demo" && (
        <>
          <Section title={t("recommended")} />
          <CourseCard course={demoCourses[3]} compact />
        </>
      )}
      {state.role !== "learner" && (
        <Button
          label={t(
            ["instructor", "admin"].includes(state.role)
              ? "admin"
              : "management",
          )}
          secondary
          onPress={() =>
            go(
              ["instructor", "admin"].includes(state.role)
                ? "admin"
                : "management",
            )
          }
        />
      )}
    </View>
  );
}
export function Courses({ spec }: { spec: ScreenSpec }) {
  const { t, b, state } = useApp();
  const [query, setQuery] = useState(""),
    [filter, setFilter] = useState("all");
  const c = useTheme();
  const resource = useResource<Course[]>(
    `${spec.source === "courses" ? "courses" : "plan"}?locale=${state.locale}`,
  );
  const raw =
    state.mode === "live"
      ? resource.data || []
      : courses.map((x) => ({
          ...x,
          progress: state.completed.includes(x.id) ? 100 : x.progress,
        }));
  const active = ["mandatory", "assigned"].includes(spec.id)
    ? "required"
    : spec.id === "in-progress"
      ? "inProgress"
      : spec.id === "completed"
        ? "completed"
        : filter;
  const list = raw.filter(
    (x) =>
      b(x.title).toLowerCase().includes(query.toLowerCase()) &&
      (active === "all" ||
        (active === "required" && x.mandatory) ||
        (active === "inProgress" && x.progress > 0 && x.progress < 100) ||
        (active === "completed" && x.progress === 100)),
  );
  return (
    <View style={styles.stack}>
      <T style={{ color: c.muted }}>{t("planHours")}</T>
      <Search value={query} onChange={setQuery} />
      <Row style={{ gap: 6, flexWrap: "wrap" }}>
        {["all", "required", "inProgress", "completed"].map((f) => (
          <Pressable
            key={f}
            onPress={() => setFilter(f)}
            accessibilityRole="button"
            accessibilityState={{ selected: filter === f }}
            style={{
              minHeight: 44,
              paddingHorizontal: 11,
              paddingVertical: 11,
              backgroundColor: filter === f ? c.text : c.surface,
              borderRadius: 24,
              borderWidth: 1,
              borderColor: c.border,
            }}
          >
            <T
              variant="small"
              style={{ color: filter === f ? c.bg : c.muted, fontSize: 11 }}
            >
              {t(f)}
            </T>
          </Pressable>
        ))}
      </Row>
      {resource.loading ? (
        <StateView type="loading" />
      ) : resource.error ? (
        <StateView
          type={resource.error.status === 403 ? "restricted" : "error"}
          detail={resource.error.message}
          onRetry={resource.retry}
        />
      ) : list.length ? (
        list.map((course) => <CourseCard key={course.id} course={course} />)
      ) : (
        <StateView />
      )}
      <ListRow title={t("browse")} onPress={() => go("catalog")} />
      <ListRow
        title={
          state.locale === "ar" ? "المجالات المهنية" : "Professional domains"
        }
        onPress={() => go("categories")}
      />
      <ListRow
        title={
          state.locale === "ar"
            ? "مسارات وبرامج التعلّم"
            : "Learning paths & programs"
        }
        onPress={() => go("paths")}
      />
      <ListRow title={t("history")} onPress={() => go("course-history")} />
    </View>
  );
}
type LiveCourse = Course & {
  objectives?: string[];
  owner?: string;
  items: { id: number; title: string; type: string; duration: number }[];
  assessments: { id: number; title: string }[];
};
export function CourseView({ id }: { id?: string }) {
  const { t, b, state, update, notify } = useApp();
  const c = useTheme();
  const live = useResource<LiveCourse>(
    state.mode === "live"
      ? `course?id=${encodeURIComponent(id || "")}&locale=${state.locale}`
      : undefined,
  );
  const course =
    state.mode === "live"
      ? live.data
      : courses.find((x) => x.id === id) || courses[0];
  if (live.loading) return <StateView type="loading" />;
  if (live.error)
    return (
      <StateView
        type="error"
        detail={live.error.message}
        onRetry={live.retry}
      />
    );
  if (!course) return <StateView />;
  const favorite = state.favorites.includes(course.id);
  const items =
    state.mode === "live"
      ? live.data?.items || []
      : Array.from({ length: course.lessons }, (_, i) => ({
          id: i + 1,
          title:
            i === 2
              ? t("lessonTitle")
              : [
                  state.locale === "ar"
                    ? "فهم تجربة الضيف"
                    : "Understand the guest experience",
                  state.locale === "ar"
                    ? "التواصل بثقة"
                    : "Communicate with confidence",
                  t("lessonTitle"),
                  state.locale === "ar"
                    ? "المتابعة والأثر"
                    : "Follow-through & impact",
                ][i % 4],
        }));
  return (
    <View style={styles.stack}>
      <Image
        source={assets[course.image]}
        style={{ width: "100%", height: 215, borderRadius: 19 }}
      />
      <Badge label={b(course.category)} />
      <T variant="display" style={{ fontSize: 30 }}>
        {b(course.title)}
      </T>
      <T style={{ color: c.muted }}>{b(course.description)}</T>
      <Row>
        <Icon name="time-outline" size={17} />
        <T variant="small">
          {course.minutes} {t("minutes")} · {course.lessons} {t("lessons")}
        </T>
      </Row>
      <Bar
        value={state.completed.includes(course.id) ? 100 : course.progress}
      />
      <Button
        label={t(course.progress > 0 ? "resumeCourse" : "startCourse")}
        onPress={() =>
          items.length
            ? go(
                "lesson",
                state.mode === "live" ? String(items[0].id) : course.id,
              )
            : notify("notAvailable")
        }
        icon="play-outline"
      />
      <Button
        label={t(favorite ? "removeFavorite" : "bookmark")}
        secondary
        onPress={() => {
          update((s) => ({
            ...s,
            favorites: favorite
              ? s.favorites.filter((x) => x !== course.id)
              : [...s.favorites, course.id],
          }));
        }}
      />
      {(state.mode === "demo" || !!live.data?.objectives?.length) && <>
        <Section title={t("outcomes")} />
        <T>{state.mode === "demo" ? t("outcome") : live.data?.objectives?.join("\n\n")}</T>
      </>}
      <Section title={t("lessons")} />
      {items.map((item, i) => (
        <ListRow
          key={item.id}
          title={`${String(i + 1).padStart(2, "0")}  ${item.title}`}
          subtitle={
            i === 2 && state.mode === "demo" ? t("knowledgeCheck") : undefined
          }
          onPress={() =>
            go("lesson", state.mode === "live" ? String(item.id) : course.id)
          }
        />
      ))}
      {(state.mode === "demo" || !!live.data?.owner) && <>
        <Section title={t("owner")} />
        <T>{state.mode === "demo" ? t("ownerName") : live.data?.owner}</T>
      </>}
      <ListRow
        title={t("discussion")}
        onPress={() => go("discussion", course.id)}
      />
      <ListRow
        title={t("resources")}
        onPress={() => go("resources", course.id)}
      />
      <ListRow
        title={t("issue")}
        onPress={() => go("report-issue", course.id)}
      />
    </View>
  );
}
export function Lesson({ id }: { id?: string }) {
  const { t, state, update, api, notify } = useApp();
  const c = useTheme();
  const live = useResource<JsonRecord>(
    state.mode === "live"
      ? `lesson?id=${encodeURIComponent(id || "")}&locale=${state.locale}`
      : undefined,
  );
  const [busy, setBusy] = useState(false),
    [showTranscript, setShowTranscript] = useState(false),
    [error, setError] = useState("");
  if (live.loading) return <StateView type="loading" />;
  if (live.error)
    return (
      <StateView
        type="error"
        detail={live.error.message}
        onRetry={live.retry}
      />
    );
  return (
    <View style={styles.stack}>
      <View style={{ height: 190, borderRadius: 17, overflow: "hidden" }}>
        <Image source={assets.lobby} style={styles.heroImage} />
        <View style={styles.heroOverlay} />
        <View style={styles.heroText}>
          <Badge
            label={
              state.mode === "demo"
                ? t("lessonSubtitle")
                : String(live.data?.type || "")
            }
          />
          <T variant="display" style={{ fontSize: 29, color: "#FFFFFF" }}>
            {state.mode === "demo"
              ? t("lessonTitle")
              : String(live.data?.title || "")}
          </T>
        </View>
      </View>
      <Row>
        <Bar value={state.mode === "demo" ? 65 : 0} />
        <T variant="small">{t("progress")}</T>
      </Row>
      <Card>
        <T style={{ fontSize: 17, lineHeight: 29 }}>
          {state.mode === "demo"
            ? t("readLesson")
            : String(live.data?.body || "")}
        </T>
      </Card>
      {state.mode === "demo" && (
        <T variant="small" style={{ color: c.muted }}>
          {t("noVideo")}
        </T>
      )}
      {state.mode === "live" && Array.isArray(live.data?.media) && (
        <LessonMedia
          items={live.data.media as Media[]}
          lessonId={id || ""}
          position={Number(
            (live.data?.progress as JsonRecord)?.last_position_seconds || 0,
          )}
        />
      )}
      {state.mode === "live" && Number(live.data?.assessment_id) > 0 && (
        <Button
          label={t("knowledgeCheck")}
          onPress={() => go("quiz", String(live.data?.assessment_id))}
        />
      )}
      <Section title={t("resources")} />
      <Row>
        <Button
          label={t("transcript")}
          secondary
          onPress={() =>
            state.mode === "live"
              ? setShowTranscript(!showTranscript)
              : go("transcript", id)
          }
        />
        <Button
          label={t("sops")}
          secondary
          onPress={() =>
            state.mode === "live" ? go("knowledge") : go("sop", "complaint")
          }
        />
      </Row>
      {showTranscript && (
        <Card>
          <T>{String(live.data?.transcript || t("notAvailable"))}</T>
        </Card>
      )}
      {!!error && <T style={{ color: c.accent }}>{error}</T>}
      <Button
        label={t(state.mode === "demo" ? "knowledgeCheck" : "acknowledge")}
        busy={busy}
        onPress={async () => {
          if (state.mode === "demo") {
            update((s) => ({
              ...s,
              recent: [
                id || "guest-service",
                ...s.recent.filter((x) => x !== id),
              ].slice(0, 20),
            }));
            go("quiz", id);
          } else if (api) {
            setBusy(true);
            try {
              await api.request("lesson_complete", "POST", { id: Number(id) });
              notify("lessonComplete");
              go("learning");
            } catch (e) {
              setError(e instanceof Error ? e.message : t("apiFailure"));
            } finally {
              setBusy(false);
            }
          }
        }}
      />
      <ListRow title={t("issue")} onPress={() => go("report-issue", id)} />
    </View>
  );
}
export function Quiz({ id }: { id?: string }) {
  const { t, b, state, update } = useApp();
  const [answers, setAnswers] = useState<number[]>([]),
    [index, setIndex] = useState(0),
    [selected, setSelected] = useState<number | null>(null);
  const c = useTheme();
  if (state.mode === "live") return <LiveAssessment id={id} />;
  if (state.quizScores.length >= 3)
    return <StateView type="empty" detail={t("retryLimit")} />;
  const q = questions[index];
  return (
    <View style={styles.stack}>
      <Badge
        label={`${t("questionCount")} ${index + 1} / ${questions.length}`}
      />
      <Bar value={((index + 1) / questions.length) * 100} />
      <T variant="display" style={{ fontSize: 28 }}>
        {b(q.title)}
      </T>
      {q.options.map((option, i) => (
        <Pressable
          key={i}
          onPress={() => setSelected(i)}
          accessibilityRole="radio"
          accessibilityState={{ checked: selected === i }}
          style={{
            backgroundColor: selected === i ? c.soft : c.surface,
            borderWidth: 1,
            borderColor: selected === i ? c.accent : c.border,
            borderRadius: 14,
            padding: 18,
            minHeight: 65,
          }}
        >
          <Row>
            <Icon
              name={selected === i ? "radio-button-on" : "radio-button-off"}
              color={selected === i ? c.accent : c.muted}
            />
            <T style={{ flex: 1 }}>{b(option)}</T>
          </Row>
        </Pressable>
      ))}
      <T variant="small" style={{ color: c.muted }}>
        {t("passRule")}
      </T>
      <Button
        label={t(index < questions.length - 1 ? "next" : "submit")}
        disabled={selected === null}
        onPress={() => {
          if (selected === null) return;
          const nextAnswers = [...answers, selected];
          if (index < questions.length - 1) {
            setAnswers(nextAnswers);
            setIndex(index + 1);
            setSelected(null);
          } else {
            const score = Math.round(
              (nextAnswers.reduce(
                (sum, x, i) => sum + (x === questions[i].correct ? 1 : 0),
                0,
              ) /
                questions.length) *
                100,
            );
            update((s) => ({
              ...s,
              quizScores: [...s.quizScores, score],
              completed:
                score >= 80
                  ? [...new Set([...s.completed, id || "guest-service"])]
                  : s.completed,
            }));
            go("quiz-result", id);
          }
        }}
      />
    </View>
  );
}
export function Result({ id }: { id?: string }) {
  const { t, b, state } = useApp();
  const c = useTheme();
  if (state.mode === "live") return <LiveResult id={id} />;
  const score = state.quizScores.at(-1) || 0;
  return (
    <View style={styles.stack}>
      <Card style={{ alignItems: "center", padding: 30 }}>
        <Icon
          name={score >= 80 ? "ribbon-outline" : "refresh-outline"}
          size={47}
          color={score >= 80 ? c.green : c.accent}
        />
        <T
          variant="number"
          style={{ fontSize: 60, color: score >= 80 ? c.green : c.accent }}
        >
          {score}%
        </T>
        <T variant="title" style={{ textAlign: "center" }}>
          {t(score >= 80 ? "passed" : "notPassed")}
        </T>
      </Card>
      {questions.map((q, i) => (
        <Card key={i}>
          <T style={{ fontWeight: "600" }}>{b(q.title)}</T>
          <T style={{ color: c.muted }}>{b(q.explanation)}</T>
        </Card>
      ))}
      <Button
        label={t(score >= 80 ? "progress" : "review")}
        onPress={() => go(score >= 80 ? "progress" : "lesson", id)}
      />
      {score < 80 && state.quizScores.length < 3 && (
        <Button label={t("retry")} secondary onPress={() => go("quiz", id)} />
      )}
    </View>
  );
}
export function Progress() {
  const { state, t } = useApp();
  const c = useTheme();
  const live = useResource<Course[]>("plan?locale=" + state.locale);
  if (live.loading) return <StateView type="loading" />;
  if (live.error)
    return (
      <StateView
        type="error"
        detail={live.error.message}
        onRetry={live.retry}
      />
    );
  const list =
    state.mode === "demo"
      ? courses.map((x) => ({
          ...x,
          progress: state.completed.includes(x.id) ? 100 : x.progress,
        }))
      : live.data || [];
  const avg = list.length
    ? Math.round(list.reduce((s, x) => s + x.progress, 0) / list.length)
    : 0;
  return (
    <View style={styles.stack}>
      <Card style={{ padding: 25 }}>
        <T variant="label" style={{ color: c.muted }}>
          {t("learningPlan")}
        </T>
        <T variant="number" style={{ fontSize: 65, color: c.accent }}>
          {avg}%
        </T>
        <Bar value={avg} />
        <T>{t("planHours")}</T>
      </Card>
      <Row>
        <Card style={{ flex: 1 }}>
          <T variant="number">
            {list.filter((x) => x.progress === 100).length}
          </T>
          <T variant="small">{t("totalCourses")}</T>
        </Card>
        <Card style={{ flex: 1 }}>
          <T variant="number">{state.quizScores.length}</T>
          <T variant="small">{t("history")}</T>
        </Card>
      </Row>
      {state.mode === "demo" && (
        <Card>
          <Section title={t("activity")} />
          <Row style={{ height: 95, alignItems: "flex-end", gap: 15 }}>
            {[22, 41, 32, 66, 51, 78, 60].map((h, i) => (
              <View
                key={i}
                style={{
                  flex: 1,
                  height: h,
                  backgroundColor: i === 5 ? c.accent : c.soft,
                  borderRadius: 5,
                }}
              />
            ))}
          </Row>
          <T variant="small" style={{ color: c.muted }}>
            {t("teamSample")} · {t("weeks")}
          </T>
        </Card>
      )}
      <ListRow title={t("competencies")} onPress={() => go("competencies")} />
      <ListRow title={t("readiness")} onPress={() => go("readiness")} />
      <ListRow title={t("actions")} onPress={() => go("actions")} />
      <ListRow title={t("certificates")} onPress={() => go("certificates")} />
      <ListRow title={t("history")} onPress={() => go("assessment-history")} />
      <T style={{ color: c.muted }}>{t("readinessNote")}</T>
      <Button
        label={t("share")}
        secondary
        onPress={() =>
          void Share.share({
            message: `ALTUS · ${t("progress")}: ${avg}%\n${state.mode === "demo" ? t("demoNotice") : t("live")}`,
          })
        }
      />
    </View>
  );
}
