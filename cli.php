<?php
//suppress notices because they annoy me
error_reporting(E_ALL & ~E_NOTICE);
require_once(__DIR__.'/checks.php');
require_once(__DIR__.'/sslLabsApi.php');
define("UNDETECTED","Undetected");

  //first argument, a URL
  $site= $argv[1];
  $password = "";

  $date = gmdate('Y-m-d H:i:s');
  $api = new sslLabsApi();
  $prime_ssl_check = json_decode($api->fetchHostInformation($host,false,false,false,null,'done',false),true);
  $site_info = check_site($site);
  $site_info['date'] = $date;
  print_r($site_info);
  echo "\n";
  $perf = check_browsertime($site);
  print_r($perf);
  echo "\n";
  $ssl_scores = check_ssl($site);
  print_r($ssl_scores);
  echo "\n Tests Complete\n";
//  write_checks($site_info,$ssl_scores,$perf);
