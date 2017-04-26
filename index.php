<?php
error_reporting(E_ALL & ~E_NOTICE);
$site= $argv[1];
$ch = curl_init($site);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_VERBOSE, 0);
curl_setopt($ch, CURLOPT_HEADER, 1);
$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header_text = substr($response, 0, $header_size);
$body = substr($response, $header_size);
$headers=parse_headers($header_text);
$provider = check_provider_headers($header_text);
$cms = check_cms_headers($headers);
echo "$site is running $cms on $provider\n\n";


function check_cms_headers($respheaders){
  $cms = "Undetected";
  $checks=json_decode(file_get_contents("./cmsheaders.json"),true);
  foreach($checks as $k=>$v){
    if (is_array($v)){
      $header = key($v);
      $value = $v[$header];
      $rawgenerator = $respheaders[strtolower($header)];
      if (stripos($rawgenerator,$value)!==false){
        $generator = preg_replace('/\(.+\)/', '', $rawgenerator);
        $cms = $generator;
        break;
      }
    }
  }
  return $cms;
}



function check_provider_headers($headers){
  $provider = "Undetected";
  $checks=json_decode(file_get_contents("./providerheaders.json"),true);
  //print_r($checks);
  foreach($checks as $k=>$v){
    if(stripos($headers,$v)){
      $provider = $k;
      break;
    }
  }
  return $provider;
}
function parse_headers($header_text){
  $delimiter = "/:\s/";
  $temp = explode("\n",$header_text);
  $headers = array();
  foreach ($temp as $header){
    if (preg_match($delimiter,$header)){
      list($k,$v) =preg_split($delimiter,$header);
      $v = preg_replace('/[[:^print:]]/', '', $v);
      $headers[strtolower($k)] = $v;
    }
  }
  return $headers;
}
