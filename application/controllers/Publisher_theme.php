<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Publisher_theme extends CI_Controller {
    public function css() {
        $this->load->database(); $this->output->set_content_type('text/css')->set_header('Cache-Control: private, no-store');
        if (!$this->db->table_exists('ha_site_studio')) { return; }
        $this->load->library('ha_content_studio'); $this->output->set_output($this->ha_content_studio->css($this->input->get('preview')==='1'));
    }
}
