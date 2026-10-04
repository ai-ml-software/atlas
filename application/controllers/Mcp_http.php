<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * HTTP adapter for the native PHP MCP server and OAuth 2.1 authorization server.
 *
 *   POST|GET|DELETE /mcp                                   Streamable HTTP MCP endpoint
 *   GET  /.well-known/oauth-protected-resource[/mcp]       RFC 9728
 *   GET  /.well-known/oauth-authorization-server[/...]     RFC 8414 (also /.well-known/openid-configuration)
 *   POST /oauth/register                                    RFC 7591 (public clients)
 *   GET  /oauth/authorize                                   -> ALTUS sign-in + consent (hkp/cms/mcp_authorize)
 *   POST /oauth/token                                       authorization_code (PKCE S256) / refresh_token
 *   POST /oauth/revoke                                      RFC 7009
 *
 * All logic lives in Ha_mcp_server / Ha_mcp_oauth; this class only translates
 * the PHP request into an array and emits the response. No Node process is used.
 */
class Mcp_http extends CI_Controller {
    public function index() {
        $this->load->database();
        $this->load->helper(array('url', 'hkp', 'ha_security'));
        $this->load->library('ha_mcp_server');
        $headers = array();
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_') === 0) $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
        }
        if (isset($_SERVER['CONTENT_TYPE'])) $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        // Apache/CGI sometimes strips Authorization; recover it from the rewrite env or apache_request_headers().
        if (empty($headers['authorization'])) {
            if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $headers['authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            elseif (function_exists('apache_request_headers')) { foreach ((array) apache_request_headers() as $k => $v) if (strtolower($k) === 'authorization') $headers['authorization'] = $v; }
        }
        $res = $this->ha_mcp_server->dispatch(array(
            'method' => $this->input->method(true), 'path' => $this->uri->uri_string(), 'headers' => $headers,
            'query' => (array) $this->input->get(), 'body' => (string) $this->input->raw_input_stream, 'ip' => function_exists('ha_client_ip') ? ha_client_ip() : $this->input->ip_address(),
        ));
        $this->output->set_status_header($res['status']);
        foreach ($res['headers'] as $k => $v) $this->output->set_header($k . ': ' . $v);
        $this->output->set_header('X-Content-Type-Options: nosniff');
        $this->output->set_output($res['body']);
    }
}
