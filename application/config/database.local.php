<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Local Laragon MySQL. Do not upload this file to any server.
return array(
    'hostname' => '127.0.0.1',
    'username' => 'root',
    'password' => '',
    // atlas_merged = production dump (khidmat_atlas.sql) + migrations 11-15 + local-only data.
    // The previous local database is still available as atlas_local.
    'database' => 'atlas_merged',
);
