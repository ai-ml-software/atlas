<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Minimal PDF 1.4 writer: one full-page JPEG per page, nothing else.
 *
 * Certificates are rendered with GD, so the PDF only has to wrap finished
 * pictures. JPEG data is embedded as-is with DCTDecode (PDF readers decode
 * JPEG natively), which keeps the writer to a few dozen lines instead of a
 * PDF library with fonts, layout and a vendor directory.
 *
 * Each page is sized in points (1/72 inch) and the image is stretched to
 * fill it, so the image aspect ratio should match the page. A4 landscape is
 * 841.89 x 595.28 pt.
 */
class Ha_pdf {

    const PRODUCER = 'altus HK&P';

    /**
     * @param array $pages each array('jpeg' => binary, 'width_pt' => float, 'height_pt' => float)
     * @param array $meta  optional: 'title', 'author', 'subject'
     * @return string PDF binary
     */
    public function from_jpegs(array $pages, array $meta = array()) {
        if (!$pages) {
            throw new InvalidArgumentException('At least one page is required.');
        }

        $objects = array();   // object number => body (without "N 0 obj" / "endobj")
        $page_count = count($pages);
        $info_id = 3 + 3 * $page_count;
        $kids = array();

        $i = 0;
        foreach (array_values($pages) as $page) {
            if (!isset($page['jpeg']) || !is_string($page['jpeg']) || $page['jpeg'] === '') {
                throw new InvalidArgumentException('Page ' . ($i + 1) . ' has no JPEG data.');
            }
            $jpeg = $page['jpeg'];
            $info = @getimagesizefromstring($jpeg);
            if ($info === false || $info[2] !== IMAGETYPE_JPEG) {
                throw new InvalidArgumentException('Page ' . ($i + 1) . ' is not a valid JPEG.');
            }
            $px_w = (int) $info[0];
            $px_h = (int) $info[1];
            $channels = isset($info['channels']) ? (int) $info['channels'] : 3;
            $bpc = isset($info['bits']) ? (int) $info['bits'] : 8;
            $w = isset($page['width_pt']) ? (float) $page['width_pt'] : (float) $px_w;
            $h = isset($page['height_pt']) ? (float) $page['height_pt'] : (float) $px_h;

            if ($channels === 1) {
                $color = '/DeviceGray';
                $decode = '';
            } elseif ($channels === 4) {
                // Photoshop-style CMYK JPEGs are stored inverted.
                $color = '/DeviceCMYK';
                $decode = ' /Decode [1 0 1 0 1 0 1 0]';
            } else {
                $color = '/DeviceRGB';
                $decode = '';
            }

            $page_id = 3 + 3 * $i;
            $image_id = $page_id + 1;
            $content_id = $page_id + 2;
            $kids[] = $page_id . ' 0 R';

            $objects[$page_id] = '<< /Type /Page /Parent 2 0 R'
                . ' /MediaBox [0 0 ' . self::num($w) . ' ' . self::num($h) . ']'
                . ' /Resources << /XObject << /Im1 ' . $image_id . ' 0 R >> /ProcSet [/PDF /ImageB /ImageC] >>'
                . ' /Contents ' . $content_id . ' 0 R >>';

            $objects[$image_id] = '<< /Type /XObject /Subtype /Image'
                . ' /Width ' . $px_w . ' /Height ' . $px_h
                . ' /ColorSpace ' . $color . ' /BitsPerComponent ' . $bpc . $decode
                . ' /Filter /DCTDecode /Length ' . strlen($jpeg) . " >>\nstream\n" . $jpeg . "\nendstream";

            $content = 'q ' . self::num($w) . ' 0 0 ' . self::num($h) . " 0 0 cm /Im1 Do Q\n";
            $objects[$content_id] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream";
            $i++;
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $page_count . ' >>';

        $info = '<< /Producer ' . self::text(self::PRODUCER)
            . ' /CreationDate ' . self::text(self::date(isset($meta['time']) ? (int) $meta['time'] : time()));
        foreach (array('title' => 'Title', 'author' => 'Author', 'subject' => 'Subject') as $key => $name) {
            if (isset($meta[$key]) && (string) $meta[$key] !== '') {
                $info .= ' /' . $name . ' ' . self::text((string) $meta[$key]);
            }
        }
        $objects[$info_id] = $info . ' >>';
        ksort($objects);

        // Header plus a binary comment so transfer tools treat the file as binary.
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = array();
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xref_at = strlen($out);
        $count = count($objects) + 1;
        $out .= "xref\n0 " . $count . "\n0000000000 65535 f \n";
        for ($id = 1; $id < $count; $id++) {
            $out .= sprintf('%010d 00000 n ', $offsets[$id]) . "\n";
        }
        $file_id = md5($out);
        $out .= "trailer\n<< /Size " . $count . ' /Root 1 0 R /Info ' . $info_id . ' 0 R'
            . ' /ID [<' . $file_id . '> <' . $file_id . '>] >>' . "\n"
            . "startxref\n" . $xref_at . "\n%%EOF\n";
        return $out;
    }

    /** Single page from a GD image, A4 landscape by default. */
    public function from_gd($gd_image, $width_pt = 841.89, $height_pt = 595.28, $quality = 92, array $meta = array()) {
        ob_start();
        $ok = imagejpeg($gd_image, null, max(0, min(100, (int) $quality)));
        $jpeg = ob_get_clean();
        if (!$ok || $jpeg === '' || $jpeg === false) {
            throw new RuntimeException('Could not encode the image as JPEG.');
        }
        return $this->from_jpegs(array(array(
            'jpeg'      => $jpeg,
            'width_pt'  => $width_pt,
            'height_pt' => $height_pt,
        )), $meta);
    }

    private static function num($value) {
        $s = rtrim(rtrim(sprintf('%.4F', (float) $value), '0'), '.');
        return $s === '' || $s === '-0' ? '0' : $s;
    }

    /** D:YYYYMMDDHHmmSS+HH'mm' */
    private static function date($timestamp) {
        $offset = date('O', $timestamp);   // +0300
        return 'D:' . date('YmdHis', $timestamp) . substr($offset, 0, 3) . "'" . substr($offset, 3, 2) . "'";
    }

    /** Literal string for ASCII, UTF-16BE hex string with BOM otherwise (Arabic titles). */
    private static function text($value) {
        if (preg_match('/^[\x20-\x7E]*$/', $value)) {
            return '(' . strtr($value, array('\\' => '\\\\', '(' => '\\(', ')' => '\\)')) . ')';
        }
        $utf16 = function_exists('mb_convert_encoding')
            ? mb_convert_encoding($value, 'UTF-16BE', 'UTF-8')
            : iconv('UTF-8', 'UTF-16BE', $value);
        return '<FEFF' . strtoupper(bin2hex($utf16)) . '>';
    }
}
