<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Studio_home_binding extends Ha_migration {
    public function up() { $this->add_columns('ha_page', array('studio_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0')); }
    public function down() { $this->drop_columns('ha_page', array('studio_enabled')); }
}
