<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Hospitality Academy test runner.
 *
 *   php index.php ha_test run                 run everything
 *   php index.php ha_test run auth            run files matching "auth"
 *   php index.php ha_test list                list discovered test classes
 *
 * Tests live in application/tests/*.php, one class per file extending
 * Ha_testcase, with public methods named test_*.
 *
 * The runner switches the database connection to HA_TEST_DB (default
 * atlas_hospitality_test) so a test run can never touch working data. The test
 * database is rebuilt from the migrations on every run, then seeded.
 */
class Ha_test extends CI_Controller {

    private $test_db;
    private $passed = 0;
    private $failed = 0;
    private $assertions = 0;
    private $failures = array();
    private $started;

    public function __construct() {
        parent::__construct();
        if (!is_cli()) {
            show_404();
        }
        @set_time_limit(0);
        ini_set('memory_limit', '512M');
        $this->load->database();
        $this->test_db = getenv('HA_TEST_DB') ? getenv('HA_TEST_DB') : 'atlas_hospitality_test';
    }

    private function out($line = '') {
        fwrite(STDOUT, $line . PHP_EOL);
    }

    public function index() {
        $this->run();
    }

    public function list_tests() {
        foreach ($this->discover() as $file => $class) {
            $this->out(str_pad($class, 40) . basename($file));
        }
    }

    private function discover($filter = null) {
        $found = array();
        $files = glob(APPPATH . 'tests/*.php');
        sort($files);
        foreach ($files as $file) {
            $base = basename($file, '.php');
            if ($filter !== null && stripos($base, $filter) === false) {
                continue;
            }
            $found[$file] = 'Test_' . strtolower(preg_replace('/^\d+_/', '', $base));
        }
        return $found;
    }

    /**
     * Rebuilds the test database from the real migrations so the schema under
     * test is the schema that ships, then applies the base install dump for the
     * legacy tables the academy depends on (users, settings).
     */
    private function prepare_database() {
        // Read the connection straight from the config file: the driver does
        // not keep the credentials on public properties in every build.
        $db = array();
        require APPPATH . 'config/database.php';
        $conf = $db[$active_group];
        $host = $conf['hostname'];
        $user = $conf['username'];
        $pass = $conf['password'];

        $link = @mysqli_connect($host, $user, $pass);
        if (!$link) {
            fwrite(STDERR, 'ERROR: cannot connect to MySQL to build the test database.' . PHP_EOL);
            exit(1);
        }
        mysqli_query($link, 'DROP DATABASE IF EXISTS `' . $this->test_db . '`');
        mysqli_query($link, 'CREATE DATABASE `' . $this->test_db . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        mysqli_select_db($link, $this->test_db);

        // Legacy Academy LMS tables (users, settings, sessions) come from the
        // shipped install dump so the academy sits on the same foundation.
        $sql = file_get_contents(FCPATH . 'uploads/install.sql');
        $statement = '';
        foreach (explode("\n", $sql) as $line) {
            if (substr(ltrim($line), 0, 2) === '--' || trim($line) === '') {
                continue;
            }
            $statement .= $line . "\n";
            if (substr(rtrim($line), -1) === ';') {
                mysqli_query($link, $statement);
                $statement = '';
            }
        }
        mysqli_close($link);

        // Point the application connection at the test database for this run.
        $this->db->close();
        $this->db->database = $this->test_db;
        $this->db->initialize();
    }

    private function migrate_and_seed() {
        require_once APPPATH . 'libraries/Ha_migration.php';
        $files = glob(APPPATH . 'migrations/*.php');
        sort($files);
        foreach ($files as $file) {
            require_once $file;
            preg_match('/^(\d+)_(.+)$/', basename($file, '.php'), $m);
            $class = 'Migration_' . ucfirst(strtolower($m[2]));
            $migration = new $class();
            $migration->up();
        }

        require_once APPPATH . 'libraries/Ha_seeder.php';
        $seeds = glob(APPPATH . 'seeds/*.php');
        sort($seeds);
        foreach ($seeds as $file) {
            require_once $file;
            $parts = explode('_', basename($file, '.php'), 2);
            $class = 'Seed_' . strtolower($parts[1]);
            $seeder = new $class();
            $seeder->run($this->db);
        }
    }

    public function run($filter = null) {
        $this->started = microtime(true);
        $this->out('Hospitality Academy test suite');
        $this->out('database: ' . $this->test_db);
        $this->out(str_repeat('-', 72));

        $this->prepare_database();
        $this->migrate_and_seed();

        require_once APPPATH . 'libraries/Ha_testcase.php';
        $classes = $this->discover($filter);
        if (!$classes) {
            fwrite(STDERR, 'ERROR: no test files matched.' . PHP_EOL);
            exit(1);
        }

        foreach ($classes as $file => $class) {
            require_once $file;
            if (!class_exists($class, FALSE)) {
                $this->failures[] = basename($file) . ': class ' . $class . ' not found';
                $this->failed++;
                continue;
            }
            $this->run_class($class);
        }

        return $this->report();
    }

    private function run_class($class) {
        $instance = new $class();
        $this->out($instance->name());

        $methods = array_filter(get_class_methods($instance), function ($m) {
            return strpos($m, 'test_') === 0;
        });
        sort($methods);

        $instance->setUpClass();
        foreach ($methods as $method) {
            $case = new $class();
            $case->failures = array();
            $case->assertions = 0;
            $label = str_replace('_', ' ', substr($method, 5));

            try {
                $case->setUp();
                $case->$method();
                $case->tearDown();
            } catch (Exception $e) {
                $case->failures[] = 'threw ' . get_class($e) . ': ' . $e->getMessage()
                    . ' at ' . basename($e->getFile()) . ':' . $e->getLine();
            } catch (Error $e) {
                $case->failures[] = 'fatal ' . get_class($e) . ': ' . $e->getMessage()
                    . ' at ' . basename($e->getFile()) . ':' . $e->getLine();
            }

            $this->assertions += $case->assertions;

            if ($case->assertions === 0 && !$case->failures) {
                $case->failures[] = 'test made no assertions';
            }

            if ($case->failures) {
                $this->failed++;
                $this->out('  FAIL  ' . $label);
                foreach ($case->failures as $f) {
                    $this->out('        ' . $f);
                    $this->failures[] = $class . '::' . $method . ' - ' . $f;
                }
            } else {
                $this->passed++;
                $this->out('  pass  ' . $label . '  (' . $case->assertions . ')');
            }
        }
        $instance->tearDownClass();
        $this->out('');
    }

    private function report() {
        $elapsed = number_format(microtime(true) - $this->started, 2);
        $this->out(str_repeat('-', 72));
        $this->out('tests: ' . ($this->passed + $this->failed)
            . '   passed: ' . $this->passed
            . '   failed: ' . $this->failed
            . '   assertions: ' . $this->assertions
            . '   time: ' . $elapsed . 's');

        if ($this->failed) {
            $this->out('');
            $this->out('FAILURES');
            foreach ($this->failures as $f) {
                $this->out('  ' . $f);
            }
            exit(1);
        }
        $this->out('OK');
        return 0;
    }
}
