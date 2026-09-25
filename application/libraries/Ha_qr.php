<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * QR Code encoder (ISO/IEC 18004), versions 1-40, byte mode.
 *
 * Certificates carry a verification link that has to scan from a printed
 * page, and pulling in a third-party QR package (or calling an external
 * image API that would see every certificate number) was not an option.
 * This is the whole encoder: UTF-8 text goes in as byte-mode data, the
 * smallest version that fits at the requested error-correction level is
 * chosen, Reed-Solomon codewords are computed over GF(256) and interleaved
 * per block, and all eight masks are scored with the standard penalty rules
 * so the one scanners find easiest is kept.
 *
 * Only byte mode is implemented. Numeric and alphanumeric modes would make
 * some symbols one version smaller; for URLs with lower-case letters they
 * would not apply anyway.
 *
 * The block layout follows the approach of Project Nayuki's reference
 * encoder: per-version EC codeword counts and block counts, with the short
 * and long block split derived from the raw module count.
 */
class Ha_qr {

    const MIN_VERSION = 1;
    const MAX_VERSION = 40;

    /** EC codewords per block, index = version (0 unused). */
    private static $ecc_per_block = array(
        'L' => array(-1,  7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30),
        'M' => array(-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28),
        'Q' => array(-1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30),
        'H' => array(-1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30),
    );

    /** Number of EC blocks, index = version (0 unused). */
    private static $num_blocks = array(
        'L' => array(-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4,  4,  4,  4,  4,  6,  6,  6,  6,  7,  8,  8,  9,  9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25),
        'M' => array(-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5,  5,  8,  9,  9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49),
        'Q' => array(-1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8,  8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68),
        'H' => array(-1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81),
    );

    /** Two-bit EC level indicator used in the format information. */
    private static $format_bits = array('L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2);

    private static $gf_exp = null;
    private static $gf_log = null;

    // Working state for the symbol being built.
    private $size;
    private $modules;
    private $is_function;

    /** Version the last matrix() call chose; handy for callers and tests. */
    public $last_version = null;

    /**
     * Rows of 0/1, dark = 1, without the quiet zone.
     *
     * @throws InvalidArgumentException when the text does not fit version 40.
     */
    public function matrix($text, $ecc = 'M') {
        $ecc = self::normalise_ecc($ecc);
        $bytes = array_values(unpack('C*', (string) $text) ?: array());
        $len = count($bytes);

        $version = null;
        for ($v = self::MIN_VERSION; $v <= self::MAX_VERSION; $v++) {
            $count_bits = $v <= 9 ? 8 : 16;
            $needed = 4 + $count_bits + 8 * $len;
            if ($len < (1 << $count_bits) && $needed <= self::num_data_codewords($v, $ecc) * 8) {
                $version = $v;
                break;
            }
        }
        if ($version === null) {
            throw new InvalidArgumentException(
                'Text is too long for a QR code at error-correction level ' . $ecc
                . ' (' . $len . ' bytes, maximum ' . self::capacity(self::MAX_VERSION, $ecc) . ').'
            );
        }
        $this->last_version = $version;

        $data = $this->encode_data($bytes, $version, $ecc);
        $codewords = $this->add_ecc_and_interleave($data, $version, $ecc);

        $this->size = $version * 4 + 17;
        $this->modules = array_fill(0, $this->size, array_fill(0, $this->size, 0));
        $this->is_function = $this->modules;
        $this->draw_function_patterns($version, $ecc);
        $this->draw_codewords($codewords);

        // Try every mask, keep the one with the lowest penalty.
        $best_mask = 0;
        $best_penalty = PHP_INT_MAX;
        $unmasked = $this->modules;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->modules = $unmasked;
            $this->apply_mask($mask);
            $this->draw_format_bits($ecc, $mask);
            $penalty = $this->penalty_score();
            if ($penalty < $best_penalty) {
                $best_penalty = $penalty;
                $best_mask = $mask;
            }
        }
        $this->modules = $unmasked;
        $this->apply_mask($best_mask);
        $this->draw_format_bits($ecc, $best_mask);

        $result = $this->modules;
        $this->modules = $this->is_function = null;
        return $result;
    }

    /** Largest byte-mode payload for a version and EC level. */
    public static function capacity($version, $ecc = 'M') {
        $ecc = self::normalise_ecc($ecc);
        $count_bits = $version <= 9 ? 8 : 16;
        return (int) floor((self::num_data_codewords($version, $ecc) * 8 - 4 - $count_bits) / 8);
    }

    /**
     * GD image of the symbol. $scale is pixels per module, $margin is the
     * quiet zone in modules (the standard asks for 4).
     */
    public function gd_image($text, $scale = 8, $margin = 4, $ecc = 'M', $fg = array(13, 27, 42), $bg = array(255, 255, 255)) {
        $matrix = $this->matrix($text, $ecc);
        $scale = max(1, (int) $scale);
        $margin = max(0, (int) $margin);
        $n = count($matrix);
        $px = ($n + 2 * $margin) * $scale;

        $img = imagecreatetruecolor($px, $px);
        $bg_color = imagecolorallocate($img, (int) $bg[0], (int) $bg[1], (int) $bg[2]);
        $fg_color = imagecolorallocate($img, (int) $fg[0], (int) $fg[1], (int) $fg[2]);
        imagefilledrectangle($img, 0, 0, $px - 1, $px - 1, $bg_color);

        for ($y = 0; $y < $n; $y++) {
            $x = 0;
            while ($x < $n) {
                if (!$matrix[$y][$x]) {
                    $x++;
                    continue;
                }
                $start = $x;
                while ($x < $n && $matrix[$y][$x]) {
                    $x++;
                }
                imagefilledrectangle(
                    $img,
                    ($start + $margin) * $scale,
                    ($y + $margin) * $scale,
                    ($x + $margin) * $scale - 1,
                    ($y + $margin + 1) * $scale - 1,
                    $fg_color
                );
            }
        }
        return $img;
    }

    /** PNG binary. */
    public function png($text, $scale = 8, $margin = 4, $ecc = 'M', $fg = array(13, 27, 42), $bg = array(255, 255, 255)) {
        $img = $this->gd_image($text, $scale, $margin, $ecc, $fg, $bg);
        ob_start();
        imagepng($img, null, 9);
        $png = ob_get_clean();
        imagedestroy($img);
        return $png;
    }

    /**
     * SVG document: a white background rectangle and one path in module
     * units, scaled by the width/height attributes, with crispEdges so
     * browsers do not anti-alias the seams between modules.
     */
    public function svg($text, $scale = 8, $margin = 4, $ecc = 'M', $fg = '#0D1B2A') {
        $matrix = $this->matrix($text, $ecc);
        $scale = max(1, (int) $scale);
        $margin = max(0, (int) $margin);
        $n = count($matrix);
        $dim = $n + 2 * $margin;
        $px = $dim * $scale;

        $d = '';
        for ($y = 0; $y < $n; $y++) {
            $x = 0;
            while ($x < $n) {
                if (!$matrix[$y][$x]) {
                    $x++;
                    continue;
                }
                $start = $x;
                while ($x < $n && $matrix[$y][$x]) {
                    $x++;
                }
                $d .= 'M' . ($start + $margin) . ' ' . ($y + $margin) . 'h' . ($x - $start) . 'v1h-' . ($x - $start) . 'z';
            }
        }

        $fg = htmlspecialchars((string) $fg, ENT_QUOTES, 'UTF-8');
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="' . $px . '" height="' . $px
            . '" viewBox="0 0 ' . $dim . ' ' . $dim . '" shape-rendering="crispEdges">'
            . '<rect width="100%" height="100%" fill="#FFFFFF"/>'
            . '<path fill="' . $fg . '" d="' . $d . '"/>'
            . '</svg>';
    }

    // ------------------------------------------------------------------
    // Data and error correction
    // ------------------------------------------------------------------

    private static function normalise_ecc($ecc) {
        $ecc = strtoupper((string) $ecc);
        if (!isset(self::$format_bits[$ecc])) {
            throw new InvalidArgumentException('Error-correction level must be L, M, Q or H.');
        }
        return $ecc;
    }

    /** Modules available for data and EC codewords, including remainder bits. */
    private static function num_raw_data_modules($version) {
        $result = (16 * $version + 128) * $version + 64;
        if ($version >= 2) {
            $num_align = intdiv($version, 7) + 2;
            $result -= (25 * $num_align - 10) * $num_align - 55;
            if ($version >= 7) {
                $result -= 36;
            }
        }
        return $result;
    }

    private static function num_data_codewords($version, $ecc) {
        return intdiv(self::num_raw_data_modules($version), 8)
            - self::$ecc_per_block[$ecc][$version] * self::$num_blocks[$ecc][$version];
    }

    private function encode_data(array $bytes, $version, $ecc) {
        $bits = array();
        $append = function ($value, $length) use (&$bits) {
            for ($i = $length - 1; $i >= 0; $i--) {
                $bits[] = ($value >> $i) & 1;
            }
        };
        $append(0x4, 4);                                   // byte mode
        $append(count($bytes), $version <= 9 ? 8 : 16);    // character count
        foreach ($bytes as $b) {
            $append($b, 8);
        }

        $capacity = self::num_data_codewords($version, $ecc) * 8;
        $append(0, min(4, $capacity - count($bits)));      // terminator
        $append(0, (8 - count($bits) % 8) % 8);            // byte align

        $data = array();
        for ($i = 0, $n = count($bits); $i < $n; $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | $bits[$i + $j];
            }
            $data[] = $byte;
        }
        for ($pad = 0xEC; count($data) < $capacity / 8; $pad ^= 0xEC ^ 0x11) {
            $data[] = $pad;
        }
        return $data;
    }

    private function add_ecc_and_interleave(array $data, $version, $ecc) {
        $num_blocks = self::$num_blocks[$ecc][$version];
        $block_ecc_len = self::$ecc_per_block[$ecc][$version];
        $raw_codewords = intdiv(self::num_raw_data_modules($version), 8);
        $num_short_blocks = $num_blocks - $raw_codewords % $num_blocks;
        $short_block_len = intdiv($raw_codewords, $num_blocks);

        $divisor = self::rs_divisor($block_ecc_len);
        $blocks = array();
        for ($i = 0, $k = 0; $i < $num_blocks; $i++) {
            $dat_len = $short_block_len - $block_ecc_len + ($i < $num_short_blocks ? 0 : 1);
            $dat = array_slice($data, $k, $dat_len);
            $k += $dat_len;
            $ecc_words = self::rs_remainder($dat, $divisor);
            if ($i < $num_short_blocks) {
                $dat[] = 0;   // placeholder so every block has the same length; skipped below
            }
            $blocks[] = array_merge($dat, $ecc_words);
        }

        $result = array();
        $block_len = count($blocks[0]);
        for ($i = 0; $i < $block_len; $i++) {
            for ($j = 0; $j < $num_blocks; $j++) {
                if ($i !== $short_block_len - $block_ecc_len || $j >= $num_short_blocks) {
                    $result[] = $blocks[$j][$i];
                }
            }
        }
        return $result;
    }

    private static function gf_init() {
        if (self::$gf_exp !== null) {
            return;
        }
        self::$gf_exp = array_fill(0, 512, 0);
        self::$gf_log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$gf_exp[$i] = $x;
            self::$gf_log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;   // x^8 + x^4 + x^3 + x^2 + 1
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$gf_exp[$i] = self::$gf_exp[$i - 255];
        }
    }

    private static function gf_mul($a, $b) {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$gf_exp[self::$gf_log[$a] + self::$gf_log[$b]];
    }

    /** Generator polynomial (x - a^0)(x - a^1)...(x - a^(degree-1)), leading 1 dropped. */
    private static function rs_divisor($degree) {
        self::gf_init();
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::gf_mul($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::gf_mul($root, 0x02);
        }
        return $result;
    }

    private static function rs_remainder(array $data, array $divisor) {
        $degree = count($divisor);
        $result = array_fill(0, $degree, 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($result);
            $result[] = 0;
            for ($i = 0; $i < $degree; $i++) {
                $result[$i] ^= self::gf_mul($divisor[$i], $factor);
            }
        }
        return $result;
    }

    // ------------------------------------------------------------------
    // Symbol layout
    // ------------------------------------------------------------------

    private function set_function($x, $y, $dark) {
        $this->modules[$y][$x] = $dark ? 1 : 0;
        $this->is_function[$y][$x] = 1;
    }

    private function draw_function_patterns($version, $ecc) {
        $size = $this->size;

        for ($i = 0; $i < $size; $i++) {
            $this->set_function(6, $i, $i % 2 === 0);
            $this->set_function($i, 6, $i % 2 === 0);
        }

        $this->draw_finder(3, 3);
        $this->draw_finder($size - 4, 3);
        $this->draw_finder(3, $size - 4);

        $positions = self::alignment_positions($version);
        $n = count($positions);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) {
                    continue;   // these overlap the finder patterns
                }
                $this->draw_alignment($positions[$i], $positions[$j]);
            }
        }

        // Reserve the format areas now; the real bits go in per mask.
        $this->draw_format_bits($ecc, 0);
        $this->draw_version($version);
    }

    private function draw_finder($cx, $cy) {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x >= 0 && $x < $this->size && $y >= 0 && $y < $this->size) {
                    $dist = max(abs($dx), abs($dy));
                    $this->set_function($x, $y, $dist !== 2 && $dist !== 4);
                }
            }
        }
    }

    private function draw_alignment($cx, $cy) {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->set_function($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    private static function alignment_positions($version) {
        if ($version === 1) {
            return array();
        }
        $size = $version * 4 + 17;
        $num_align = intdiv($version, 7) + 2;
        $step = intdiv($version * 8 + $num_align * 3 + 5, $num_align * 4 - 4) * 2;
        $result = array();
        for ($i = 0, $pos = $size - 7; $i < $num_align - 1; $i++, $pos -= $step) {
            array_unshift($result, $pos);
        }
        array_unshift($result, 6);
        return $result;
    }

    /** 15-bit format information: 5 data bits, BCH(15,5) remainder, fixed XOR mask. */
    private function draw_format_bits($ecc, $mask) {
        $data = (self::$format_bits[$ecc] << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | ($rem & 0x3FF)) ^ 0x5412;
        $size = $this->size;

        // Copy around the top-left finder.
        for ($i = 0; $i <= 5; $i++) {
            $this->set_function(8, $i, ($bits >> $i) & 1);
        }
        $this->set_function(8, 7, ($bits >> 6) & 1);
        $this->set_function(8, 8, ($bits >> 7) & 1);
        $this->set_function(7, 8, ($bits >> 8) & 1);
        for ($i = 9; $i < 15; $i++) {
            $this->set_function(14 - $i, 8, ($bits >> $i) & 1);
        }

        // Copy split between the other two finders.
        for ($i = 0; $i < 8; $i++) {
            $this->set_function($size - 1 - $i, 8, ($bits >> $i) & 1);
        }
        for ($i = 8; $i < 15; $i++) {
            $this->set_function(8, $size - 15 + $i, ($bits >> $i) & 1);
        }
        $this->set_function(8, $size - 8, true);   // the always-dark module
    }

    /** 18-bit version information, BCH(18,6), for versions 7 and up. */
    private function draw_version($version) {
        if ($version < 7) {
            return;
        }
        $rem = $version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = ($version << 12) | ($rem & 0xFFF);
        for ($i = 0; $i < 18; $i++) {
            $bit = ($bits >> $i) & 1;
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->set_function($a, $b, $bit);
            $this->set_function($b, $a, $bit);
        }
    }

    /** Zig-zag placement: two-module columns, right to left, alternating up and down, skipping column 6. */
    private function draw_codewords(array $codewords) {
        $size = $this->size;
        $total_bits = count($codewords) * 8;
        $i = 0;
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            $upward = (($right + 1) & 2) === 0;
            for ($vert = 0; $vert < $size; $vert++) {
                $y = $upward ? $size - 1 - $vert : $vert;
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    if (!$this->is_function[$y][$x] && $i < $total_bits) {
                        $this->modules[$y][$x] = ($codewords[$i >> 3] >> (7 - ($i & 7))) & 1;
                        $i++;
                    }
                    // Remainder bits stay light (0), as the standard requires.
                }
            }
        }
    }

    private function apply_mask($mask) {
        $size = $this->size;
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($this->is_function[$y][$x]) {
                    continue;
                }
                switch ($mask) {
                    case 0: $invert = ($x + $y) % 2 === 0; break;
                    case 1: $invert = $y % 2 === 0; break;
                    case 2: $invert = $x % 3 === 0; break;
                    case 3: $invert = ($x + $y) % 3 === 0; break;
                    case 4: $invert = (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0; break;
                    case 5: $invert = ($x * $y % 2 + $x * $y % 3) === 0; break;
                    case 6: $invert = (($x * $y % 2 + $x * $y % 3) % 2) === 0; break;
                    default: $invert = ((($x + $y) % 2 + $x * $y % 3) % 2) === 0; break;
                }
                if ($invert) {
                    $this->modules[$y][$x] ^= 1;
                }
            }
        }
    }

    /** The four penalty rules from section 7.8.3 (N1=3, N2=3, N3=40, N4=10). */
    private function penalty_score() {
        $m = $this->modules;
        $size = $this->size;
        $penalty = 0;

        // Rule 1 and rule 3, over rows and then columns.
        for ($pass = 0; $pass < 2; $pass++) {
            for ($a = 0; $a < $size; $a++) {
                $line = array();
                for ($b = 0; $b < $size; $b++) {
                    $line[] = $pass === 0 ? $m[$a][$b] : $m[$b][$a];
                }
                $run = 1;
                for ($b = 1; $b <= $size; $b++) {
                    if ($b < $size && $line[$b] === $line[$b - 1]) {
                        $run++;
                    } else {
                        if ($run >= 5) {
                            $penalty += 3 + ($run - 5);
                        }
                        $run = 1;
                    }
                }
                $str = implode('', $line);
                $penalty += 40 * substr_count($str, '10111010000');
                $penalty += 40 * substr_count($str, '00001011101');
                // Patterns touching the quiet zone (light on the outside).
                if (substr($str, 0, 7) === '1011101' && substr($str, 7, 4) !== '0000') {
                    $penalty += 40;
                }
                if (substr($str, -7) === '1011101' && substr($str, -11, 4) !== '0000') {
                    $penalty += 40;
                }
            }
        }

        // Rule 2: 2x2 blocks of one colour.
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $c = $m[$y][$x];
                if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                    $penalty += 3;
                }
            }
        }

        // Rule 4: balance of dark and light.
        $dark = 0;
        foreach ($m as $row) {
            $dark += array_sum($row);
        }
        $total = $size * $size;
        $k = (int) ceil(abs($dark * 20 - $total * 10) / $total) - 1;
        $penalty += max(0, $k) * 10;

        return $penalty;
    }
}
