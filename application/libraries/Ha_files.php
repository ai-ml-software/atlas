<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Private file storage (ppt-features 37, 154).
 *
 * Files are written under application/storage/private, which the web server
 * refuses to serve (.htaccess denies application/), with a random stored name
 * unrelated to the upload name. They are only ever returned by
 * /hkp/file/{token} after Hkp::file() has authenticated the user and checked
 * the object the file belongs to. The extension and the sniffed MIME type must
 * both be on the allow-list; a PHP file renamed to .pdf is refused.
 */
class Ha_files {

    protected $CI;

    protected static $mime_by_ext = array(
        'pdf' => array('application/pdf'),
        'jpg' => array('image/jpeg'), 'jpeg' => array('image/jpeg'), 'png' => array('image/png'), 'webp' => array('image/webp'),
        'mp4' => array('video/mp4'), 'mov' => array('video/quicktime'),
        'docx' => array('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'),
        'xlsx' => array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'),
        'pptx' => array('application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'),
        'csv' => array('text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'),
        'txt' => array('text/plain'),
    );

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
    }

    public static function root() {
        return APPPATH . 'storage' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR;
    }

    /**
     * Stores an upload from $_FILES[$field] (or a raw array('name','tmp_name','size','error')).
     * @return array the ha_file row
     */
    public function store_upload($upload, array $meta) {
        if (is_string($upload)) {
            $upload = isset($_FILES[$upload]) ? $_FILES[$upload] : null;
        }
        if (!$upload || !isset($upload['error']) || (int) $upload['error'] === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('Choose a file to upload.');
        }
        if ((int) $upload['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('The upload did not complete (error ' . (int) $upload['error'] . ').');
        }
        $is_upload = isset($meta['_trusted_path']) ? false : true;
        if ($is_upload && !is_uploaded_file($upload['tmp_name'])) {
            throw new InvalidArgumentException('That is not an uploaded file.');
        }
        return $this->store_path($upload['tmp_name'], $upload['name'], $meta, $is_upload);
    }

    /** Stores a file from a local path (used by generated artefacts and tests). */
    public function store_path($path, $original_name, array $meta, $move = false) {
        $this->CI->load->library('ha_tenant');
        $max_mb = (int) $this->CI->ha_tenant->get('files.max_mb', isset($meta['property_id']) ? $meta['property_id'] : null);
        $allowed = array_map('trim', explode(',', strtolower((string) $this->CI->ha_tenant->get('files.allowed_ext'))));
        $ext = strtolower(pathinfo((string) $original_name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true) || !isset(self::$mime_by_ext[$ext])) {
            throw new InvalidArgumentException('Files of type .' . $ext . ' are not accepted. Allowed: ' . implode(', ', $allowed) . '.');
        }
        $size = (int) @filesize($path);
        if ($size <= 0) {
            throw new InvalidArgumentException('The file is empty.');
        }
        if ($size > $max_mb * 1048576) {
            throw new InvalidArgumentException('The file is larger than ' . $max_mb . ' MB.');
        }
        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            $mime = (string) finfo_file($f, $path);
            finfo_close($f);
        }
        if (!in_array($mime, self::$mime_by_ext[$ext], true)) {
            throw new InvalidArgumentException('The file content (' . $mime . ') does not match its .' . $ext . ' extension.');
        }
        $token = bin2hex(random_bytes(20));
        $dir = self::root() . date('Y') . DIRECTORY_SEPARATOR . date('m') . DIRECTORY_SEPARATOR;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Private storage is not writable.');
        }
        $this->ensure_guard();
        $target = $dir . $token . '.bin';
        $ok = $move ? @move_uploaded_file($path, $target) : @copy($path, $target);
        if (!$ok) {
            throw new RuntimeException('The file could not be saved.');
        }
        $row = array(
            'token' => $token,
            'organization_id' => isset($meta['organization_id']) ? $meta['organization_id'] : null,
            'property_id' => isset($meta['property_id']) ? $meta['property_id'] : null,
            'owner_user_id' => isset($meta['owner_user_id']) ? $meta['owner_user_id'] : null,
            'entity_type' => isset($meta['entity_type']) ? $meta['entity_type'] : null,
            'entity_id' => isset($meta['entity_id']) ? (int) $meta['entity_id'] : null,
            'version_no' => isset($meta['version_no']) ? (int) $meta['version_no'] : 1,
            'original_name' => mb_substr(basename((string) $original_name), 0, 255),
            'stored_path' => str_replace(self::root(), '', $target),
            'mime_type' => $mime,
            'size_bytes' => $size,
            'sha256' => hash_file('sha256', $target),
            'created_at' => date('Y-m-d H:i:s'),
        );
        $this->CI->db->insert('ha_file', $row);
        $row['id'] = (int) $this->CI->db->insert_id();
        return $row;
    }

    public function attach($file_id, $entity_type, $entity_id) {
        $this->CI->db->where('id', (int) $file_id)->update('ha_file', array('entity_type' => $entity_type, 'entity_id' => (int) $entity_id));
    }

    public function by_token($token) {
        if (!preg_match('/^[a-f0-9]{40}$/', (string) $token)) {
            return null;
        }
        return $this->CI->db->get_where('ha_file', array('token' => $token))->row_array() ?: null;
    }

    public function absolute_path(array $file) {
        $path = self::root() . $file['stored_path'];
        $real = realpath($path);
        $root = realpath(self::root());
        if (!$real || !$root || strpos($real, $root) !== 0) {
            return null;
        }
        return $real;
    }

    /** A deny-all guard in case the storage folder is ever moved under the web root. */
    protected function ensure_guard() {
        $guard = self::root() . '.htaccess';
        if (!is_file($guard)) {
            @file_put_contents($guard, "Require all denied\nDeny from all\n");
        }
        $index = self::root() . 'index.html';
        if (!is_file($index)) {
            @file_put_contents($index, '');
        }
    }
}
