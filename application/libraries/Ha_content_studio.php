<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/** Private drafts shared by browser editing and the publishing adapter. */
class Ha_content_studio {
    private $CI;
    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->library(array('ha_auth','ha_audit','ha_studio_catalogue','ha_website_navigation','ha_website_studio'));
    }
    public function authorize($type, $publish = false) {
        if (isset(Ha_studio_catalogue::types()[$type])) { return $this->CI->ha_studio_catalogue->authorize($type, $publish ? 'publish' : 'update'); }
        if (!in_array($type, array('site','navigation'), true) || !$this->CI->ha_auth->is_system_scoped() || !$this->CI->ha_auth->has($publish ? 'cms_pages.publish' : 'cms_pages.update')) { throw new RuntimeException('Platform website permission required.'); }
    }
    /** Shared site draft: theme tokens plus global site settings. Older saved payloads fall back to these values. */
    public static function defaults() { return array('accent'=>'#a84d27','background'=>'#faf7f2','ink'=>'#292725','surface'=>'#ffffff','muted'=>'#6f6259','line'=>'#e8e2db','link'=>'#a84d27','footer_background'=>'#292725',
        'heading_font'=>'serif','body_font'=>'sans-serif','spacing'=>'comfortable','header_style'=>'standard','navigation_style'=>'standard','footer_style'=>'standard',
        'logo'=>'','site_name'=>'','seo_title_suffix'=>'','seo_description'=>'','contact_email'=>'','contact_phone'=>'','contact_address'=>''); }
    public static function colour_keys() { return array('accent','background','ink','surface','muted','line','link','footer_background'); }
    /** Approved typefaces: only families already self-hosted in assets/academy/altus-fonts.css, plus system stacks. */
    public static function fonts() { return array('serif'=>"'Fraunces','Fraunces Fallback',Georgia,serif",'sans-serif'=>"'Inter','Inter Fallback',system-ui,sans-serif",'georgia'=>'Georgia,serif','arial'=>'Arial,sans-serif','fraunces'=>"'Fraunces','Fraunces Fallback',Georgia,serif",'inter'=>"'Inter','Inter Fallback',Arial,sans-serif",'inter-tight'=>"'Inter Tight','Inter',Arial,sans-serif",'plex-arabic'=>"'IBM Plex Sans Arabic',Tahoma,sans-serif"); }
    public static function options() { return array('spacing'=>array('compact','comfortable','spacious'),'header_style'=>array('standard','compact','tall'),'navigation_style'=>array('standard','underline','pill','uppercase'),'footer_style'=>array('standard','compact')); }
    /** Published site settings for visitors; the private draft only for authorized editors who ask for a preview. Null until first publication. */
    public function site_settings($preview = false) {
        if (!$this->CI->db->table_exists('ha_site_studio')) { return null; }
        $editor = $preview && $this->CI->ha_auth->is_system_scoped() && $this->CI->ha_auth->has('cms_pages.update');
        if (!$editor && !$this->CI->db->where('object_type','site')->count_all_results('ha_studio_revision')) { return null; }
        try { return $this->validate('site', $editor ? $this->state('site',1)['payload'] : $this->snapshot('site',1)); } catch (Throwable $e) { return null; }
    }
    public function snapshot($type, $id) {
        if ($type === 'site') { $r = $this->CI->db->get_where('ha_site_studio',array('id'=>1))->row_array(); return $r ? json_decode($r['payload_json'],true) : self::defaults(); }
        if ($type === 'navigation') { $items=$this->CI->ha_website_navigation->items($id); if (!$this->CI->db->get_where('ha_menu',array('id'=>(int)$id))->row_array()) throw new InvalidArgumentException('Menu not found.'); foreach ($items as &$item) $item['visible']=$item['status']==='active'?1:0; unset($item); return array('items'=>$items); }
        $r = $this->CI->ha_studio_catalogue->record($type,$id); $def = Ha_studio_catalogue::types()[$type];
        $p = array('status'=>$r['row']['status'],'image'=>$r['row'][$def['image']] ?? '', 'courses'=>array_column($r['courses'] ?? array(),'course_id'));
        foreach (array('en','ar') as $loc) { $tr = $r['tr'][$loc] ?? array(); foreach (array('title','summary','body') as $f) { $col=$f==='summary'?$def['summary']:($f==='body'?$def['body']:'title'); $p[$f.'_'.$loc] = $col ? (isset($def['translation']) ? ($tr[$col] ?? '') : ($r['row'][$col.'_'.$loc] ?? '')) : ''; } $p['slug_'.$loc]=$r['row']['slug_'.$loc]; }
        foreach (array('level','duration_hours','department_code','topic_type','city') as $f) { if (array_key_exists($f,$r['row'])) { $p[$f]=$r['row'][$f]; } }
        if ($type==='paths') { $p['steps_json']=json_encode($r['steps']); }
        $p['version']=$this->CI->ha_studio_catalogue->hash($r); return $p;
    }
    public function hash($type,$id) { return hash('sha256',json_encode($this->snapshot($type,$id),JSON_UNESCAPED_UNICODE)); }
    public function state($type,$id) {
        $this->authorize($type); $live=$this->snapshot($type,$id);
        $d=$this->CI->db->get_where('ha_studio_draft',array('object_type'=>$type,'object_id'=>(int)$id))->row_array();
        return array('payload'=>$d?json_decode($d['payload_json'],true):$live,'version'=>$d?(int)$d['version']:0,'base_hash'=>$d?$d['base_hash']:$this->hash($type,$id),'published'=>$live);
    }
    public function validate($type,array $p) {
        if (strlen(json_encode($p))>1000000) { throw new InvalidArgumentException('Draft is too large.'); }
        if ($type==='site') { $out=self::defaults(); $opts=self::options(); $opts['heading_font']=$opts['body_font']=array_keys(self::fonts());
            foreach ($out as $k=>$v) { $x=trim(str_replace("\0",'',(string)($p[$k]??$v)));
                if (in_array($k,self::colour_keys(),true)) { if (!preg_match('/^#[a-f0-9]{6}$/i',$x)) { throw new InvalidArgumentException('Use six-digit hexadecimal colours.'); } $x=strtolower($x); }
                elseif (isset($opts[$k])) { if (!in_array($x,$opts[$k],true)) { throw new InvalidArgumentException('Unsupported theme setting.'); } }
                elseif ($k==='logo') { Ha_website_studio::safe_url($x); if ($x!=='' && !preg_match('~\.(png|jpe?g|webp|svg)(\?.*)?$~i',$x)) { throw new InvalidArgumentException('Use a PNG, JPEG, WebP or SVG logo.'); } }
                elseif ($k==='contact_email') { if ($x!=='' && !filter_var($x,FILTER_VALIDATE_EMAIL)) { throw new InvalidArgumentException('Enter a valid contact email.'); } }
                else { $x=strip_tags($x); if (mb_strlen($x)>($k==='seo_description'||$k==='contact_address'?320:120)) { throw new InvalidArgumentException('Site setting is too long: '.$k); } }
                $out[$k]=$x; }
            return $out; }
        if ($type==='navigation') { if (!isset($p['items']) || !is_array($p['items']) || count($p['items'])>80) { throw new InvalidArgumentException('Invalid menu draft.'); } return $p; }
        $def=Ha_studio_catalogue::types()[$type]; foreach (array('en','ar') as $loc) { if (empty($p['title_'.$loc]) || mb_strlen($p['title_'.$loc])>190 || !preg_match('/^[\pL\pN_-]{1,190}$/u',(string)($p['slug_'.$loc]??''))) { throw new InvalidArgumentException('Both languages need a title and valid address.'); } $p['body_'.$loc]=hkp_safe_html((string)($p['body_'.$loc]??'')); }
        Ha_website_studio::safe_url((string)($p['image']??'')); return $p;
    }
    public function save($type,$id,array $p,$version,$hash) {
        $this->authorize($type); $p=$this->validate($type,$p); $db=$this->CI->db; $db->trans_begin();
        try { $this->lock($type,$id); $s=$this->state($type,$id); if ((int)$version!==$s['version'] || !hash_equals($this->hash($type,$id),(string)$hash) || !hash_equals($s['base_hash'],(string)$hash)) { throw new DomainException('Content changed in another editor. Reload before saving.'); }
            $row=array('version'=>$s['version']+1,'base_hash'=>$hash,'payload_json'=>json_encode($p,JSON_UNESCAPED_UNICODE),'updated_by'=>$this->CI->ha_auth->id(),'updated_at'=>date('Y-m-d H:i:s'));
            $s['version'] ? $db->where(array('object_type'=>$type,'object_id'=>(int)$id))->update('ha_studio_draft',$row) : $db->insert('ha_studio_draft',$row+array('object_type'=>$type,'object_id'=>(int)$id));
            $this->CI->ha_audit->log('update','studio_draft',$id,array('description'=>$type.' private draft saved'));
            if (!$db->trans_status()) { throw new RuntimeException('Draft save failed.'); } $db->trans_commit(); return $row['version'];
        } catch (Throwable $e) { $db->trans_rollback(); throw $e; }
    }
    private function lock($type,$id) {
        $db=$this->CI->db;
        if ($type==='site') { $db->query('INSERT IGNORE INTO ha_site_studio (id,payload_json,updated_at) VALUES (1,?,?)',array(json_encode(self::defaults()),date('Y-m-d H:i:s'))); $db->query('SELECT id FROM ha_site_studio WHERE id=1 FOR UPDATE'); }
        else { $table=$type==='navigation'?'ha_menu':Ha_studio_catalogue::types()[$type]['table']; $db->query('SELECT id FROM '.$table.' WHERE id=? FOR UPDATE',array((int)$id)); }
    }
    public function publish($type,$id,$version) {
        $this->authorize($type,true); $db=$this->CI->db; $db->trans_begin();
        try { $this->lock($type,$id); $s=$this->state($type,$id); if (!$version || (int)$version!==$s['version'] || !hash_equals($this->hash($type,$id),$s['base_hash'])) { throw new DomainException('Review the current draft before publishing.'); }
            $p=$this->validate($type,$s['payload']); $this->revision($type,$id,$s['published']);
            if ($type==='site') { $db->where('id',1)->update('ha_site_studio',array('payload_json'=>json_encode($p),'updated_at'=>date('Y-m-d H:i:s'))); }
            elseif ($type==='navigation') { $this->CI->ha_website_navigation->save($id,$p['items']); }
            else { $p['version']=$s['published']['version']; $p['status']='published'; $this->CI->ha_studio_catalogue->save($type,$id,$p); }
            $this->revision($type,$id,$p); $db->where(array('object_type'=>$type,'object_id'=>(int)$id))->delete('ha_studio_draft');
            $this->CI->ha_audit->log('publish',$type,$id,array('description'=>'Reviewed studio draft published'));
            if (!$db->trans_status()) { throw new RuntimeException('Publication failed.'); } $db->trans_commit();
        } catch (Throwable $e) { $db->trans_rollback(); throw $e; }
    }
    private function revision($type,$id,$p) { $this->CI->db->insert('ha_studio_revision',array('object_type'=>$type,'object_id'=>(int)$id,'payload_json'=>json_encode($p,JSON_UNESCAPED_UNICODE),'actor_id'=>$this->CI->ha_auth->id(),'created_at'=>date('Y-m-d H:i:s'))); }
    public function restore($type,$id,$revision,$version,$hash) { $this->authorize($type); $r=$this->CI->db->get_where('ha_studio_revision',array('id'=>(int)$revision,'object_type'=>$type,'object_id'=>(int)$id))->row_array(); if (!$r) { throw new InvalidArgumentException('Revision not found.'); } return $this->save($type,$id,json_decode($r['payload_json'],true),$version,$hash); }
    public function css($preview=false) {
        $published=$this->CI->db->where('object_type','site')->count_all_results('ha_studio_revision');
        if (!$published && (!$preview || !$this->CI->ha_auth->is_system_scoped() || !$this->CI->ha_auth->has('cms_pages.update'))) return '';
        $p=$this->snapshot('site',1); if ($preview && $this->CI->ha_auth->is_system_scoped() && $this->CI->ha_auth->has('cms_pages.update')) { $p=$this->state('site',1)['payload']; }
        $p=$this->validate('site',$p); $d=self::defaults(); $fonts=self::fonts(); $pad=array('compact'=>'36','comfortable'=>'64','spacious'=>'96'); $head=array('standard'=>'','compact'=>'60','tall'=>'96');
        $nav=array('standard'=>'','underline'=>'body.ha .ha-mast__nav a:hover,body.ha .ha-mast__nav a[aria-current]{text-decoration:underline;text-underline-offset:6px;text-decoration-color:var(--ha-accent)}','pill'=>'body.ha .ha-mast__nav a{border-radius:999px;padding-inline:12px}body.ha .ha-mast__nav a:hover,body.ha .ha-mast__nav a[aria-current]{background:color-mix(in srgb,var(--ha-accent) 14%,transparent)}','uppercase'=>'body.ha .ha-mast__nav a{text-transform:uppercase;letter-spacing:.08em;font-size:.82em}');
        // Only settings that differ from the defaults are emitted, so publishing the defaults never restyles the site's own stylesheet.
        $css=':root{--ha-space-section:'.$pad[$p['spacing']].'px}'; $vars='';
        foreach (array('accent'=>array('--ha-accent','--ha-orange'),'background'=>array('--ha-bg'),'ink'=>array('--ha-ink'),'surface'=>array('--ha-surface'),'muted'=>array('--ha-ink-faint'),'line'=>array('--ha-line')) as $k=>$names) { if ($p[$k]!==$d[$k]) { foreach ($names as $var) { $vars.=$var.':'.$p[$k].';'; } } }
        if ($p['background']!==$d['background']) { $vars.='background:'.$p['background'].';'; }
        if ($p['ink']!==$d['ink']) { $vars.='color:'.$p['ink'].';'; }
        if ($vars!=='') { $css.='body.ha{'.rtrim($vars,';').'}'; }
        if ($p['body_font']!==$d['body_font']) { $css.='body.ha:not(.ha--ar){--ha-font:'.$fonts[$p['body_font']].';font-family:var(--ha-font)}'; }
        if ($p['heading_font']!==$d['heading_font']) { $css.='body.ha:not(.ha--ar){--ha-font-display:'.$fonts[$p['heading_font']].';--ha-font-heading:'.$fonts[$p['heading_font']].'}body.ha:not(.ha--ar) h1,body.ha:not(.ha--ar) h2,body.ha:not(.ha--ar) h3{font-family:'.$fonts[$p['heading_font']].'}'; }
        if ($p['accent']!==$d['accent']) { $css.='body.ha .ha-btn{background:'.$p['accent'].'}'; }
        if ($p['spacing']!==$d['spacing']) { $css.='body.ha .ha-section{padding-block:'.$pad[$p['spacing']].'px}'; }
        if ($p['footer_style']!==$d['footer_style']) { $css.='body.ha .ha-foot{padding-block:24px}'; }
        if ($p['link']!==$d['link']) { $css.='body.ha .ha-prose a{color:'.$p['link'].'}'; }
        if ($p['footer_background']!==$d['footer_background']) { $css.='body.ha .ha-foot{background:'.$p['footer_background'].'}'; }
        if ($head[$p['header_style']]!=='') { $css.='body.ha .ha-mast__row{min-height:'.$head[$p['header_style']].'px}'; }
        return $css.$nav[$p['navigation_style']];
    }
}
