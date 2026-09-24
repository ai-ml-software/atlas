<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Arabic text for raster output (slide images).
 *
 * GD draws code points left to right and never joins letters, so Arabic
 * passed to imagettftext comes out as disconnected isolated letters in the
 * wrong order: unreadable, and exactly the kind of defect a native reader
 * notices in the first second. Two steps fix it:
 *
 *   shape()   pick each letter's contextual form (isolated, final, initial,
 *             medial) from the Arabic Presentation Forms-B block, including
 *             the mandatory lam-alef ligatures. Logical order is kept.
 *   visual()  reorder one already-wrapped line for left-to-right drawing:
 *             Arabic runs reversed, Latin words and numbers kept readable.
 *
 * Wrap in logical order first, then call visual() per line; reordering a
 * whole paragraph before wrapping would put the first words on the last line.
 * Harakat are dropped: GD cannot position combining marks.
 */
class Ha_arabic {

    /** code point => array(isolated, final, initial, medial); null = form does not exist */
    private static $forms = array(
        0x0621 => array(0xFE80, null, null, null),
        0x0622 => array(0xFE81, 0xFE82, null, null),
        0x0623 => array(0xFE83, 0xFE84, null, null),
        0x0624 => array(0xFE85, 0xFE86, null, null),
        0x0625 => array(0xFE87, 0xFE88, null, null),
        0x0626 => array(0xFE89, 0xFE8A, 0xFE8B, 0xFE8C),
        0x0627 => array(0xFE8D, 0xFE8E, null, null),
        0x0628 => array(0xFE8F, 0xFE90, 0xFE91, 0xFE92),
        0x0629 => array(0xFE93, 0xFE94, null, null),
        0x062A => array(0xFE95, 0xFE96, 0xFE97, 0xFE98),
        0x062B => array(0xFE99, 0xFE9A, 0xFE9B, 0xFE9C),
        0x062C => array(0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0),
        0x062D => array(0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4),
        0x062E => array(0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8),
        0x062F => array(0xFEA9, 0xFEAA, null, null),
        0x0630 => array(0xFEAB, 0xFEAC, null, null),
        0x0631 => array(0xFEAD, 0xFEAE, null, null),
        0x0632 => array(0xFEAF, 0xFEB0, null, null),
        0x0633 => array(0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4),
        0x0634 => array(0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8),
        0x0635 => array(0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC),
        0x0636 => array(0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0),
        0x0637 => array(0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4),
        0x0638 => array(0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8),
        0x0639 => array(0xFEC9, 0xFECA, 0xFECB, 0xFECC),
        0x063A => array(0xFECD, 0xFECE, 0xFECF, 0xFED0),
        0x0640 => array(0x0640, 0x0640, 0x0640, 0x0640),
        0x0641 => array(0xFED1, 0xFED2, 0xFED3, 0xFED4),
        0x0642 => array(0xFED5, 0xFED6, 0xFED7, 0xFED8),
        0x0643 => array(0xFED9, 0xFEDA, 0xFEDB, 0xFEDC),
        0x0644 => array(0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0),
        0x0645 => array(0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4),
        0x0646 => array(0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8),
        0x0647 => array(0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC),
        0x0648 => array(0xFEED, 0xFEEE, null, null),
        0x0649 => array(0xFEEF, 0xFEF0, null, null),
        0x064A => array(0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4),
    );

    /** alef variant => array(isolated lam-alef, final lam-alef) */
    private static $lam_alef = array(
        0x0622 => array(0xFEF5, 0xFEF6),
        0x0623 => array(0xFEF7, 0xFEF8),
        0x0625 => array(0xFEF9, 0xFEFA),
        0x0627 => array(0xFEFB, 0xFEFC),
    );

    public static function has_arabic($text) {
        return (bool) preg_match('/[\x{0600}-\x{06FF}]/u', (string) $text);
    }

    public static function shape($text) {
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', (string) $text);
        $cps = self::code_points($text);
        $n = count($cps);
        $out = array();

        for ($i = 0; $i < $n; $i++) {
            $c = $cps[$i];
            if (!isset(self::$forms[$c])) {
                $out[] = $c;
                continue;
            }
            $prev = $i > 0 ? $cps[$i - 1] : null;
            $joins_prev = $prev !== null && self::joins_forward($prev);

            // Lam followed by an alef is one mandatory ligature.
            if ($c === 0x0644 && $i + 1 < $n && isset(self::$lam_alef[$cps[$i + 1]])) {
                $pair = self::$lam_alef[$cps[$i + 1]];
                $out[] = $joins_prev ? $pair[1] : $pair[0];
                $i++;
                continue;
            }

            $next = $i + 1 < $n ? $cps[$i + 1] : null;
            $joins_next = $next !== null && isset(self::$forms[$next]) && self::$forms[$next][1] !== null
                && self::joins_forward($c);

            $f = self::$forms[$c];
            if ($joins_prev && $joins_next) {
                $form = $f[3];
            } elseif ($joins_prev) {
                $form = $f[1];
            } elseif ($joins_next) {
                $form = $f[2];
            } else {
                $form = $f[0];
            }
            $out[] = $form !== null ? $form : $f[0];
        }
        return self::from_code_points($out);
    }

    /** Can this letter connect to the letter after it? */
    private static function joins_forward($c) {
        return isset(self::$forms[$c]) && self::$forms[$c][2] !== null;
    }

    /** Reorder one line (already shaped) for left-to-right drawing. */
    public static function visual($line) {
        $cps = array_reverse(self::code_points($line));
        // Re-reverse runs that must read left to right: Latin words, digits,
        // and the punctuation inside them (1.5, 24/7, e-mail).
        $out = array();
        $run = array();
        foreach ($cps as $c) {
            $ltr = ($c >= 0x30 && $c <= 0x39) || ($c >= 0x41 && $c <= 0x5A) || ($c >= 0x61 && $c <= 0x7A)
                || ($c >= 0x660 && $c <= 0x669)
                || ($run && in_array($c, array(0x2E, 0x2C, 0x3A, 0x2F, 0x2D, 0x25, 0x40, 0x26), true));
            if ($ltr) {
                $run[] = $c;
                continue;
            }
            if ($run) {
                $out = array_merge($out, self::trim_run(array_reverse($run), $trail));
                $out = array_merge($out, $trail);
                $run = array();
            }
            $out[] = self::mirror($c);
        }
        if ($run) {
            $out = array_merge($out, self::trim_run(array_reverse($run), $trail));
            $out = array_merge($out, $trail);
        }
        return self::from_code_points($out);
    }

    /** Punctuation that ended up at the start of a reversed LTR run belongs outside it. */
    private static function trim_run(array $run, &$trail) {
        $trail = array();
        while ($run && !(($run[0] >= 0x30 && $run[0] <= 0x39) || ($run[0] >= 0x41 && $run[0] <= 0x5A) || ($run[0] >= 0x61 && $run[0] <= 0x7A) || ($run[0] >= 0x660 && $run[0] <= 0x669))) {
            array_unshift($trail, array_shift($run));
        }
        return $run;
    }

    private static function mirror($c) {
        $pairs = array(0x28 => 0x29, 0x29 => 0x28, 0x5B => 0x5D, 0x5D => 0x5B, 0x7B => 0x7D, 0x7D => 0x7B, 0x3C => 0x3E, 0x3E => 0x3C, 0xAB => 0xBB, 0xBB => 0xAB);
        return isset($pairs[$c]) ? $pairs[$c] : $c;
    }

    private static function code_points($text) {
        $out = array();
        foreach (preg_split('//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            $out[] = mb_ord($ch, 'UTF-8');
        }
        return $out;
    }

    private static function from_code_points(array $cps) {
        $s = '';
        foreach ($cps as $c) {
            $s .= mb_chr($c, 'UTF-8');
        }
        return $s;
    }
}
