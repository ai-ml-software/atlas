<?php
defined('BASEPATH') OR exit('No direct script access allowed');
return array(
    'live_editing'=>true,
    'mcp_enabled'=>false,
    'gateway_url'=>'http://127.0.0.1:3100',
    'gateway_secret'=>'', // Same random secret as the gateway; at least 32 characters.
    'gateway_key_id'=>'2026-10', // Must match ALTUS_MCP_KEY_ID in mcp-gateway/.env.
    'gateway_secret_previous'=>'',
    'gateway_key_id_previous'=>'previous',
    'allow_self_approval'=>true, // false = a second administrator must approve.
    'python'=>'C:/Python313/python.exe', // Set the installed Python path on your server.
    'tesseract'=>'C:/Program Files/Tesseract-OCR/tesseract.exe',
    'tessdata'=>APPPATH.'storage/private/ocr/tessdata',
);
