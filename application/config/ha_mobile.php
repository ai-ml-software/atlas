<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$config['web_origins'] = array_filter(array_map('trim', explode(',', (string) getenv('HA_MOBILE_WEB_ORIGINS'))));
if (ENVIRONMENT !== 'production') {
    foreach (array('localhost', '127.0.0.1') as $host) {
        foreach (array(8081, 8082, 8083) as $port) { $config['web_origins'][] = 'http://' . $host . ':' . $port; }
    }
}
if (is_file(APPPATH . 'config/ha_mobile.local.php')) { include APPPATH . 'config/ha_mobile.local.php'; }
