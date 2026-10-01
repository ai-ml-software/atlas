<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Hooks
| -------------------------------------------------------------------------
| This file lets you define "hooks" to extend CI without hacking the core
| files.  Please see the user guide for info:
|
|	https://codeigniter.com/user_guide/general/hooks.html
|
*/
$hook['display_override'][] = array('class' => 'Ha_translation_output', 'function' => 'display', 'filename' => 'Ha_translation_output.php', 'filepath' => 'hooks');
