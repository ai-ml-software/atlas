<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Collects every English interface string the workspace can display, so the
 * Arabic dictionary can be checked for completeness (Test_hkp_i18n) and a
 * translator can be handed exactly what is missing.
 *
 *   - literals passed to hkp_t() / hkp_e() in workspace views, controllers and libraries
 *   - enum values rendered through hkp_label() (every ENUM in the ha_* schema)
 *   - dynamic labels: readiness checks, setting descriptions, CRUD titles and fields
 */
class Ha_i18n_keys {

    public static function collect() {
        $keys = array();
        $files = array_merge(glob(APPPATH . 'views/hkp/*.php'), glob(APPPATH . 'controllers/Hkp*.php'), array(APPPATH . 'core/Hkp_Controller.php'),
            glob(APPPATH . 'libraries/Ha_{competency,practical,action_plans,readiness,certification,knowledge,governed_ai,learning,theory,kpi,advisory,reports,crud}.php', GLOB_BRACE));
        foreach ($files as $f) {
            $src = file_get_contents($f);
            if (preg_match_all("/hkp_[te]\(\s*'((?:[^'\\\\]|\\\\.)*)'/", $src, $m)) {
                foreach ($m[1] as $s) {
                    $keys[stripcslashes($s)] = true;
                }
            }
            if (preg_match_all('/hkp_[te]\(\s*"((?:[^"\\\\]|\\\\.)*)"/', $src, $m)) {
                foreach ($m[1] as $s) {
                    $keys[stripcslashes($s)] = true;
                }
            }
        }
        $label = function ($v) { return ucfirst(str_replace('_', ' ', $v)); };
        foreach (glob(APPPATH . 'migrations/*.php') as $f) {
            if (preg_match_all("/ENUM\(([^)]*)\)/i", file_get_contents($f), $m)) {
                foreach ($m[1] as $list) {
                    foreach (explode(',', $list) as $v) {
                        $v = trim($v, " '");
                        if ($v !== '' && preg_match('/^[a-z_]+$/', $v)) {
                            $keys[$label($v)] = true;
                        }
                    }
                }
            }
        }
        require_once APPPATH . 'libraries/Ha_readiness.php';
        require_once APPPATH . 'libraries/Ha_tenant.php';
        require_once APPPATH . 'libraries/Ha_crud.php';
        require_once APPPATH . 'libraries/Ha_importer.php';
        require_once APPPATH . 'libraries/Ha_notify.php';
        require_once APPPATH . 'seeds/001_rbac.php';
        foreach (Ha_readiness::check_labels() as $l) {
            $keys[$l] = true;
        }
        foreach (Ha_tenant::registry() as $k => $meta) {
            $keys[$meta[3]] = true;
            $keys[$label($meta[2])] = true;
        }
        foreach (Ha_crud::entities() as $e) {
            $keys[$e['title']] = true;
            foreach (array_merge(array_keys($e['fields']), $e['list']) as $f) {
                $keys[$label($f)] = true;
            }
            foreach ($e['fields'] as $t) {
                if (strpos($t, 'enum:') === 0) {
                    foreach (explode(',', rtrim(substr($t, 5), '*')) as $v) {
                        if ($v !== '') {
                            $keys[$label($v)] = true;
                        }
                    }
                }
            }
        }
        foreach (array_keys(Ha_importer::types()) as $t) {
            $keys[$label($t)] = true;
        }
        foreach (array_keys(Ha_notify::events()) as $ev) {
            $keys[$label(str_replace('.', '_', $ev))] = true;
        }
        foreach (array_keys(Seed_rbac::roles()) as $r) {
            $keys[$label($r)] = true;
        }
        foreach (array('altus_zone', 'legacy_operator', 'digital_veneer', 'undermanaged_asset', 'foundational', 'developing', 'established', 'leading',
            'course', 'knowledge', 'practice', 'lesson', 'module', 'theory', 'competency', 'readiness', 'global', 'organization', 'property', 'on_target',
            'no_target', 'no_data', 'operational', 'digital', 'overall', 'not_calculated', 'platform', 'recruitment', 'systems', 'commercial', 'exempted', 'started') as $v) {
            $keys[$label($v)] = true;
        }
        unset($keys['']);
        ksort($keys);
        return array_keys($keys);
    }
}
