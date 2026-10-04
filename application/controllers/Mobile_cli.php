<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Local mobile build setup. Never issues personal credentials or connects to production. */
class Mobile_cli extends CI_Controller {
    public function __construct() {
        parent::__construct();
        if (!is_cli()) { show_404(); }
        $this->load->database();
        if (!in_array($this->db->hostname, array('localhost', '127.0.0.1'), true)) {
            throw new RuntimeException('Mobile local setup requires a loopback database.');
        }
        $this->load->library(array('ha_mobile_config', 'ha_auth'));
    }

    public function configure_local($url = 'http://localhost/atlas/atlas') {
        $parts = parse_url($url);
        if (!$parts || !in_array(isset($parts['host']) ? $parts['host'] : '', array('localhost', '127.0.0.1', '10.0.2.2'), true)
            || !in_array(isset($parts['scheme']) ? $parts['scheme'] : '', array('http', 'https'), true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Use a local URL, for example http://localhost/atlas/atlas or http://10.0.2.2/atlas/atlas.');
        }
        $file = FCPATH . 'mobile app/altus-mobile/.env';
        if (is_file($file)) {
            echo 'The mobile .env already exists; update it explicitly instead of replacing its app key.' . PHP_EOL;
            return;
        }
        $admin = $this->db->select('u.id')->from('users u')->join('ha_user_role ur', 'ur.user_id=u.id')->join('ha_role r', 'r.id=ur.role_id')
            ->where(array('u.status'=>1, 'r.code'=>'super_admin', 'r.scope'=>'system'))->order_by('u.id')->get()->row();
        if (!$admin) { throw new RuntimeException('An active system administrator is required.'); }
        $this->ha_auth->assume((int) $admin->id);
        $key = $this->ha_mobile_config->create_key('Local mobile build 1.0.1', 'android', (int) $admin->id);
        $data = '# Local build only. App configuration key; no personal sign-in credentials.' . PHP_EOL
            . 'EXPO_PUBLIC_PLATFORM_URL=' . rtrim($url, '/') . PHP_EOL . 'EXPO_PUBLIC_ALTUS_APP_KEY=' . $key['key'] . PHP_EOL;
        if (file_put_contents($file, $data, LOCK_EX) === false) {
            $this->ha_mobile_config->revoke_key($key['id'], (int) $admin->id);
            throw new RuntimeException('Could not write mobile .env. The unused key was revoked.');
        }
        echo 'Configured mobile .env for ' . rtrim($url, '/') . '; app key #' . (int) $key['id'] . ' is visible in Admin → Mobile app settings (secret kept in ignored .env).' . PHP_EOL;
        echo 'For USB devices/emulators: adb reverse tcp:80 tcp:80. Restart Expo or rebuild the APK after changing .env.' . PHP_EOL;
    }

    public function configure_preview() {
        $file = APPPATH . 'config/ha_mobile.local.php';
        if (is_file($file)) { echo 'Mobile local origin configuration already exists; review it directly.' . PHP_EOL; return; }
        $contents = "<?php\ndefined('BASEPATH') OR exit('No direct script access allowed');\n"
            . "\$config['web_origins'] = array('http://localhost:8083', 'http://127.0.0.1:8083');\n";
        if (file_put_contents($file, $contents, LOCK_EX) === false) { throw new RuntimeException('Could not write local preview origins.'); }
        echo 'Enabled only localhost/127.0.0.1:8083 mobile web preview origins in the ignored local configuration.' . PHP_EOL;
    }
}
