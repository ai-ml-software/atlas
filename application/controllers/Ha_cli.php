<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Hospitality Academy command line runner.
 *
 * Usage (from the application root):
 *   php index.php ha_cli migrate
 *   php index.php ha_cli rollback [target_version]
 *   php index.php ha_cli seed [name_fragment]
 *   php index.php ha_cli fresh
 *   php index.php ha_cli status
 *
 * CodeIgniter 3.1.9 ships a Migration library that calls
 * is_callable(array($class, 'up')), which PHP 8 evaluates as FALSE for
 * non-static methods, so the bundled library cannot run on this PHP build.
 * This runner keeps the same file format and ha_migration bookkeeping table
 * instead of patching system/.
 */
class Ha_cli extends CI_Controller {

    const TABLE = 'ha_migration';

    public function __construct() {
        parent::__construct();
        if (!is_cli()) {
            show_404();
        }
        @set_time_limit(0);
        $this->load->database();
        $this->ensure_table();
    }

    private function ensure_table() {
        $this->db->query('CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
            version VARCHAR(30) NOT NULL,
            name VARCHAR(190) NOT NULL,
            ran_at DATETIME NOT NULL,
            PRIMARY KEY (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    private function out($line) {
        fwrite(STDOUT, $line . PHP_EOL);
    }

    private function fail($line) {
        fwrite(STDERR, 'ERROR: ' . $line . PHP_EOL);
        exit(1);
    }

    /** @return array version => array(file, class) sorted ascending */
    private function discover() {
        $found = array();
        foreach (glob(APPPATH . 'migrations/*.php') as $file) {
            $base = basename($file, '.php');
            if (!preg_match('/^(\d+)_(.+)$/', $base, $m)) {
                continue;
            }
            $found[$m[1]] = array(
                'file'  => $file,
                'name'  => $m[2],
                'class' => 'Migration_' . ucfirst(strtolower($m[2])),
            );
        }
        ksort($found);
        return $found;
    }

    private function applied() {
        $rows = $this->db->order_by('version', 'ASC')->get(self::TABLE)->result_array();
        $out = array();
        foreach ($rows as $r) {
            $out[$r['version']] = $r['name'];
        }
        return $out;
    }

    private function instantiate($meta) {
        require_once APPPATH . 'libraries/Ha_migration.php';
        require_once $meta['file'];
        if (!class_exists($meta['class'], FALSE)) {
            $this->fail('Migration class ' . $meta['class'] . ' not found in ' . basename($meta['file']));
        }
        return new $meta['class']();
    }

    public function index() {
        $this->out('Hospitality Academy CLI. Commands: migrate, rollback, seed, fresh, status.');
    }

    public function migrate() {
        $all = $this->discover();
        $done = $this->applied();
        $ran = 0;
        foreach ($all as $version => $meta) {
            if (isset($done[$version])) {
                continue;
            }
            $m = $this->instantiate($meta);
            $m->up();
            $this->db->insert(self::TABLE, array(
                'version' => $version,
                'name'    => $meta['name'],
                'ran_at'  => date('Y-m-d H:i:s'),
            ));
            $this->out('migrated  ' . $version . '  ' . $meta['name']);
            $ran++;
        }
        $this->out($ran ? ('Applied ' . $ran . ' migration(s).') : 'Nothing to migrate.');
    }

    public function rollback($target = '0') {
        $all = $this->discover();
        $done = $this->applied();
        krsort($done);
        $ran = 0;
        foreach ($done as $version => $name) {
            if ($version <= $target) {
                continue;
            }
            if (!isset($all[$version])) {
                $this->fail('Applied migration ' . $version . ' has no file on disk.');
            }
            $m = $this->instantiate($all[$version]);
            $m->down();
            $this->db->where('version', $version)->delete(self::TABLE);
            $this->out('rolled back  ' . $version . '  ' . $name);
            $ran++;
        }
        $this->out($ran ? ('Rolled back ' . $ran . ' migration(s).') : 'Nothing to roll back.');
    }

    public function status() {
        $all = $this->discover();
        $done = $this->applied();
        foreach ($all as $version => $meta) {
            $this->out(str_pad(isset($done[$version]) ? 'applied' : 'pending', 10)
                . $version . '  ' . $meta['name']);
        }
    }

    /**
     * Runs every seeder in application/seeds in filename order.
     * Seeders must be idempotent: re-running updates rather than duplicating.
     */
    public function seed($only = null) {
        $files = glob(APPPATH . 'seeds/*.php');
        sort($files);
        if (!$files) {
            $this->fail('No seeders found in ' . APPPATH . 'seeds/');
        }
        $total = 0;
        foreach ($files as $file) {
            $base = basename($file, '.php');
            if ($only !== null && strpos($base, $only) === false) {
                continue;
            }
            require_once $file;
            $parts = explode('_', $base, 2);
            $class = 'Seed_' . strtolower($parts[1]);
            if (!class_exists($class, FALSE)) {
                $this->fail('Seeder class ' . $class . ' not found in ' . $base);
            }
            $seeder = new $class();
            $count = (int) $seeder->run($this->db);
            $total += $count;
            $this->out(str_pad($base, 46) . ' ok (' . $count . ' rows)');
        }
        $this->out('Seeding complete: ' . $total . ' rows.');
    }

    public function fresh() {
        $this->rollback('0');
        $this->migrate();
        $this->seed();
    }
}
