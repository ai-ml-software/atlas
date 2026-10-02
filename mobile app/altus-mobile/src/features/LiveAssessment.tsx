import React, { useEffect, useState } from "react";
import { View, Pressable } from "react-native";
import { useApp } from "../state/AppProvider";
import {
  T,
  Card,
  Button,
  Field,
  Row,
  Bar,
  Icon,
  go,
  StateView,
  useTheme,
  styles,
} from "../components/ui";
import { useResource } from "../services/useResource";
interface LiveQuestion {
  id: number;
  type: string;
  body: string;
  options: { id: number; body: string; is_correct?: number }[];
  explanation: string | null;
  matches?: string[];
}
interface Paper {
  id: number;
  status: string;
  score: number | null;
  passed: number | null;
  pass_score: number;
  questions: LiveQuestion[];
}
export function LiveAssessment({ id }: { id?: string }) {
  const { api, t, state } = useApp();
  const c = useTheme();
  const [paper, setPaper] = useState<Paper | null>(null),
    [error, setError] = useState(""),
    [busy, setBusy] = useState(false),
    [answers, setAnswers] = useState<
      Record<string, string | number | number[] | Record<string, string>>
    >({}),
    [index, setIndex] = useState(0);
  useEffect(() => {
    let active = true;
    const start = async () => {
      try {
        if (!api || !id) return;
        const attempt = await api.request<{ id: number }>(
          "assessment_start",
          "POST",
          { id: Number(id) },
        );
        const value = await api.request<Paper>(
          `assessment?id=${attempt.id}&locale=${state.locale}`,
        );
        if (active) setPaper(value);
      } catch (e) {
        if (active) setError(e instanceof Error ? e.message : t("apiFailure"));
      }
    };
    void start();
    return () => {
      active = false;
    };
  }, [api, id, state.locale, t]);
  if (error && !paper) return <StateView type="error" detail={error} />;
  if (!paper) return <StateView type="loading" />;
  const q = paper.questions[index];
  if (!q) return <StateView />;
  const response = answers[String(q.id)];
  const choice = q.options.length > 0;
  const multiple = ["multiple_answer", "multiple_response"].includes(q.type);
  const ordered = Array.isArray(response) ? response : [];
  const matches =
    typeof response === "object" && !Array.isArray(response) && response
      ? response
      : {};
  const complete =
    q.type === "ordering"
      ? ordered.length === q.options.length
      : q.type === "matching"
        ? q.options.every((o) => !!matches[String(o.id)])
        : Array.isArray(response)
          ? response.length > 0
          : typeof response === "string"
            ? !!response.trim()
            : response !== undefined;
  return (
    <View style={styles.stack}>
      <Bar value={((index + 1) / paper.questions.length) * 100} />
      <T variant="small">
        {t("questionCount")} {index + 1}/{paper.questions.length}
      </T>
      <T variant="display" style={{ fontSize: 27 }}>
        {q.body}
      </T>
      {q.type === "ordering" ? (
        <>
          {ordered.map((optionId, orderIndex) => (
            <ListRowForOrder
              key={optionId}
              label={`${orderIndex + 1}. ${q.options.find((o) => o.id === optionId)?.body || ""}`}
              onPress={() =>
                setAnswers((old) => ({
                  ...old,
                  [String(q.id)]: ordered.filter((x) => x !== optionId),
                }))
              }
            />
          ))}
          {q.options
            .filter((o) => !ordered.includes(o.id))
            .map((option) => (
              <Button
                key={option.id}
                secondary
                label={option.body}
                onPress={() =>
                  setAnswers((old) => ({
                    ...old,
                    [String(q.id)]: [...ordered, option.id],
                  }))
                }
              />
            ))}
        </>
      ) : q.type === "matching" ? (
        q.options.map((option) => (
          <Card key={option.id}>
            <T>{option.body}</T>
            {q.matches?.map((value) => (
              <Button
                key={value}
                secondary={matches[String(option.id)] !== value}
                label={value}
                onPress={() =>
                  setAnswers((old) => ({
                    ...old,
                    [String(q.id)]: { ...matches, [String(option.id)]: value },
                  }))
                }
              />
            ))}
          </Card>
        ))
      ) : choice ? (
        q.options.map((option) => {
          const selected = Array.isArray(response)
            ? response.includes(option.id)
            : response === option.id;
          return (
            <Pressable
              key={option.id}
              accessibilityRole={multiple ? "checkbox" : "radio"}
              accessibilityState={{ checked: selected }}
              onPress={() =>
                setAnswers((old) => ({
                  ...old,
                  [String(q.id)]: multiple
                    ? Array.isArray(response) && response.includes(option.id)
                      ? response.filter((x) => x !== option.id)
                      : [
                          ...(Array.isArray(response) ? response : []),
                          option.id,
                        ]
                    : option.id,
                }))
              }
              style={{
                padding: 16,
                borderWidth: 1,
                borderColor: selected ? c.accent : c.border,
                borderRadius: 12,
                minHeight: 55,
              }}
            >
              <Row>
                <Icon
                  name={selected ? "checkmark-circle" : "ellipse-outline"}
                />
                <T style={{ flex: 1 }}>{option.body}</T>
              </Row>
            </Pressable>
          );
        })
      ) : (
        <Field
          label={t("details")}
          multiline
          value={typeof response === "string" ? response : ""}
          onChangeText={(value) =>
            setAnswers((old) => ({ ...old, [String(q.id)]: value }))
          }
        />
      )}
      {!!error && <T style={{ color: c.accent }}>{error}</T>}
      {index > 0 && (
        <Button
          secondary
          label={t("previous")}
          onPress={() => setIndex(index - 1)}
        />
      )}
      <Button
        label={t(index < paper.questions.length - 1 ? "next" : "submit")}
        busy={busy}
        disabled={!complete}
        onPress={async () => {
          if (index < paper.questions.length - 1) {
            setIndex(index + 1);
            return;
          }
          setBusy(true);
          try {
            await api?.request("assessment_submit", "POST", {
              id: paper.id,
              answers,
            });
            go("quiz-result", String(paper.id));
          } catch (e) {
            setError(e instanceof Error ? e.message : t("apiFailure"));
          } finally {
            setBusy(false);
          }
        }}
      />
    </View>
  );
}
export function LiveResult({ id }: { id?: string }) {
  const { t, state } = useApp();
  const paper = useResource<Paper>(
    `assessment?id=${encodeURIComponent(id || "")}&locale=${state.locale}`,
  );
  if (paper.loading) return <StateView type="loading" />;
  if (paper.error)
    return (
      <StateView
        type="error"
        detail={paper.error.message}
        onRetry={paper.retry}
      />
    );
  if (!paper.data) return <StateView />;
  return (
    <View style={styles.stack}>
      <Card>
        <T variant="number">
          {paper.data.score === null ? t("pending") : `${paper.data.score}%`}
        </T>
        <T variant="title">
          {t(
            paper.data.passed === null
              ? "pending"
              : paper.data.passed
                ? "passed"
                : "notPassed",
          )}
        </T>
      </Card>
      {paper.data.questions
        .filter((q) => q.explanation)
        .map((q) => (
          <Card key={q.id}>
            <T>{q.body}</T>
            <T>{q.explanation}</T>
          </Card>
        ))}
      <Button label={t("learningPlan")} onPress={() => go("learning")} />
    </View>
  );
}
function ListRowForOrder({
  label,
  onPress,
}: {
  label: string;
  onPress: () => void;
}) {
  const { t } = useApp();
  return (
    <Card>
      <T>{label}</T>
      <Button secondary label={t("remove")} onPress={onPress} />
    </Card>
  );
}
