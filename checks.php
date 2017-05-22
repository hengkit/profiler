<?php

function gsheet_append($site,$perf,$ssl){
  require_once __DIR__ . '/vendor/autoload.php';
  date_default_timezone_set("UTC");
  putenv("GOOGLE_APPLICATION_CREDENTIALS=". __DIR__ . "/client_secret.json");
  define('SCOPES', implode(' ', array(Google_Service_Sheets::SPREADSHEETS)));
  $conf = ["valueInputOption" => "RAW"];
//TEST DATA
//  $spreadsheetId = "1-bSPqo_BJc9xQ7SqpUmvZK7iG58lbIi5H_HIRYKNnco";
//  $range="data";
  //LIVE
  $spreadsheetId = "1yUipxUpW0BDOlL6ZiHlZU2RzSR5k7z1TebQ7wj_Fldw";
  $range="Data";

  $values = array_values($site);
  $values = array_merge($values,array_values($perf));

  $ssljson = preg_replace("/\{|\}|\"/","",json_encode($ssl));
  $values[] =$ssljson;
  $valueHash['values'] = $values;
  $client = new Google_Client();
  $client->useApplicationDefaultCredentials();
  $client->setScopes(SCOPES);
  $service = new Google_Service_Sheets($client);

  $valueRange= new Google_Service_Sheets_ValueRange();
  $valueRange->setValues($valueHash);

  $response = $service->spreadsheets_values->append($spreadsheetId,$range,$valueRange,$conf);
  //print_r($response);
  }

function write_checks($site,$ssl_scores,$performance_scores){

  $servername = "localhost";
  $username = "root";
  $dbname = "profiler";
  global $password;
  $test_id="";
// Create connection
  $conn = mysqli_connect($servername, $username, $password,$dbname);
  if ($conn->connect_error) {
      die("Connection failed: " . $conn->connect_error);
  }
  $stmt = $conn->prepare("INSERT INTO site (test_date, url,provider, cms, ip) VALUES (?,?,?,?,?)" );
  $stmt->bind_param("sssss",$site['date'],$site['site'],$site['provider'],$site['cms'],$site['ip']);

  if ($stmt->execute() === TRUE) {
      $test_id = $stmt->insert_id;
      if (count($site['features'])>0){
        foreach($site['features'] as $feature){
          $stmt = $conn->prepare("INSERT INTO features (test_id,feature) VALUES (?,?)" );
          $stmt->bind_param("ss",$test_id,$feature);
          $stmt->execute();
        }
      }
      if (count($ssl_scores)>0){
        foreach($ssl_scores as $k=>$v){
          $stmt = $conn->prepare("INSERT INTO security (test_id,endpoint, score) VALUES (?,?,?)" );
          $stmt->bind_param("sss",$test_id,$k,$v);
          $stmt->execute();
        }
      }
      if (count($performance_scores)>0){
        $fields="";
        $param="";
        if ($performance_scores['runs'] > 1){
          foreach($performance_scores as $k=>$v){
            $fields .= $k . ",";
            $param .= $v . ",";
          }
          $fields = "test_id," . rtrim($fields,",");
          $param  = $test_id . ",".rtrim($param,",");
          $sql = "INSERT INTO performance (" . $fields . ") VALUES (" . $param . ")";
          echo $sql,"\n";
          $conn->query($sql);
        }
      }

  } else {
      echo "Error: " . $sql . "<br>" . $conn->error;
  }
  $stmt->close();
  $conn->close();
  display_checks($test_id);
  return 1;

}
function display_checks($testid){
  $sitequery = "select * from site where id =". $testid;
  $perfquery = "select * from performance where test_id =".$testid;
  $featurequery = "select * from features where test_id =".$testid;
  $securityquery = "select * from security where test_id=".$testid;

  $servername = "localhost";
  $username = "root";
  $dbname = "profiler";
  global $password;

  // Create connection
  $conn = mysqli_connect($servername, $username, $password,$dbname);
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }
}
function check_browsertime($site){
  $results = array();
  if(`which browsertime`){
    $btime = exec('browsertime -n 3 ' . $site);
    preg_match('/Wrote data to (.+)/',$btime,$resultdir);
    $resultfile = __DIR__ . "/" . $resultdir[1] . "/browsertime.json";
    $data=json_decode(file_get_contents($resultfile),true);
    $results['firstPaint'] = $data['statistics']['timings']['firstPaint']['median'];
    $results['rumSpeedIndex'] =  $data['statistics']['timings']['rumSpeedIndex']['median'];
    $results['pageLoad'] = $data['statistics']['timings']['pageTimings']['pageLoadTime']['median'];
    $results['backEnd'] = $data['statistics']['timings']['pageTimings']['backEndTime']['median'];
    $results['domContentLoaded'] = $data['statistics']['timings']['pageTimings']['domContentLoadedTime']['median'];
    $results['runs']=count($data['timestamps']);
  }
  return $results;
}
function check_sitespeed($site){
  $results = array();
  $runs = 3;
  if(`which sitespeed.io`){
    $rawresults = `sitespeed.io $site -n $runs`;
    $temp = explode("\n",$rawresults);
    foreach($temp as $line){
      if(strpos($line,'backEndTime')){
        $regex = "/([\d|\.]+)/";
        preg_match_all($regex,$line,$matches,PREG_PATTERN_ORDER,24);
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
curl_setopt($ch,CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.96 Safari/537.36');
  $response = curl_exec($ch);
  $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $header_text = substr($response, 0, $header_size);
  //separate the body from the headers
  $body = substr($response, $header_size);
  $headers=parse_headers($header_text);
  //just check the headers first
  $provider = check_headers($headers,"provider");
  $cms = check_headers($headers,"cms");
  if ($cms[0] == "Undetected"){
    $cms=check_body($body,"cms");

  }

  if ($cms[0]=="WordPress" && $provider=="Undetected"){
    $provider = check_body($body,"provider");
  }
  $headerfeatures = check_headers($headers,"feature");
  $bodyfeatures = check_body($body,"feature");
  $site_info['site'] = $site;
  $site_info['cms'] = $cms[0];
  $site_info['provider'] = $provider[0];
  $site_info['features'] = $headerfeatures + $bodyfeatures;
  $site_info['ip'] = gethostbyname(parse_url($site,PHP_URL_HOST).'.');
  if (count($site_info['features'])> 0){
    $features=array_unique($site_info['features'],SORT_STRING);
    $features = implode(", ",$features);
    //normalize for google docs
    $site_info['features'] = $features;
  }

  return $site_info;
}

function check_ssl($host){

  //if (substr($host,0,5) == 'https'){
    $endpoint_scores = array();
  //Return API response as JSON string
    $api = new sslLabsApi();
    $info = json_decode($api->fetchApiInfo(),true);
  //var_dump($info);
    $maxTests = $info["maxAssessments"];
    $currentTests = $info["currentAssessments"];
    if ($maxTests > $currentTests){
      $hostinfo = json_decode($api->fetchHostInformation($host,false,false,false,null,'done',false),true);
      $teststatus = $hostinfo['status'];
      while ($teststatus != "READY"){
        $hostinfo = json_decode($api->fetchHostInformation($host,false,false,false,null,'done',false),true);
        $teststatus = $hostinfo['status'];
      }
      foreach ($hostinfo['endpoints'] as $endpoint){
        if($endpoint['statusMessage'] == "Ready"){
          $endpoint_scores[$endpoint['ipAddress']] = $endpoint['grade'];
        }
      }
    }
//  } else {
//    echo "Cannot run SSL Scan on ", $host,"\n";
//  }
  return $endpoint_scores;
}
function check_body($body,$type){
  $features = array();
  $file = dirname(__FILE__). "/checks/".$type ."body.json";
  $checks=json_decode(file_get_contents($file),true);
  foreach($checks as $k=>$v){
    foreach($v as $v2){
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
  $file = dirname(__FILE__). "/checks/".$type ."headers.json";
  $checks=json_decode(file_get_contents($file),true);
  foreach($checks as $k=>$v){
    foreach($v as $k2=>$v2){
      //check only for header existence
      if ($v2 ==""){
        if ($headers[strtolower($k2)]){
          array_push($features,$k);
        }
      } else {
        //check the key and value of the header
        $respheader = $headers[strtolower($k2)];
        $v2 = "/".$v2."/";
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
