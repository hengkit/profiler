<?php
//suppress notices because they annoy me
error_reporting(E_ALL & ~E_NOTICE);
require_once(__DIR__.'/checks.php');
require_once(__DIR__.'/sslLabsApi.php');
define("UNDETECTED","Undetected");

if (empty($_POST)){
  echo "<form action=", htmlspecialchars($_SERVER["PHP_SELF"])," method=\"post\">";
  echo "Site: <input type=\"text\" name=\"site\"><br>";
  echo " <input type=\"submit\"></form>";
} else {
  //first argument, a URL
  $site= $_POST['site'];
  $password = "beeblebrox";

  $date = gmdate('Y-m-d H:i:s');
  $api = new sslLabsApi();
  $prime_ssl_check = json_decode($api->fetchHostInformation($host,false,false,false,null,'done',false),true);
  $site_info = check_site($site);
  $site_info['date'] = $date;
  $perf = check_browsertime($site);
  $ssl_scores = check_ssl($site);
  write_checks($site_info,$ssl_scores,$perf);
}
