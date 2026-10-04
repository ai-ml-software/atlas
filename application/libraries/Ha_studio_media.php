<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Ha_studio_media {
    private $CI;
    public function __construct() { $this->CI =& get_instance(); $this->CI->load->library(array('ha_auth','ha_audit')); }
    public function authorize() { if (!$this->CI->ha_auth->is_system_scoped() || !$this->CI->ha_auth->has(array('cms_pages.update','media.create'))) throw new RuntimeException('Platform media permission required.'); }
    public function listing($query='') { $this->authorize(); return $this->CI->db->select('id,file_path,original_name,alt_en,alt_ar,width,height')->where('disk','public')->where_in('mime_type',array('image/jpeg','image/png','image/webp'))->like('original_name',mb_substr($query,0,100))->order_by('id','DESC')->limit(60)->get('ha_media')->result_array(); }
    public function image($path,$name,$alt_en='',$alt_ar='') {
        $this->authorize(); $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION)); $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);$allowed=array('jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp');
        if (!isset($allowed[$ext]) || $allowed[$ext]!==$mime || !is_file($path) || filesize($path)>5*1048576 || !($dimensions=@getimagesize($path)) || $dimensions[0]*$dimensions[1]>40000000) throw new InvalidArgumentException('Upload a valid JPG, PNG or WebP image up to 5 MB and 40 megapixels.');
        $folder='uploads/hkp/studio/'.date('Y/m').'/'; if (!is_dir(FCPATH.$folder) && !mkdir(FCPATH.$folder,0755,true)) throw new RuntimeException('Media storage unavailable.'); $relative=$folder.bin2hex(random_bytes(16)).'.'.$ext;
        if (!copy($path,FCPATH.$relative)) throw new RuntimeException('Image could not be saved.');
        $this->CI->db->insert('ha_media',array('disk'=>'public','file_path'=>$relative,'original_name'=>mb_substr(basename($name),0,255),'mime_type'=>$mime,'extension'=>$ext,'file_size'=>filesize($path),'width'=>$dimensions[0],'height'=>$dimensions[1],'alt_en'=>mb_substr($alt_en,0,255),'alt_ar'=>mb_substr($alt_ar,0,255),'checksum'=>sha1_file($path),'uploaded_by'=>$this->CI->ha_auth->id(),'created_at'=>date('Y-m-d H:i:s')));
        $id=(int)$this->CI->db->insert_id();$this->CI->ha_audit->log('create','media',$id,array('description'=>'Studio image uploaded'));return array('id'=>$id,'path'=>$relative,'url'=>base_url($relative));
    }
}
