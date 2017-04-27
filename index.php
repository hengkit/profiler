<?php
//suppress notices because they annoy me
error_reporting(E_ALL & ~E_NOTICE);
require_once 'sslLabsApi.php';
//just the one argument, a URL
$site= $argv[1];
check_site($site);
check_ssl($site);
check_speed($site);

function check_speed($site){
  if(`which sitespeed.io`){
    echo "Checking Site Performance\nBrowsers will spawn, Do not be alarmed.\n";
    $rawresults = `sitespeed.io $site`;
    $temp = explode("\n",$rawresults);
    foreach($temp as $line){
      if(strpos($line,'backEndTime')){
        $line = preg_replace("/\[.+\] INFO: /","",$line);
        $results = "Performance Test Results: " . $line. "\n";
        break;
      } else {
        $results = "Performance Test Failed\n";
      }
    }
    echo $results;
  } else {
    echo "sitespeed.io not installed. Cannot run performance test\n";
  }
}

function check_site($site){
  //init curl
  $ch = curl_init($site);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  //gotta catch those 301s
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
  curl_setopt($ch, CURLOPT_VERBOSE, 0);
  curl_setopt($ch, CURLOPT_HEADER, 1);

  $response = curl_exec($ch);
  $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $header_text = substr($response, 0, $header_size);
  //echo "$header_text\n";
  //separate the body from the headers
  $body = substr($response, $header_size);
  $headers=parse_headers($header_text);
  //just check the headers first
  $provider = check_headers($headers,"provider");
  $cms = check_headers($headers,"cms");
  $headerfeatures = check_headers($headers,"feature");
  $bodyfeatures = check_body($body);
  echo "\n$site is running $cms on $provider with $headerfeatures, $bodyfeatures \n\n";
}


function check_ssl($host){
  if (substr($host,0,5) == 'https'){
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
          echo "Endpoint: ", $endpoint['ipAddress']," - ", $endpoint['grade'], "\n";
        }
      }
    } else {
      echo " - Not Available\n";
    }
  } else {
    echo "Cannot run SSL Scan on ", $host,"\n";
  }
}

function check_body($body){
  $features = array();
  $file = dirname(__FILE__). "/featurebody.json";
  $checks=json_decode(file_get_contents($file),true);
  foreach($checks as $k=>$v){
    echo "Checking for $k in body \n";
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
    $features = implode(", ",$features);
  } else {
    $features = "";
  }
  return $features;
}
function check_headers($headers,$type){
  $features = array();
  switch ($type){
    case "feature":
      $file = dirname(__FILE__). "/featureheaders.json";
      break;
    case "cms":
      $file = dirname(__FILE__)."/cmsheaders.json";
      break;
    case "provider":
      $file = dirname(__FILE__)."/providerheaders.json";
      break;
    default:
      $file = dirname(__FILE__)."/providerheaders.json";
  }
  $checks=json_decode(file_get_contents($file),true);
  foreach($checks as $k=>$v){
    echo "Checking for $k in headers\n";
    foreach($v as $k2=>$v2){
      //check only for header existence
      if ($v2 ==""){
        if ($headers[strtolower($k2)]){
          array_push($features,$k);
        }
      } else {
        //echo "Key: $k2 , Value:$v2\n";
        //check the key and value of the header
        //should probably use regex instead of stripos
        $respheader = $headers[strtolower($k2)];

        if(stripos($respheader,$v2)!==false){
          array_push($features,$k);
        }
      }
    }

  }
  if (count($features)> 0){
    $features=array_unique($features,SORT_STRING);
    $features = implode(", ",$features);
  } else {
    $features = "Undetected";
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
