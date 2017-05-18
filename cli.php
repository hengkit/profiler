<?php
//suppress notices because they annoy me
error_reporting(E_ALL & ~E_NOTICE);
require_once(__DIR__.'/checks.php');
require_once(__DIR__.'/sslLabsApi.php');
define("UNDETECTED","Undetected");
if (php_sapi_name() != 'cli') {
  throw new Exception('This application must be run on the command line.');
}
  //first argument, a URL
$site= $argv[1];
$date = gmdate('Y-m-d H:i:s');
$api = new sslLabsApi();
$prime_ssl_check = json_decode($api->fetchHostInformation($host,false,false,false,null,'done',false),true);
$site_info = check_site($site);
$site_info['date'] = $date;
$perf = check_browsertime($site);
$ssl = check_ssl($site);
gsheet_append($site_info,$perf,$ssl);
echo "\nTests Complete\n";
