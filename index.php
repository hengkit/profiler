<?php
//suppress notices because they annoy me
error_reporting(E_ALL & ~E_NOTICE);
//just the one argument, a URL
$site= $argv[1];
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
echo "$header_text\n";
//separate the body from the headers
$body = substr($response, $header_size);
$headers=parse_headers($header_text);
//just check the headers first
$provider = check_headers($headers,"provider");
$cms = check_headers($headers,"cms");
$features = check_headers($headers,"feature");
echo "\n$site is running $cms on $provider with $features\n\n";

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
    //echo "Key: $k , Value:";
    foreach($v as $k2=>$v2){
      //check only for header existence
      if ($v2 ==""){
        //echo "Key: $k2 , Value:$v2\n";
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
    $features = implode(" ",$features);
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
