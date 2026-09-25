<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * AI help for editors (pages, sections, modules, lessons).
 *
 * The editor picks any enabled provider and model (or leaves the task route to
 * decide), can first "enhance" a rough prompt into a precise brief they can
 * read and edit, and then generate or improve content. Every call is logged
 * with the model, the prompt, the enhanced prompt and the output. Nothing is
 * saved to the page or lesson until the editor accepts it: the AI drafts, a
 * person publishes.
 */
class Ha_ai_assist {

    protected $CI;

    public static function tasks() {
        return array(
            'enhance_prompt' => 'Turn a rough request into a precise brief',
            'section'        => 'Write a page section',
            'improve'        => 'Improve the selected text',
            'translate_ar'   => 'Translate to Arabic (Modern Standard Arabic)',
            'translate_en'   => 'Translate to English',
            'seo_meta'       => 'Suggest meta title and description',
            'faq'            => 'Write FAQ questions and answers',
            'lesson'         => 'Write a short applied lesson',
            'quiz'           => 'Write assessment questions',
        );
    }

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->library(array('ha_ai_gateway', 'ha_auth'));
    }

    /** Enabled chat-capable providers with their models, for the model picker. */
    public function catalogue() {
        $out = array();
        foreach ($this->CI->ha_ai_gateway->providers() as $slug => $p) {
            if (empty($p['enabled']) || !in_array('chat', (array) $p['capabilities'], true)) {
                continue;
            }
            $models = array();
            foreach ($this->CI->ha_ai_gateway->models($slug) as $m) {
                $models[] = array('id' => $m['model_id'], 'label' => $m['label'] ?: $m['model_id']);
            }
            $out[] = array('slug' => $slug, 'name' => $p['name'], 'models' => $models);
        }
        return $out;
    }

    protected function system_for($task, $locale) {
        $lang = $locale === 'ar' ? 'Modern Standard Arabic' : 'English';
        $base = "You write for altus Hospitality Knowledge & Performance, a bilingual hotel training and advisory platform in Saudi Arabia (Altus Advisory). "
            . "Write for hotel owners, managers and staff. Be concrete and practical. Never invent statistics, quotes, laws, clients, awards or partnerships; "
            . "if a fact is needed and not given, leave a clearly marked placeholder like [source needed]. Output only the requested content.";
        switch ($task) {
            case 'enhance_prompt':
                return $base . " Rewrite the user's rough request as a precise brief for a writer: audience, goal, key points, structure, tone, length, language, "
                    . "SEO focus keyword if relevant, and what to avoid. Return only the improved brief, in the same language as the request.";
            case 'section':
                return $base . " Return JSON only: {\"heading\": string, \"body\": string (HTML using <p>, <ul>, <li>, <strong> only), \"items\": [{\"title\": string, \"text\": string}] (optional)}. Write in " . $lang . '.';
            case 'faq':
                return $base . " Return JSON only: {\"heading\": string, \"items\": [{\"q\": string, \"a\": string}]} with 4-6 questions people really ask and direct answers of 20-50 words. Write in " . $lang . '.';
            case 'seo_meta':
                return $base . " Return JSON only: {\"meta_title\": string (30-60 characters, includes the focus keyword), \"meta_description\": string (70-160 characters)}. Write in " . $lang . '.';
            case 'improve':
                return $base . ' Improve clarity, structure and tone of the given text without changing its facts. Keep the same language and HTML tags.';
            case 'translate_ar':
                return $base . ' Translate into Modern Standard Arabic suitable for Gulf hotel staff. Keep HTML tags, numbers and placeholders unchanged.';
            case 'translate_en':
                return $base . ' Translate into clear international English. Keep HTML tags, numbers and placeholders unchanged.';
            case 'lesson':
                return $base . " Write a short applied lesson (400-700 words) as HTML (<h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>): objective, why it matters, step-by-step standard, common mistakes, a scenario, a 3-question knowledge check. Write in " . $lang . '.';
            case 'quiz':
                return $base . " Return JSON only: {\"questions\": [{\"q\": string, \"options\": [string, string, string, string], \"correct\": 1-4, \"why\": string}]} with 5 questions about work actually done on shift. Write in " . $lang . '.';
        }
        return $base;
    }

    /**
     * @param array $in task, prompt, context (existing text), provider, model, locale, entity_type, entity_id
     * @return array ok, text, json (decoded when the task returns JSON), provider, model
     */
    public function run(array $in) {
        if (!$this->CI->ha_auth->has(array('ai.generate', 'cms_pages.update', 'courses.update', 'lessons.update'))) {
            throw new RuntimeException('You do not have access to AI writing help.');
        }
        $task = isset($in['task']) && isset(self::tasks()[$in['task']]) ? $in['task'] : 'improve';
        $prompt = trim((string) (isset($in['prompt']) ? $in['prompt'] : ''));
        $context = trim((string) (isset($in['context']) ? $in['context'] : ''));
        if ($prompt === '' && $context === '') {
            throw new InvalidArgumentException('Write a request, or select text to work on.');
        }
        if (mb_strlen($prompt) > 6000 || mb_strlen($context) > 20000) {
            throw new InvalidArgumentException('The request is too long.');
        }
        $locale = isset($in['locale']) && $in['locale'] === 'ar' ? 'ar' : 'en';
        $gw = $this->CI->ha_ai_gateway;
        $slug = isset($in['provider']) ? preg_replace('/[^a-z0-9_]/i', '', (string) $in['provider']) : '';
        $model = isset($in['model']) ? trim((string) $in['model']) : '';
        if ($slug !== '') {
            $p = $gw->provider($slug);
            if (!$p || empty($p['enabled'])) {
                throw new RuntimeException('That AI provider is not enabled. Open AI Studio → Providers.');
            }
            if ($model === '') {
                $models = $gw->models($slug);
                $model = $models ? $models[0]['model_id'] : '';
            }
            if ($model === '') {
                throw new RuntimeException('Choose a model for ' . $p['name'] . '.');
            }
        } else {
            list($p, $model) = $gw->resolve('assistant');
        }
        $user = $prompt;
        if ($context !== '') {
            $user .= ($user !== '' ? "\n\n" : '') . "TEXT:\n" . $context;
        }
        $json = in_array($task, array('section', 'faq', 'seo_meta', 'quiz'), true);
        $log = array('user_id' => $this->CI->ha_auth->id(), 'context' => $task, 'entity_type' => isset($in['entity_type']) ? mb_substr($in['entity_type'], 0, 40) : null,
            'entity_id' => isset($in['entity_id']) ? (int) $in['entity_id'] : null, 'provider' => $p['slug'], 'model' => mb_substr($model, 0, 190),
            'prompt' => mb_substr($user, 0, 60000), 'created_at' => date('Y-m-d H:i:s'));
        try {
            $res = $gw->chat_with($p, $model, array(array('role' => 'system', 'content' => $this->system_for($task, $locale)), array('role' => 'user', 'content' => $user)),
                array('temperature' => $task === 'enhance_prompt' ? 0.3 : 0.5, 'max_tokens' => $task === 'lesson' ? 3000 : 1500, 'json' => $json), 'assistant');
        } catch (Exception $e) {
            $this->CI->db->insert('ha_ai_prompt_log', $log + array('ok' => 0, 'error' => mb_substr($e->getMessage(), 0, 500)));
            throw new RuntimeException($e->getMessage());
        }
        $text = trim((string) $res['text']);
        $decoded = null;
        if ($json) {
            $clean = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text);
            if (preg_match('/\{.*\}/s', $clean, $m)) {
                $decoded = json_decode($m[0], true);
            }
            if (!is_array($decoded)) {
                $this->CI->db->insert('ha_ai_prompt_log', $log + array('output' => $text, 'ok' => 0, 'error' => 'The model did not return valid JSON.'));
                throw new RuntimeException('The model did not return the expected structure. Try again or choose another model.');
            }
        }
        $this->CI->db->insert('ha_ai_prompt_log', $log + array('output' => $text, 'ok' => 1, 'enhanced_prompt' => $task === 'enhance_prompt' ? $text : null));
        return array('ok' => true, 'text' => $text, 'json' => $decoded, 'provider' => $p['slug'], 'model' => $model,
            'tokens' => (int) $res['input_tokens'] + (int) $res['output_tokens']);
    }
}
