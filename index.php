<?php
//suppress notices because they annoy me
error_reporting(E_ALL & ~E_NOTICE);
define("UNDETECTED","Undetected");
require_once 'sslLabsApi.php';
//first argument, a URL
$site= $argv[1];
$password = $argv[2];

$date = gmdate('Y-m-d H:i:s');
$api = new sslLabsApi();
$prime_ssl_check = json_decode($api->fetchHostInformation($host,false,false,false,null,'done',false),true);
$site_info = check_site($site);
$site_info['date'] = $date;
$ssl_scores = check_ssl($site);
//$ssl_scores= NULL;
$performance_scores = check_speed($site);
write_checks($site_info,$ssl_scores,$performance_scores);

function write_checks($site,$ssl_scores,$performance_scores){
  //print_r($site);
  //echo "\n\n";
  $servername = "localhost";
  $username = "root";
  $dbname = "profile";
  global $password;
// Create connection
  $conn = mysqli_connect($servername, $username, $password,$dbname);
  if ($conn->connect_error) {
      die("Connection failed: " . $conn->connect_error);
  }
  $stmt = $conn->prepare("INSERT INTO site (date, url,provider, cms) VALUES (?,?,?,?)" );
  $stmt->bind_param("ssss",$site['date'],$site['site'],$site['provider'],$site['cms']);

  if ($stmt->execute() === TRUE) {
    //  echo "New record created successfully";
      $site_id = $stmt->insert_id;
      if (count($site['features'])>0){
        foreach($site['features'] as $feature){
        //echo $feature,"\n";
          $stmt = $conn->prepare("INSERT INTO features (site_id,feature) VALUES (?,?)" );
          $stmt->bind_param("ss",$site_id,$feature);
          $stmt->execute();
        }
      }
      if (count($ssl_scores)>0){
        //print_r($ssl_scores);
        foreach($ssl_scores as $k=>$v){
        //echo $feature,"\n";
          $stmt = $conn->prepare("INSERT INTO security (site_id,endpoint, score) VALUES (?,?,?)" );
          $stmt->bind_param("sss",$site_id,$k,$v);
          $stmt->execute();
        }
      }
      if (count($performance_scores)>0){
        //print_r($performance_scores);
        $fields="";
        $param="";
        if ($performance_scores['runs'] > 1){
          foreach($performance_scores as $k=>$v){
            $fields .= $k . ",";
            $param .= $v . ",";
          }
          $fields = "site_id," . rtrim($fields,",");
          $param  = $site_id . ",".rtrim($param,",");
          $sql = "INSERT INTO PERFORMANCE (" . $fields . ") VALUES (" . $param . ")";
          $conn->query($sql);
        }
      }

  } else {
      echo "Error: " . $sql . "<br>" . $conn->error;
  }
  $stmt->close();
  $conn->close();
  return 1;
}
function check_speed($site){
  $results = array();
  $runs = 2;
  if(`which sitespeed.io`){
    echo "Checking Site Performance\nBrowsers will spawn, Do not be alarmed.\n";
    $rawresults = `sitespeed.io $site -n $runs`;
    $temp = explode("\n",$rawresults);
    foreach($temp as $line){
      if(strpos($line,'backEndTime')){
        //echo $line;
        $regex = "/([\d|\.]+)/";
        preg_match_all($regex,$line,$matches,PREG_PATTERN_ORDER,24);
        //print_r($matches);
        if ($runs > 1){
          $results['requests'] = $matches[0][0];
          $results['size'] = $matches[0][1];
          $results['backendtime'] = $matches[0][2];
          $results['backendtime_deviation'] = $matches[0][3]/1000;
          $results['firstpaint'] = $matches[0][4];
          $results['firstpaint_deviation'] = $matches[0][5]/1000;
          $results['domcontentloaded'] = $matches[0][6];
          $results['domcontentloaded_deviation'] = $matches[0][7]/1000;
          $results['pageload'] = $matches[0][8];
          $results['pageload_deviation'] = $matches[0][9]/1000;
          $results['rumspeedindex'] = $matches[0][10];
          $results['rumspeedindex_deviation'] = $matches[0][11];
          $results['runs'] = $matches[0][12];
        } else {
          $results['requests'] = $matches[0][0];
          $results['size'] = $matches[0][1];
          $results['backendtime'] = $matches[0][2];
          $results['firstpaint'] = $matches[0][3];
          $results['domcontentloaded'] = $matches[0][4];
          $results['load'] = $matches[0][5];
          $results['rumspeedindex'] = $matches[0][6];
          $results['runs'] = 1;
        }
        break;
      }
    }
  } else {
    echo "sitespeed.io not installed. Cannot run performance test\n";
  }
  return $results;
}

function check_site($site){
  //init curl
  $site_info= array();
  $ch = curl_init($site);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  //gotta catch those 301s
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
  curl_setopt($ch, CURLOPT_VERBOSE, 0);
  curl_setopt($ch, CURLOPT_HEADER, 1);

  $response = curl_exec($ch);
  $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $header_text = substr($response, 0, $header_size);
  echo "Checking Site Features\n";
  //separate the body from the headers
  $body = substr($response, $header_size);
  $headers=parse_headers($header_text);
  //just check the headers first
  $provider = check_headers($headers,"provider");
  $cms = check_headers($headers,"cms");
  if ($cms == "Undetected"){
    $cms=check_body($body,"cms");
  }
  if ($cms=="WordPress" && $provider=="Undetected"){
//    echo "Checking for WP Engine in body\n";
    $provider = check_body($body,"provider");
  }
  $headerfeatures = check_headers($headers,"feature");
  $bodyfeatures = check_body($body,"feature");
  $site_info['site'] = $site;
  $site_info['cms'] = $cms[0];
  $site_info['provider'] = $provider[0];
  $site_info['features'] = $headerfeatures + $bodyfeatures;
  if (count($site_info['features'])> 0){
    $features=array_unique($site_info['features'],SORT_STRING);
    $features = implode(", ",$features);
  }
  echo "\n$site is running $cms on $provider with $features \n\n";
  return $site_info;
}

function check_ssl($host){
  if (substr($host,0,5) == 'https'){
    $endpoint_scores = array();
  //Return API response as JSON string
    $api = new sslLabsApi();
    echo "Checking SSL Labs API Status";
    $info = json_decode($api->fetchApiInfo(),true);
  //var_dump($info);
    $maxTests = $info["maxAssessments"];
    $currentTests = $info["currentAssessments"];
    if ($maxTests > $currentTests){
      echo " - OK\nInitializing Tests\n";
      $hostinfo = json_decode($api->fetchHostInformation($host,false,false,false,null,'done',false),true);
      $teststatus = $hostinfo['status'];
      echo "Waiting for tests to complete";
      while ($teststatus != "READY"){

        for ($x=1;$x<=3;$x++){
          sleep(10);
          echo ".";
        }

        $hostinfo = json_decode($api->fetchHostInformation($host,false,false,false,null,'done',false),true);
        $teststatus = $hostinfo['status'];
      }
    //var_dump($hostinfo);

      echo "\nTest Results for ",$hostinfo['host'], "\n";
      foreach ($hostinfo['endpoints'] as $endpoint){
        //print_r($endpoint);
        if($endpoint['statusMessage'] == "Ready"){
          $endpoint_scores[$endpoint['ipAddress']] = $endpoint['grade'];
          //echo "Endpoint: ", $endpoint['ipAddress']," - ", $endpoint['grade'], "\n";
        }
      }
    } else {
      echo " - Not Available\n";
    }
  } else {
    echo "Cannot run SSL Scan on ", $host,"\n";
  }
  return $endpoint_scores;
}
function check_body($body,$type){
  $features = array();
  $file = dirname(__FILE__). "/".$type ."body.json";
  $checks=json_decode(file_get_contents($file),true);
  foreach($checks as $k=>$v){
//    echo "Checking for $k in body \n";
    //print_r($v);
    foreach($v as $v2){
      //echo "checking for $v2 in body\n";
      if(stripos($body,$v2)!==false){
        array_push($features,$k);
      }
    }
  }
 if (count($features)> 0){
    $features=array_unique($features,SORT_STRING);
  //  $features = implode(", ",$features);
  } else {
    $features = (array) UNDETECTED;
  }
  return $features;
}

function check_headers($headers,$type){
  $features = array();
  $file = dirname(__FILE__). "/".$type ."headers.json";
  $checks=json_decode(file_get_contents($file),true);
  foreach($checks as $k=>$v){
//    echo "Checking for $k in headers\n";
    foreach($v as $k2=>$v2){
      //check only for header existence
      if ($v2 ==""){
        if ($headers[strtolower($k2)]){
          array_push($features,$k);
        }
      } else {
        //echo "Key: $k2 , Value:$v2\n";
        //check the key and value of the header
        $respheader = $headers[strtolower($k2)];
        $v2 = "/".$v2."/";
        //echo "Header Key:$k2, Header Value: $respheader , Value: $v2\n";
        if(preg_match($v2,$respheader)){
          array_push($features,$k);
        }
      }
    }

  }
  if (count($features)> 0){
    $features=array_unique($features,SORT_STRING);
  //  $features = implode(", ",$features);
  } else {
    $features = (array) UNDETECTED;
  }
  return $features;
}

function parse_headers($header_text){
  //takes a big old string of headers and return them in a hash
  $delimiter = "/:\s/";
  // create a new array with keys and values in each entry
  $temp = explode("\n",$header_text);
  $headers = array();
  foreach ($temp as $header){
    //iterate through the temp array to look for K-v pairs
    if (preg_match($delimiter,$header)){
      list($k,$v) =preg_split($delimiter,$header);
      //get rid of the nasty unprintables
      $v = preg_replace('/[[:^print:]]/', '', $v);
      //make the keys all lower case
      //append repeated header values together
      if ($headers[strtolower($k)]){
        $headers[strtolower($k)] .= " ".$v;
      } else {
        $headers[strtolower($k)] = $v;
      }
    }
  }
  return $headers;
}
