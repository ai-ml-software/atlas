<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Ha_seeder.php';
require_once APPPATH . 'helpers/ha_locale_helper.php';

/**
 * The Dyafa service-standards library: 40 courses built from the group's own
 * training decks, one file per course in application/seeds/library/<slug>.json.
 *
 *   course  = one deck                 ha_course            code dy-<slug>
 *   chapter = a part of the deck       ha_course_section    (course, sort_order)
 *   lesson  = a reading chapter        ha_lesson            (course, sort_order)
 *             + an optional verified YouTube video           ha_lesson_video_source
 *   quiz    = 4 scenario questions after every lesson       ha_assessment as-dy-<slug>-<n>
 *
 * Every lesson has completion_rule = quiz: the lesson counts as done only when
 * its quiz is passed (PASS per cent), and Ha_bridge publishes the courses to the
 * lesson player with sequential unlock, so the next lesson opens only after the
 * quiz before it has been passed.
 *
 * Text is stored for every language present in the file: ha_*_translation rows
 * per locale; en/ar column pairs plus ha_i18n_text overlays for the others.
 *
 * Idempotent and safe on production data: records are matched on natural keys
 * (codes, course + position) and updated in place, so ids - and with them a
 * learner's progress - survive a re-run.
 *
 *   php index.php ha_cli seed library
 */
class Seed_library extends Ha_seeder {

    const PREFIX = 'dy-';
    const PASS = 75;
    const ATTEMPTS = 10;
    const QUIZ_MINUTES = 3;

    /** Deck category -> academy category code. Two of them already exist. */
    private $category_map = array(
        'communication'          => 'communication-skills',
        'guest-handling'         => 'guest-relations',
        'personalised-service'   => 'guest-experience',
        'safety-security'        => 'security-and-safety',
        'professional-wellbeing' => 'professional-wellbeing',
    );

    public function run($db) {
        $this->boot($db);
        $files = glob(APPPATH . 'seeds/library/*.json');
        if (!$files || !$this->db->table_exists('ha_course')) {
            return 0;
        }
        sort($files);
        $n = $this->categories();
        foreach ($files as $file) {
            $course = json_decode(file_get_contents($file), true);
            if (!is_array($course) || empty($course['slug']) || empty($course['locales']['en'])) {
                continue;
            }
            $n += $this->course($course);
        }
        return $n;
    }

    // ------------------------------------------------------------ categories

    private function categories() {
        $new = array(
            'communication-skills' => array(12, 'uploads/academy/library/cat-communication-skills.webp', array(
                'en' => array('Communication Skills', 'Listening, adapting your style, the telephone, working across languages and departments: the conversations every guest-facing colleague has every day.'),
                'ar' => array('مهارات التواصل', 'الإنصات، وتكييف أسلوب الحديث، والهاتف، والتواصل عبر اللغات والأقسام: الحوارات التي يخوضها كل موظف يخدم النزلاء يوميًا.'),
                'hi' => array('संचार कौशल', 'सुनना, अपनी शैली को ढालना, टेलीफ़ोन, भाषाओं और विभागों के पार काम करना: हर दिन मेहमानों से जुड़े हर सहकर्मी की बातचीत।'),
                'ur' => array('مواصلاتی مہارتیں', 'سننا، اپنا انداز ڈھالنا، ٹیلی فون، زبانوں اور شعبوں کے درمیان کام کرنا: وہ گفتگو جو مہمانوں سے جڑا ہر ساتھی روزانہ کرتا ہے۔'),
                'tl' => array('Kasanayan sa Komunikasyon', 'Pakikinig, pag-aangkop ng istilo, telepono, at pakikipag-ugnayan sa iba\'t ibang wika at departamento: ang mga usapang ginagawa araw-araw ng bawat kawaning humaharap sa bisita.'),
                'bn' => array('যোগাযোগ দক্ষতা', 'শোনা, নিজের ধরন মানিয়ে নেওয়া, টেলিফোন, ভাষা ও বিভাগ পেরিয়ে কাজ করা: অতিথির সামনে কাজ করা প্রত্যেক সহকর্মীর প্রতিদিনের কথোপকথন।'),
                'ne' => array('सञ्चार सीप', 'सुन्ने, आफ्नो शैली मिलाउने, टेलिफोन, भाषा र विभागबीच काम गर्ने: पाहुनासँग काम गर्ने हरेक सहकर्मीले दैनिक गर्ने कुराकानी।'),
                'ml' => array('ആശയവിനിമയ നൈപുണ്യം', 'കേൾക്കൽ, ശൈലി ക്രമീകരിക്കൽ, ടെലിഫോൺ, ഭാഷകൾക്കും വകുപ്പുകൾക്കും അപ്പുറമുള്ള ആശയവിനിമയം: അതിഥികളെ സേവിക്കുന്ന ഓരോ സഹപ്രവർത്തകന്റെയും ദൈനംദിന സംഭാഷണങ്ങൾ.'),
            )),
            'guest-relations' => array(13, 'uploads/academy/library/cat-guest-relations.webp', array(
                'en' => array('Guest Relations', 'Requests, complaints, apologies, alternatives and follow-up: turning every guest interaction, including the difficult ones, into trust.'),
                'ar' => array('علاقات النزلاء', 'الطلبات والشكاوى والاعتذار والبدائل والمتابعة: تحويل كل تعامل مع النزيل، حتى الصعب منه، إلى ثقة.'),
                'hi' => array('अतिथि संबंध', 'अनुरोध, शिकायतें, माफ़ी, विकल्प और फ़ॉलो-अप: हर अतिथि बातचीत को, कठिन बातचीत को भी, भरोसे में बदलना।'),
                'ur' => array('مہمانوں سے تعلقات', 'درخواستیں، شکایات، معذرت، متبادل اور فالو اپ: مہمان کے ساتھ ہر معاملے کو، مشکل معاملات سمیت، اعتماد میں بدلنا۔'),
                'tl' => array('Ugnayan sa Bisita', 'Mga kahilingan, reklamo, paghingi ng paumanhin, alternatibo at follow-up: gawing tiwala ang bawat pakikitungo sa bisita, pati ang mahihirap.'),
                'bn' => array('অতিথি সম্পর্ক', 'অনুরোধ, অভিযোগ, ক্ষমা চাওয়া, বিকল্প ও ফলো-আপ: কঠিনগুলো সহ প্রতিটি অতিথি আলাপকে আস্থায় পরিণত করা।'),
                'ne' => array('अतिथि सम्बन्ध', 'अनुरोध, गुनासो, माफी, विकल्प र फलो-अप: कठिनसहित पाहुनासँगको हरेक व्यवहारलाई विश्वासमा बदल्ने।'),
                'ml' => array('അതിഥി ബന്ധങ്ങൾ', 'അഭ്യർത്ഥനകൾ, പരാതികൾ, ക്ഷമാപണം, ബദലുകൾ, ഫോളോ-അപ്പ്: പ്രയാസമുള്ളവ ഉൾപ്പെടെ ഓരോ അതിഥി ഇടപെടലിനെയും വിശ്വാസമാക്കി മാറ്റൽ.'),
            )),
            'professional-wellbeing' => array(14, 'uploads/academy/library/cat-professional-wellbeing.webp', array(
                'en' => array('Professional Growth & Wellbeing', 'Confidence, deportment, stress, balance and wellbeing: looking after the person who looks after the guest.'),
                'ar' => array('التطوير المهني والرفاهية', 'الثقة بالنفس، والمظهر المهني، وإدارة الضغط، والتوازن، والرفاهية: الاهتمام بمن يهتم بالنزيل.'),
                'hi' => array('पेशेवर विकास और कल्याण', 'आत्मविश्वास, पेशेवर आचरण, तनाव, संतुलन और कल्याण: उस व्यक्ति का ध्यान रखना जो अतिथि का ध्यान रखता है।'),
                'ur' => array('پیشہ ورانہ ترقی اور فلاح', 'خود اعتمادی، پیشہ ورانہ وضع، دباؤ، توازن اور فلاح: اس شخص کا خیال رکھنا جو مہمان کا خیال رکھتا ہے۔'),
                'tl' => array('Propesyonal na Pag-unlad at Kagalingan', 'Kumpiyansa, propesyonal na tindig, stress, balanse at kagalingan: pag-aalaga sa taong nag-aalaga sa bisita.'),
                'bn' => array('পেশাগত উন্নয়ন ও সুস্থতা', 'আত্মবিশ্বাস, পেশাদার আচরণ, চাপ, ভারসাম্য ও সুস্থতা: যিনি অতিথির যত্ন নেন তাঁর যত্ন নেওয়া।'),
                'ne' => array('व्यावसायिक विकास र कल्याण', 'आत्मविश्वास, व्यावसायिक आचरण, तनाव, सन्तुलन र कल्याण: पाहुनाको हेरचाह गर्ने व्यक्तिको हेरचाह।'),
                'ml' => array('പ്രൊഫഷണൽ വളർച്ചയും ക്ഷേമവും', 'ആത്മവിശ്വാസം, പ്രൊഫഷണൽ പെരുമാറ്റം, സമ്മർദ്ദം, സന്തുലനം, ക്ഷേമം: അതിഥിയെ പരിപാലിക്കുന്നയാളെ പരിപാലിക്കൽ.'),
            )),
        );
        $n = 0;
        foreach ($new as $code => $def) {
            list($sort, $icon, $names) = $def;
            $had = $this->db->select('slug_ar')->get_where('ha_category', array('code' => $code))->row('slug_ar');
            $id = $this->upsert('ha_category', array('code' => $code), array(
                'slug_en' => $code, 'slug_ar' => $had ? $had : $this->unique_slug('ha_category', $names['ar'][0], $code),
                'icon' => $icon, 'sort_order' => $sort, 'status' => 'active',
            ));
            foreach ($names as $locale => $pair) {
                $this->upsert('ha_category_translation', array('category_id' => $id, 'locale' => $locale),
                    array('name' => $pair[0], 'description' => $pair[1]));
            }
            $n++;
        }
        return $n;
    }

    // ---------------------------------------------------------------- course

    private function course(array $c) {
        $code = self::PREFIX . $c['slug'];
        $loc = $c['locales'];
        $en = $loc['en'];
        $ar = isset($loc['ar']) ? $loc['ar'] : $en;
        $cat_code = isset($this->category_map[$c['category']]) ? $this->category_map[$c['category']] : $c['category'];
        $category_id = (int) $this->db->select('id')->get_where('ha_category', array('code' => $cat_code))->row('id');

        $lessons = array();
        foreach ($en['chapters'] as $ci => $chapter) {
            foreach ($chapter['lessons'] as $li => $l) {
                $lessons[] = array($ci, $li, $l);
            }
        }
        $minutes = 0;
        foreach ($lessons as $x) {
            $minutes += (int) $x[2]['minutes'] + self::QUIZ_MINUTES;
        }
        $videos = isset($c['videos']) ? array_values($c['videos']) : array();
        $video_at = $this->place_videos(count($lessons), count($videos));

        $existing = $this->db->get_where('ha_course', array('code' => $code))->row_array();
        $course_id = $this->upsert('ha_course', array('code' => $code), array(
            'slug_en'              => $c['slug'],
            'slug_ar'              => $existing ? $existing['slug_ar'] : $this->unique_slug('ha_course', $ar['title'], $c['slug']),
            'category_id'          => $category_id ?: null,
            'level'                => $c['level'],
            'duration_minutes'     => $minutes,
            'thumbnail'            => isset($c['thumbnail']) ? $c['thumbnail'] : null,
            'preview_video'        => $videos ? 'https://www.youtube.com/watch?v=' . $videos[0]['id'] : null,
            'is_free'              => 1,
            'price'                => 0,
            'currency'             => 'SAR',
            'certificate_eligible' => 1,
            'pass_percentage'      => self::PASS,
            'status'               => 'published',
            'published_at'         => ($existing && $existing['published_at']) ? $existing['published_at'] : $this->now,
        ));

        foreach ($loc as $locale => $o) {
            $this->upsert('ha_course_translation', array('course_id' => $course_id, 'locale' => $locale), array(
                'title' => $o['title'], 'short_description' => $o['short_description'],
                'description' => $o['description'], 'requirements' => $o['requirements'],
            ));
            $this->db->delete('ha_course_outcome', array('course_id' => $course_id, 'locale' => $locale));
            foreach (array_values($o['outcomes']) as $i => $body) {
                $this->db->insert('ha_course_outcome', array('course_id' => $course_id, 'locale' => $locale, 'body' => $body, 'sort_order' => $i + 1));
            }
            $this->db->delete('ha_course_faq', array('course_id' => $course_id, 'locale' => $locale));
            foreach (array_values($o['faqs']) as $i => $f) {
                $this->db->insert('ha_course_faq', array('course_id' => $course_id, 'locale' => $locale,
                    'question' => $f['question'], 'answer' => $f['answer'], 'sort_order' => $i + 1));
            }
            $this->upsert('ha_seo_metadata', array('entity_type' => 'course', 'entity_id' => $course_id, 'locale' => $locale), array(
                'meta_title' => $o['meta_title'], 'meta_description' => $o['meta_description'], 'focus_keyword' => $o['focus_keyword'],
            ));
        }

        // Chapters.
        $section_ids = array();
        foreach ($en['chapters'] as $ci => $chapter) {
            $section_ids[$ci] = $this->upsert('ha_course_section', array('course_id' => $course_id, 'sort_order' => $ci), array(
                'title_en' => $chapter['title'],
                'title_ar' => isset($ar['chapters'][$ci]['title']) ? $ar['chapters'][$ci]['title'] : $chapter['title'],
            ));
            foreach ($loc as $locale => $o) {
                if ($locale !== 'en' && $locale !== 'ar' && !empty($o['chapters'][$ci]['title'])) {
                    $this->overlay('course_section', $section_ids[$ci], 'title', $locale, $o['chapters'][$ci]['title']);
                }
            }
        }

        $bank_id = $this->upsert('ha_question_bank', array('code' => 'qb-' . $code), array(
            'name_en' => $en['title'], 'name_ar' => $ar['title'], 'course_id' => $course_id, 'status' => 'active',
        ));

        foreach ($lessons as $k => $x) {
            list($ci, $li, $l) = $x;
            $video = isset($video_at[$k]) ? $videos[$video_at[$k]] : null;
            $assessment_id = $this->quiz($code, $course_id, $bank_id, $k, $ci, $li, $loc);
            $lesson_id = $this->upsert('ha_lesson', array('course_id' => $course_id, 'sort_order' => $k), array(
                'section_id'                => $section_ids[$ci],
                'lesson_type'               => $video ? 'video' : 'text',
                'video_source'              => $video ? 'youtube' : null,
                'video_url'                 => $video ? 'https://www.youtube.com/watch?v=' . $video['id'] : null,
                'media_type'                => $video ? 'video' : 'none',
                'duration_seconds'          => (int) $l['minutes'] * 60,
                'is_mandatory'              => 1,
                'is_preview'                => $k === 0 ? 1 : 0,
                'completion_rule'           => 'quiz',
                'required_watch_percentage' => 0,
                'assessment_id'             => $assessment_id,
                'status'                    => 'published',
            ));
            foreach ($loc as $locale => $o) {
                if (empty($o['chapters'][$ci]['lessons'][$li])) {
                    continue;
                }
                $ol = $o['chapters'][$ci]['lessons'][$li];
                $this->upsert('ha_lesson_translation', array('lesson_id' => $lesson_id, 'locale' => $locale), array(
                    'title' => $ol['title'], 'objective' => $o['chapters'][$ci]['title'], 'body' => $ol['body'],
                ));
            }
            if ($video) {
                $this->upsert('ha_lesson_video_source', array('lesson_id' => $lesson_id), array(
                    'provider' => 'youtube', 'video_id' => $video['id'],
                    'watch_url' => 'https://www.youtube.com/watch?v=' . $video['id'],
                    'embed_url' => 'https://www.youtube-nocookie.com/embed/' . $video['id'],
                    'title' => $video['title'], 'author_name' => $video['channel'],
                    'author_url' => isset($video['author_url']) ? $video['author_url'] : null,
                    'thumbnail_url' => 'https://i.ytimg.com/vi/' . $video['id'] . '/hqdefault.jpg',
                    'is_owned' => 0, 'status' => 'live', 'last_checked_at' => $this->now, 'last_error' => null,
                ));
            } else {
                $this->db->delete('ha_lesson_video_source', array('lesson_id' => $lesson_id));
            }
        }

        // A deck that was shortened loses its trailing lessons and chapters.
        $gone = $this->db->select('id, assessment_id')->where('course_id', $course_id)->where('sort_order >=', count($lessons))->get('ha_lesson')->result_array();
        foreach ($gone as $g) {
            $this->db->delete('ha_lesson_video_source', array('lesson_id' => $g['id']));
            $this->db->delete('ha_lesson', array('id' => $g['id']));
            if ($g['assessment_id']) {
                $this->db->delete('ha_assessment', array('id' => $g['assessment_id']));
            }
        }
        $this->db->where('course_id', $course_id)->where('sort_order >=', count($en['chapters']))->delete('ha_course_section');
        return 1;
    }

    /** Spread n videos over the lessons: 1 video -> lesson 1, 2 videos over 4 lessons -> lessons 1 and 3. */
    private function place_videos($lessons, $videos) {
        $at = array();
        for ($v = 0; $v < min($videos, $lessons); $v++) {
            $at[(int) floor($v * $lessons / $videos)] = $v;
        }
        return $at;
    }

    // ------------------------------------------------------------------ quiz

    private function quiz($course_code, $course_id, $bank_id, $k, $ci, $li, array $loc) {
        $q_en = $loc['en']['chapters'][$ci]['lessons'][$li]['quiz'];
        $q_ar = isset($loc['ar']['chapters'][$ci]['lessons'][$li]['quiz']) ? $loc['ar']['chapters'][$ci]['lessons'][$li]['quiz'] : $q_en;
        $need = (int) ceil(count($q_en['questions']) * self::PASS / 100);
        $id = $this->upsert('ha_assessment', array('code' => 'as-' . $course_code . '-' . ($k + 1)), array(
            'title_en'             => $q_en['title'],
            'title_ar'             => $q_ar['title'],
            'instructions_en'      => 'Answer all ' . count($q_en['questions']) . ' questions. ' . $need . ' correct answers unlock the next lesson; you can retake the quiz.',
            'instructions_ar'      => 'أجب عن الأسئلة ' . count($q_en['questions']) . ' كلها. تحتاج إلى ' . $need . ' إجابات صحيحة لفتح الدرس التالي، ويمكنك إعادة الاختبار.',
            'assessment_type'      => 'quiz',
            'course_id'            => $course_id,
            'bank_id'              => $bank_id,
            'question_selection'   => 'fixed',
            'shuffle_questions'    => 0,
            'shuffle_options'      => 0,
            'time_limit_minutes'   => 0,
            'max_attempts'         => self::ATTEMPTS,
            'pass_percentage'      => self::PASS,
            'show_correct_answers' => 1,
            'status'               => 'published',
        ));
        $instructions = array(
            'hi' => 'सभी {n} प्रश्नों के उत्तर दें। अगला पाठ खोलने के लिए {k} सही उत्तर चाहिए; आप क्विज़ दोबारा दे सकते हैं।',
            'ur' => 'تمام {n} سوالات کے جواب دیں۔ اگلا سبق کھولنے کے لیے {k} درست جوابات درکار ہیں؛ آپ کوئز دوبارہ دے سکتے ہیں۔',
            'tl' => 'Sagutin ang lahat ng {n} tanong. Kailangan ang {k} tamang sagot para mabuksan ang susunod na aralin; maaari mong ulitin ang pagsusulit.',
            'bn' => 'সব {n}টি প্রশ্নের উত্তর দিন। পরের পাঠ খুলতে {k}টি সঠিক উত্তর প্রয়োজন; আপনি কুইজটি আবার দিতে পারেন।',
            'ne' => 'सबै {n} प्रश्नको उत्तर दिनुहोस्। अर्को पाठ खोल्न {k} सही उत्तर चाहिन्छ; तपाईं क्विज फेरि दिन सक्नुहुन्छ।',
            'ml' => 'എല്ലാ {n} ചോദ്യങ്ങൾക്കും ഉത്തരം നൽകുക. അടുത്ത പാഠം തുറക്കാൻ {k} ശരിയുത്തരങ്ങൾ വേണം; ക്വിസ് വീണ്ടും എഴുതാം.',
        );
        foreach ($loc as $locale => $o) {
            if ($locale !== 'en' && $locale !== 'ar' && !empty($o['chapters'][$ci]['lessons'][$li]['quiz']['title'])) {
                $this->overlay('assessment', $id, 'title', $locale, $o['chapters'][$ci]['lessons'][$li]['quiz']['title']);
                if (isset($instructions[$locale])) {
                    $this->overlay('assessment', $id, 'instructions', $locale,
                        strtr($instructions[$locale], array('{n}' => count($q_en['questions']), '{k}' => $need)));
                }
            }
        }

        $linked = $this->db->select('question_id')->where('assessment_id', $id)->order_by('sort_order', 'ASC')->get('ha_assessment_question')->result_array();
        foreach ($q_en['questions'] as $qi => $q) {
            $qa = isset($q_ar['questions'][$qi]) ? $q_ar['questions'][$qi] : $q;
            $fields = array(
                'bank_id' => $bank_id, 'question_type' => 'multiple_choice',
                'body_en' => $q['question'], 'body_ar' => $qa['question'],
                'explanation_en' => $q['explanation'], 'explanation_ar' => $qa['explanation'],
                'marks' => 1, 'status' => 'active', 'updated_at' => $this->now,
            );
            if (isset($linked[$qi])) {
                $question_id = (int) $linked[$qi]['question_id'];
                $this->db->where('id', $question_id)->update('ha_question', $fields);
            } else {
                $fields['created_at'] = $this->now;
                $this->db->insert('ha_question', $fields);
                $question_id = (int) $this->db->insert_id();
                $this->db->insert('ha_assessment_question', array('assessment_id' => $id, 'question_id' => $question_id, 'marks' => 1, 'sort_order' => $qi));
            }
            $options = $this->db->select('id')->where('question_id', $question_id)->order_by('sort_order', 'ASC')->get('ha_question_option')->result_array();
            foreach ($q['options'] as $oi => $text) {
                $row = array('body_en' => $text, 'body_ar' => isset($qa['options'][$oi]) ? $qa['options'][$oi] : $text,
                    'is_correct' => $oi === (int) $q['answer'] ? 1 : 0, 'sort_order' => $oi);
                if (isset($options[$oi])) {
                    $option_id = (int) $options[$oi]['id'];
                    $this->db->where('id', $option_id)->update('ha_question_option', $row);
                } else {
                    $this->db->insert('ha_question_option', array_merge($row, array('question_id' => $question_id)));
                    $option_id = (int) $this->db->insert_id();
                }
                foreach ($loc as $locale => $o) {
                    if ($locale === 'en' || $locale === 'ar') {
                        continue;
                    }
                    $lq = isset($o['chapters'][$ci]['lessons'][$li]['quiz']['questions'][$qi]) ? $o['chapters'][$ci]['lessons'][$li]['quiz']['questions'][$qi] : null;
                    if ($lq && isset($lq['options'][$oi])) {
                        $this->overlay('question_option', $option_id, 'body', $locale, $lq['options'][$oi]);
                    }
                }
            }
            foreach (array_slice($options, count($q['options'])) as $extra) {
                $this->db->delete('ha_question_option', array('id' => $extra['id']));
            }
            foreach ($loc as $locale => $o) {
                if ($locale === 'en' || $locale === 'ar') {
                    continue;
                }
                $lq = isset($o['chapters'][$ci]['lessons'][$li]['quiz']['questions'][$qi]) ? $o['chapters'][$ci]['lessons'][$li]['quiz']['questions'][$qi] : null;
                if ($lq) {
                    $this->overlay('question', $question_id, 'body', $locale, $lq['question']);
                    $this->overlay('question', $question_id, 'explanation', $locale, $lq['explanation']);
                }
            }
        }
        foreach (array_slice($linked, count($q_en['questions'])) as $extra) {
            $this->db->delete('ha_assessment_question', array('assessment_id' => $id, 'question_id' => $extra['question_id']));
        }
        return $id;
    }

    // --------------------------------------------------------------- helpers

    private function overlay($entity, $id, $field, $locale, $value) {
        if (trim((string) $value) === '') {
            return;
        }
        $this->upsert('ha_i18n_text', array('entity' => $entity, 'entity_id' => (int) $id, 'field' => $field, 'locale' => $locale),
            array('value' => $value, 'source' => 'human'));
    }

    /** slug_ar is unique per table; the Arabic title is used, the English slug breaks a tie. */
    private function unique_slug($table, $title, $fallback) {
        $slug = $this->slugify($title);
        if ($slug === '') {
            return $fallback;
        }
        $taken = $this->db->where('slug_ar', $slug)->count_all_results($table);
        return $taken ? $slug . '-' . $fallback : $slug;
    }
}
