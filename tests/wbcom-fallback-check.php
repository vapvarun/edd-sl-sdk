<?php
// Wbcom fallback_url self-check, no WordPress needed: php -d zend.assertions=1 -d assert.exception=1 tests/wbcom-fallback-check.php .
define('ABSPATH','/');
$opts=[]; $calls=[]; $replies=[];
function get_option($k){global $opts;return $opts[$k]??false;}
function update_option($k,$v){global $opts;$opts[$k]=$v;}
function delete_option($k){global $opts;unset($opts[$k]);}
function apply_filters($h,$v){return $v;}
function wp_parse_args($a,$d){return array_merge($d,$a);}
function home_url(){return 'https://site.test';}
function wp_get_environment_type(){return 'production';}
function wp_parse_url($u){return parse_url($u);}
function add_query_arg($a,$u){return $u.'?'.http_build_query($a);}
function wp_remote_get($u){global $calls,$replies;$calls[]=parse_url($u,PHP_URL_HOST);$r=array_shift($replies);return $r;}
class WP_Error{}
function is_wp_error($r){return $r instanceof WP_Error;}
function wp_remote_retrieve_response_code($r){return $r['code'];}
function wp_remote_retrieve_body($r){return $r['body'];}
require $argv[1].'/src/Requests/API.php';
use EasyDigitalDownloads\Updater\Requests\API;
$ok=['code'=>200,'body'=>'{"license":"valid"}']; $no=['code'=>200,'body'=>'{"license":"invalid"}'];
$N='https://updates.wbcomdesigns.com'; $O='https://wbcomdesigns.com';
$p=['edd_action'=>'check_license'];
// No fallback registered: one call, behaviour as before.
$replies=[new WP_Error]; assert((new API($N))->make_request($p)===false); assert($calls===['updates.wbcomdesigns.com']);
API::set_fallback($N,$O); $opts=[];
// Network error on new host -> old host answers.
$calls=[];$replies=[new WP_Error,$ok]; assert((new API($N))->make_request($p)->license==='valid'); assert($calls===['updates.wbcomdesigns.com','wbcomdesigns.com']);
// New host now paused for an hour: straight to old host, one call.
$calls=[];$replies=[$ok]; assert((new API($N))->make_request($p)->license==='valid'); assert($calls===['wbcomdesigns.com']);
// 5xx and 403 also fall back.
foreach([503,403,404] as $c){$opts=[];$calls=[];$replies=[['code'=>$c,'body'=>''],$ok]; assert((new API($N))->make_request($p)->license==='valid'); assert(count($calls)===2);}
// Licence "no" is a 200: never retried.
$opts=[];$calls=[];$replies=[$no]; assert((new API($N))->make_request($p)->license==='invalid'); assert($calls===['updates.wbcomdesigns.com']);
// Both down: false, and each host paused separately.
$opts=[];$calls=[];$replies=[new WP_Error,new WP_Error]; assert((new API($N))->make_request($p)===false); assert(count($opts)===2);
// Old-host-only integrations are unaffected by the map.
$opts=[];$calls=[];$replies=[new WP_Error]; assert((new API($O))->make_request($p)===false); assert($calls===['wbcomdesigns.com']);
echo "sdk fallback: ok\n";
