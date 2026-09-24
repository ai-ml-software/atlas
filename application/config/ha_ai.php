<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| AI Studio tasks.
|
| Code asks for a task, never for a model. Which provider and model runs a task
| is an admin decision stored in ha_ai_route, so switching every lesson script
| from one vendor to another is a settings change, not a deploy.
|
| kind: text  -> chat completion returning text / JSON
|       tts   -> speech synthesis, returns audio
|       avatar-> talking-presenter video from a script
|       clip  -> generative video clip from a prompt (b-roll)
*/
$config['ha_ai_tasks'] = array(
    'course_outline' => array('kind' => 'text',   'label' => 'Course outline (sections, lessons, outcomes)', 'json' => true,  'max_tokens' => 8000,  'temperature' => 0.4),
    'lesson_script'  => array('kind' => 'text',   'label' => 'Lesson video script + slides + quiz',            'json' => true,  'max_tokens' => 12000, 'temperature' => 0.4),
    'translate_ar'   => array('kind' => 'text',   'label' => 'Arabic adaptation of English content',           'json' => true,  'max_tokens' => 12000, 'temperature' => 0.2),
    'review'         => array('kind' => 'text',   'label' => 'Quality and safety review of a draft',           'json' => true,  'max_tokens' => 3000,  'temperature' => 0.0),
    'assistant'      => array('kind' => 'text',   'label' => 'Admin / instructor writing assistant',           'json' => false, 'max_tokens' => 2000,  'temperature' => 0.7),
    'narration'      => array('kind' => 'tts',    'label' => 'Narration voice for slide videos'),
    'avatar'         => array('kind' => 'avatar', 'label' => 'Presenter (avatar) video'),
    'clip'           => array('kind' => 'clip',   'label' => 'Generative b-roll clip'),
);

/*
| Guard rails that apply to every generation.
*/
$config['ha_ai_limits'] = array(
    'max_jobs_per_user_per_day' => 50,
    'max_lessons_per_course'    => 40,
    'http_timeout_seconds'      => 180,
    'job_max_attempts'          => 3,
    'job_lock_minutes'          => 30,   // a job locked longer than this is considered crashed
);

/*
| Slide video renderer.
| ffmpeg_path: leave '' to search PATH. On Windows: winget install Gyan.FFmpeg
*/
$config['ha_ai_video'] = array(
    'ffmpeg_path' => getenv('HA_FFMPEG') ?: '',
    'width'       => 1280,
    'height'      => 720,
    'fps'         => 25,
    'output_dir'  => 'uploads/ai_videos/',
    'font_latin'  => '',   // '' = first readable of the candidates below
    'font_arabic' => '',
    'font_candidates_latin'  => array(
        FCPATH . 'assets/academy/fonts/NotoSans-Regular.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        'C:/Windows/Fonts/segoeui.ttf',
        'C:/Windows/Fonts/arial.ttf',
    ),
    'font_candidates_arabic' => array(
        FCPATH . 'assets/academy/fonts/NotoSansArabic-Regular.ttf',
        '/usr/share/fonts/truetype/noto/NotoSansArabic-Regular.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        'C:/Windows/Fonts/tahoma.ttf',
        'C:/Windows/Fonts/arial.ttf',
    ),
    // Brand palette (Academy LMS violet, matching assets/academy/academy.css).
    'colors' => array('bg' => '#1e1b4b', 'accent' => '#8b5cf6', 'text' => '#ffffff', 'muted' => '#c7d2fe'),
);
