<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Hospitality Academy home layout for the Academy LMS front end.
 *
 * Selected in the admin panel under Home Page Builder and stored as
 * frontend_settings.home_page = home_hospitality.
 *
 * It borrows the public academy stylesheet rather than restating its colours,
 * which is the point: the two front ends were drifting into two different
 * violets and two different card shapes, and one stylesheet is the only thing
 * that actually keeps them together. Everything below is built from the
 * components that stylesheet already defines, so a change to the theme
 * reaches this page for free.
 *
 * Every number on this page is counted from the catalogue at render time.
 * Nothing here is a marketing estimate, and there is no testimonial, because
 * a real quote needs a real, consenting person.
 */

$ha_courses    = $this->db->where('status', 'active')->count_all_results('course');
$ha_lessons    = $this->db->count_all('lesson');
$ha_quizzes    = $this->db->where('lesson_type', 'quiz')->count_all_results('lesson');
$ha_categories = $this->crud_model->get_categories()->result_array();
// get_top_courses() returns a query object, and it filters on is_top_course,
// which nothing in this catalogue sets. Fall back to the newest published
// courses so the section is never empty on a working catalogue.
// One course per department rather than the first six in the table. The
// thumbnails are department photographs, so six front office courses in a
// row showed the same lobby three times and read as a broken grid.
$ha_top = array();
$ha_seen = array();
$ha_pool = $this->db->where('status', 'active')->order_by('id', 'ASC')
    ->get('course')->result_array();
foreach ($ha_pool as $ha_row) {
    $ha_key = $ha_row['category_id'] . ':' . $ha_row['sub_category_id'];
    if (isset($ha_seen[$ha_row['category_id']])) {
        continue;
    }
    $ha_seen[$ha_row['category_id']] = true;
    $ha_top[] = $ha_row;
    if (count($ha_top) === 6) {
        break;
    }
}
?>

<link rel="stylesheet" href="<?php echo base_url('assets/academy/altus-fonts.css'); ?>">
<?php /* Brand tokens must load before the theme, which defines its roles in terms of them. */ ?>
<link rel="stylesheet" href="<?php echo base_url('assets/academy/altus-tokens.css'); ?>">
<link rel="stylesheet" href="<?php echo base_url('assets/academy/academy.css'); ?>">
<style>
    /* Only what this layout adds. Everything else comes from academy.css. */

    /* The shipped theme sets its footer headings at body size, weight 500,
       with no tracking, so they rank no higher than the text beneath them.
       Corrected here rather than in assets/frontend/default-new/css/style.css,
       which is vendor code this project does not own. The treatment is the
       guide's phase-label pattern: rank by weight and tracking, not size. */
    .ft2-title { font-weight: 700; letter-spacing: .08em; }

    .ha-lms-hero { position: relative; overflow: hidden; }
    .ha-lms-hero__grid {
        display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, .9fr);
        gap: clamp(1.6rem, 1rem + 3vw, 3.4rem); align-items: center;
    }
    .ha-lms-hero h1 { font-size: clamp(2.1rem, 1.4rem + 2.8vw, 3.4rem); line-height: 1.08; margin: 0 0 1rem; }
    .ha-lms-hero__lede { font-size: 1.12rem; color: var(--ha-ink-soft); max-width: 48ch; }
    .ha-lms-hero__art { border-radius: var(--ha-radius-lg); overflow: hidden; aspect-ratio: 4/3;
        box-shadow: 0 24px 60px rgba(13, 12, 35, .18); }
    .ha-lms-hero__art img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .ha-lms-dept { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: .8rem; }
    .ha-lms-dept a {
        display: flex; align-items: center; gap: .6rem; text-decoration: none;
        background: var(--ha-canvas); border: 1px solid var(--ha-line);
        border-radius: var(--ha-radius); padding: .8rem 1rem; color: var(--ha-ink); font-weight: 600;
        transition: border-color .18s ease, transform .18s ease;
    }
    .ha-lms-dept a:hover { border-color: var(--ha-accent); transform: translateY(-2px); }
    /* Deepened gold: the icon is meaningful, and Executive Gold on a light
       card is 2.3:1. --ha-accent-on-light keeps the gold family at 5.6:1. */
    .ha-lms-dept i { color: var(--ha-accent-on-light); }
    @media (max-width: 880px) {
        .ha-lms-hero__grid { grid-template-columns: 1fr; }
        .ha-lms-hero__art { order: -1; aspect-ratio: 16/9; }
    }
</style>

<section class="ha-hero ha-lms-hero">
    <div class="ha-shell ha-lms-hero__grid">
        <div>
            <p><span class="ha-pill ha-pill--accent"><?php echo site_phrase('Hotel training in Arabic and English'); ?></span></p>
            <h1><?php echo get_settings('system_title'); ?></h1>
            <p class="ha-lms-hero__lede"><?php echo get_settings('website_description'); ?></p>
            <div class="ha-hero__actions">
                <a class="ha-btn" href="<?php echo site_url('home/courses'); ?>"><?php echo site_phrase('Browse courses'); ?></a>
                <a class="ha-btn ha-btn--ghost" href="<?php echo base_url('en/hotels'); ?>"><?php echo site_phrase('For Hotels'); ?></a>
            </div>
        </div>
        <div class="ha-lms-hero__art">
            <?php $ha_art = $this->db->select('file_path')->where('subject', 'page-home')->get('ha_media')->row_array(); ?>
            <?php if ($ha_art): ?>
                <img src="<?php echo base_url($ha_art['file_path']); ?>" alt="" loading="eager" decoding="async">
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Counted live from the catalogue, not typed in. -->
<section class="ha-factband">
    <div class="ha-shell">
        <ul class="ha-factband__list">
            <li><span class="ha-factband__n"><?php echo $ha_courses; ?></span><span class="ha-factband__l"><?php echo site_phrase('courses'); ?></span></li>
            <li><span class="ha-factband__n"><?php echo $ha_lessons; ?></span><span class="ha-factband__l"><?php echo site_phrase('lessons'); ?></span></li>
            <li><span class="ha-factband__n"><?php echo $ha_quizzes; ?></span><span class="ha-factband__l"><?php echo site_phrase('assessments'); ?></span></li>
            <li><span class="ha-factband__n"><?php echo count($ha_categories); ?></span><span class="ha-factband__l"><?php echo site_phrase('departments'); ?></span></li>
        </ul>
        <p class="ha-factband__note"><?php echo site_phrase('Counted from the published catalogue as this page loaded'); ?>.</p>
    </div>
</section>

<section class="ha-section">
    <div class="ha-shell">
        <div class="ha-section__head">
            <h2><?php echo site_phrase('Training by hotel department'); ?></h2>
            <p><?php echo site_phrase('Pick the department someone actually works in, and the courses follow the shift rather than the job title'); ?>.</p>
        </div>
        <div class="ha-lms-dept">
            <?php foreach ($ha_categories as $ha_cat): ?>
                <a href="<?php echo site_url('home/courses?category=' . $ha_cat['slug']); ?>">
                    <i class="<?php echo $ha_cat['font_awesome_class']; ?>"></i>
                    <span><?php echo $ha_cat['name']; ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($ha_top): ?>
<section class="ha-section ha-section--tint">
    <div class="ha-shell">
        <div class="ha-section__head">
            <h2><?php echo site_phrase('Courses'); ?></h2>
            <a class="ha-btn ha-btn--ghost" href="<?php echo site_url('home/courses'); ?>"><?php echo site_phrase('All courses'); ?></a>
        </div>
        <div class="ha-grid ha-grid--3">
            <?php foreach ($ha_top as $ha_course): ?>
                <?php $ha_url = site_url('home/course/' . slugify($ha_course['title']) . '/' . $ha_course['id']); ?>
                <article class="ha-card ha-card--media">
                    <a class="ha-card__cover" href="<?php echo $ha_url; ?>" tabindex="-1" aria-hidden="true">
                        <img src="<?php echo $this->crud_model->get_course_thumbnail_url($ha_course['id']); ?>"
                             alt="" loading="lazy" decoding="async">
                    </a>
                    <h3 class="ha-card__title"><a href="<?php echo $ha_url; ?>"><?php echo $ha_course['title']; ?></a></h3>
                    <p><?php
                        // The text helper is not autoloaded on this front end,
                        // so trim here rather than depend on character_limiter.
                        $ha_desc = trim(strip_tags((string) $ha_course['short_description']));
                        echo html_escape(mb_strlen($ha_desc) > 110 ? mb_substr($ha_desc, 0, 110) . '...' : $ha_desc);
                    ?></p>
                    <div class="ha-card__meta">
                        <span class="ha-pill ha-pill--accent"><?php echo ucfirst($ha_course['level']); ?></span>
                        <?php if ($ha_course['is_free_course'] == 1): ?>
                            <span class="ha-pill ha-pill--ok"><?php echo site_phrase('Free'); ?></span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="ha-section">
    <div class="ha-shell">
        <div class="ha-section__head">
            <h2><?php echo site_phrase('How the academy works'); ?></h2>
        </div>
        <ol class="ha-steps">
            <?php
            $ha_steps = array(
                array(site_phrase('Assign'),  site_phrase('A manager assigns the courses that match each role')),
                array(site_phrase('Learn'),   site_phrase('Staff work through the standard in Arabic or English')),
                array(site_phrase('Assess'),  site_phrase('Every course ends in a short assessment that has to be passed')),
                array(site_phrase('Certify'), site_phrase('The certificate carries a code anyone can verify')),
            );
            foreach ($ha_steps as $ha_i => $ha_step): ?>
                <li class="ha-steps__item">
                    <span class="ha-steps__n"><?php echo $ha_i + 1; ?></span>
                    <div>
                        <h3><?php echo $ha_step[0]; ?></h3>
                        <p><?php echo $ha_step[1]; ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<section class="ha-close">
    <div class="ha-shell">
        <div class="ha-close__inner">
            <h2><?php echo site_phrase('Train your team to one standard'); ?></h2>
            <p><?php echo site_phrase('Hotels buy seats, assign courses by department and follow completion per employee'); ?>.</p>
            <div class="ha-close__actions">
                <a class="ha-btn ha-btn--invert" href="<?php echo base_url('en/hotels'); ?>"><?php echo site_phrase('For Hotels'); ?></a>
                <a class="ha-btn ha-btn--outline" href="<?php echo base_url('en/verify'); ?>"><?php echo site_phrase('Verify a certificate'); ?></a>
            </div>
        </div>
    </div>
</section>
