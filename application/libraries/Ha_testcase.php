<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Minimal assertion base for Hospitality Academy tests.
 *
 * The project has no composer dev dependencies and the environment is offline,
 * so this stands in for PHPUnit. It supports the same shape of work: a class
 * per subject, one public test_* method per behaviour, setUp/tearDown, and
 * assertions that record a failure rather than aborting the whole run.
 */
abstract class Ha_testcase {

    /** @var CI_Controller */
    protected $CI;

    /** @var CI_DB_query_builder */
    protected $db;

    /** Failures recorded by the currently running test method. */
    public $failures = array();

    /** Number of assertions executed, so an empty test cannot look like a pass. */
    public $assertions = 0;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->db = $this->CI->db;
    }

    /** Human readable name for the report. */
    public function name() {
        return get_class($this);
    }

    public function setUp() {}

    public function tearDown() {}

    /** Runs once before the first test of the class. */
    public function setUpClass() {}

    /** Runs once after the last test of the class. */
    public function tearDownClass() {}

    // ------------------------------------------------------------- assertions

    protected function record($ok, $message, $detail = '') {
        $this->assertions++;
        if (!$ok) {
            $this->failures[] = rtrim($message . ($detail ? ' -- ' . $detail : ''));
        }
        return $ok;
    }

    protected function assertTrue($value, $message = 'Expected true') {
        return $this->record($value === true || $value === 1 || $value === '1', $message,
            'got ' . $this->describe($value));
    }

    protected function assertFalse($value, $message = 'Expected false') {
        return $this->record($value === false || $value === 0 || $value === '0' || $value === null,
            $message, 'got ' . $this->describe($value));
    }

    protected function assertEquals($expected, $actual, $message = 'Values differ') {
        return $this->record($expected == $actual, $message,
            'expected ' . $this->describe($expected) . ', got ' . $this->describe($actual));
    }

    protected function assertSame($expected, $actual, $message = 'Values are not identical') {
        return $this->record($expected === $actual, $message,
            'expected ' . $this->describe($expected) . ', got ' . $this->describe($actual));
    }

    protected function assertNotEquals($expected, $actual, $message = 'Values should differ') {
        return $this->record($expected != $actual, $message, 'both ' . $this->describe($actual));
    }

    protected function assertNull($value, $message = 'Expected null') {
        return $this->record($value === null, $message, 'got ' . $this->describe($value));
    }

    protected function assertNotNull($value, $message = 'Expected a value') {
        return $this->record($value !== null, $message);
    }

    protected function assertEmpty($value, $message = 'Expected empty') {
        return $this->record(empty($value), $message, 'got ' . $this->describe($value));
    }

    protected function assertNotEmpty($value, $message = 'Expected not empty') {
        return $this->record(!empty($value), $message);
    }

    protected function assertCount($expected, $value, $message = 'Wrong count') {
        $n = is_array($value) || $value instanceof Countable ? count($value) : -1;
        return $this->record($n === (int) $expected, $message, 'expected ' . $expected . ', got ' . $n);
    }

    protected function assertContains($needle, $haystack, $message = 'Value not found') {
        if (is_array($haystack)) {
            return $this->record(in_array($needle, $haystack), $message,
                'looking for ' . $this->describe($needle));
        }
        return $this->record(strpos((string) $haystack, (string) $needle) !== false, $message,
            'looking for ' . $this->describe($needle));
    }

    protected function assertNotContains($needle, $haystack, $message = 'Value should be absent') {
        if (is_array($haystack)) {
            return $this->record(!in_array($needle, $haystack), $message);
        }
        return $this->record(strpos((string) $haystack, (string) $needle) === false, $message,
            'found ' . $this->describe($needle));
    }

    protected function assertGreaterThan($floor, $actual, $message = 'Value too small') {
        return $this->record($actual > $floor, $message, $this->describe($actual) . ' <= ' . $this->describe($floor));
    }

    protected function assertGreaterThanOrEqual($floor, $actual, $message = 'Value too small') {
        return $this->record($actual >= $floor, $message, $this->describe($actual) . ' < ' . $this->describe($floor));
    }

    protected function assertLessThanOrEqual($ceiling, $actual, $message = 'Value too large') {
        return $this->record($actual <= $ceiling, $message, $this->describe($actual) . ' > ' . $this->describe($ceiling));
    }

    protected function assertMatches($pattern, $subject, $message = 'Pattern did not match') {
        return $this->record((bool) preg_match($pattern, (string) $subject), $message,
            'pattern ' . $pattern);
    }

    /** Asserts a row exists in a table. */
    protected function assertDatabaseHas($table, array $where, $message = null) {
        $count = $this->db->where($where)->count_all_results($table);
        return $this->record($count > 0, $message ?: ('Expected a row in ' . $table),
            json_encode($where, JSON_UNESCAPED_UNICODE));
    }

    protected function assertDatabaseMissing($table, array $where, $message = null) {
        $count = $this->db->where($where)->count_all_results($table);
        return $this->record($count === 0, $message ?: ('Expected no row in ' . $table),
            json_encode($where, JSON_UNESCAPED_UNICODE) . ' matched ' . $count);
    }

    protected function assertDatabaseCount($table, $expected, array $where = array(), $message = null) {
        if ($where) {
            $this->db->where($where);
        }
        $count = $this->db->count_all_results($table);
        return $this->record($count === (int) $expected, $message ?: ('Wrong row count in ' . $table),
            'expected ' . $expected . ', got ' . $count);
    }

    /** Asserts that calling $fn throws. */
    protected function assertThrows(callable $fn, $message = 'Expected an exception') {
        try {
            $fn();
        } catch (Exception $e) {
            return $this->record(true, $message);
        } catch (Error $e) {
            return $this->record(true, $message);
        }
        return $this->record(false, $message, 'nothing was thrown');
    }

    protected function fail($message) {
        return $this->record(false, $message);
    }

    protected function describe($value) {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_array($value)) {
            return 'array(' . count($value) . ')';
        }
        if (is_object($value)) {
            return get_class($value);
        }
        $s = (string) $value;
        return strlen($s) > 120 ? substr($s, 0, 117) . '...' : '"' . $s . '"';
    }
}
