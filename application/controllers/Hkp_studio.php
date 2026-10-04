<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'core/Hkp_Controller.php';

/**
 * Website Studio history and review links.
 *
 *   /hkp/studio/revisions                 published revisions of entities, theme/site settings and menus
 *   POST /hkp/studio/revision_restore/{id} restore a revision into the private draft (version-checked)
 *   POST /hkp/studio/preview_link          signed 24-hour review link (still requires an editor sign-in)
 */
class Hkp_studio extends Hkp_Controller {

    /** Object types the signed-in user may edit, with their display labels. */
    private function editable_types() {
        $this->load->library(array('ha_content_studio', 'ha_studio_catalogue'));
        $out = array();
        $labels = array('site' => hkp_t('Theme & site settings'), 'navigation' => hkp_t('Menus'));
        foreach (Ha_studio_catalogue::types() as $type => $def) { $labels[$type] = hkp_t($def['title']); }
        foreach ($labels as $type => $label) {
            try { $this->ha_content_studio->authorize($type); $out[$type] = $label; } catch (Throwable $e) { /* not permitted */ }
        }
        return $out;
    }

    /** Human label for one revision's object. */
    private function object_label($type, $id, array $payload) {
        if ($type === 'site') { return hkp_t('Website theme'); }
        if ($type === 'navigation') { $m = $this->db->get_where('ha_menu', array('id' => (int) $id))->row_array(); return $m ? (string) ($m['name_' . (hkp_locale() === 'ar' ? 'ar' : 'en')] ?: $m['code']) : '#' . $id; }
        $loc = hkp_locale() === 'ar' ? 'ar' : 'en';
        return (string) ($payload['title_' . $loc] ?: ($payload['title_en'] ?? ('#' . $id)));
    }

    public function revisions() {
        $types = $this->editable_types();
        if (!$types) { $this->need('cms_pages.update'); }
        if (!$this->db->table_exists('ha_studio_revision')) { show_error('Install the publisher migrations: php index.php ha_cli migrate', 503); }
        $type = (string) $this->input->get('type');
        $id = (int) $this->input->get('id');
        $offset = max(0, (int) $this->input->get('offset'));
        $q = $this->db->select('r.id, r.object_type, r.object_id, r.payload_json, r.actor_id, r.created_at, u.first_name, u.last_name')
            ->from('ha_studio_revision r')->join('users u', 'u.id = r.actor_id', 'left')->where_in('r.object_type', array_keys($types));
        if ($type !== '' && isset($types[$type])) { $q->where('r.object_type', $type); }
        if ($id) { $q->where('r.object_id', $id); }
        $rows = $q->order_by('r.id', 'DESC')->limit(51, $offset)->get()->result_array();
        $more = count($rows) > 50; $rows = array_slice($rows, 0, 50);
        $states = array();
        foreach ($rows as &$r) {
            $payload = json_decode($r['payload_json'], true) ?: array();
            $r['label'] = $this->object_label($r['object_type'], $r['object_id'], $payload);
            $r['fields'] = count($payload);
            $key = $r['object_type'] . ':' . $r['object_id'];
            if (!isset($states[$key])) {
                try { $states[$key] = $this->ha_content_studio->state($r['object_type'], (int) $r['object_id']); } catch (Throwable $e) { $states[$key] = null; }
            }
            $r['state'] = $states[$key];
            $published = $states[$key] ? $states[$key]['published'] : null; unset($payload['version'], $published['version']);
            $r['current'] = $published !== null && json_encode($payload, JSON_UNESCAPED_UNICODE) === json_encode($published, JSON_UNESCAPED_UNICODE);
            unset($r['payload_json']);
        }
        unset($r);
        $this->render('studio_revisions', array('rows' => $rows, 'types' => $types, 'type' => $type, 'object_id' => $id, 'offset' => $offset, 'more' => $more), hkp_t('Revision history'), 'studio_revisions');
    }

    public function revision_restore($revision = 0) {
        $this->post_guard();
        $this->load->library('ha_content_studio');
        $r = $this->db->get_where('ha_studio_revision', array('id' => (int) $revision))->row_array();
        if (!$r) { $this->back(hkp_t('Revision not found.'), false); return; }
        $type = $r['object_type']; $id = (int) $r['object_id'];
        try { $this->ha_content_studio->authorize($type); } catch (Throwable $e) { $this->json(array('ok' => false, 'error' => $e->getMessage()), 403); return; }
        $this->attempt(function () use ($r, $type, $id) {
            try {
                $this->ha_content_studio->restore($type, $id, (int) $r['id'], (int) $this->input->post('draft_version'), (string) $this->input->post('base_hash'));
            } catch (DomainException $e) { throw new RuntimeException($e->getMessage()); }
            $this->ha_audit->log('update', 'studio_revision', (int) $r['id'], array('description' => $type . ' #' . $id . ' revision restored into private draft'));
        }, hkp_t('Revision restored as a private draft. Review and publish it to make it live.'), hkp_url('studio/revisions?type=' . rawurlencode($type) . '&id=' . $id));
    }

    public function preview_link() {
        $this->post_guard();
        $this->load->library(array('ha_studio_preview', 'ha_content_studio', 'ha_studio_catalogue'));
        $kind = (string) $this->input->post('kind'); $id = (int) $this->input->post('id'); $loc = $this->input->post('locale') === 'ar' ? 'ar' : 'en';
        try {
            if ($kind === 'page') {
                if (!$this->ha_auth->is_system_scoped() || !$this->ha_auth->has('cms_pages.update')) { throw new RuntimeException(hkp_t('You do not have permission to do that.')); }
                $p = $this->db->get_where('ha_page', array('id' => $id))->row_array();
                if (!$p || $p['status'] !== 'published') { throw new InvalidArgumentException(hkp_t('Only published pages have a public address to preview.')); }
                $slug = $p['code'] === 'for-hotels' ? 'hotels' : (string) $p['slug_' . $loc];
                $url = base_url($loc . ($slug !== '' ? '/' . $slug : '')) . '?studio_preview=' . $id;
            } else {
                $def = $this->ha_content_studio->authorize($kind);
                $row = $this->ha_studio_catalogue->record($kind, $id)['row'];
                if ($row['status'] !== 'published') { throw new InvalidArgumentException(hkp_t('Only published content has a public address to preview.')); }
                $url = base_url($loc . '/' . $def['route'] . '/' . $row['slug_' . $loc]) . '?studio_entity_preview=' . $kind . ':' . $id;
            }
            $url = $this->ha_studio_preview->sign_url($url, $kind, $id);
            $this->ha_audit->log('update', 'studio_preview', $id, array('description' => 'Signed review link issued for ' . $kind . ' #' . $id . ' (24 hours)'));
            $this->json(array('ok' => true, 'url' => $url, 'expires_at' => gmdate('c', Ha_studio_preview::expires_at(substr($url, strpos($url, 'studio_sig=') + 11)))));
        } catch (RuntimeException $e) { $this->json(array('ok' => false, 'error' => $e->getMessage()), 403); }
        catch (InvalidArgumentException $e) { $this->json(array('ok' => false, 'error' => $e->getMessage()), 422); }
    }
}
