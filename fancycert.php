<?php
//iterate through a line delimited file of hosts
  $hostlist = file( './list.txt' );

  foreach ( $hostlist as $host ){
    //formatting
    $host = rtrim($host);
    echo $host, ": ",checkCert($host),"\n";
  }
  function checkCert ($ip) {
    $openssl = '/usr/local/bin/openssl';
    //download the cert information
    //hate the quit file but I didn't have a better solution
    $s_clientcmd = $openssl . " s_client -connect " . $ip . ":443  < quit.txt 2>&1";
    $certinfo = shell_exec($s_clientcmd);
    //look for a valid issuer, you can sometimes get EV status from here but it's not universal
    //and if it's not set, it's probably not a good certificate
    preg_match('/issuer=.+\n/',$certinfo, $issuer);
    if (isset($issuer[0])){
      //just parse the actual cert now
      $certstart = strpos($certinfo,'-----BEGIN CERTIFICATE');
      $certend = strpos($certinfo,'-----END CERTIFICATE-----');
      $cert = substr($certinfo,$certstart,($certend + 25 - $certstart));
      $certdetails = openssl_x509_parse($cert);
      echo checkSAN($certdetails), ", ";
      echo checkEV($certdetails);

    }
  }

  function checkEV($certdetails) {
    // compare the certificate policy against a list of valid OIDs
    $evstatus = false;
    //This list might need to be updated for truthiness
    $EVoids = array( "1.3.6.1.4.1.34697.2.1",
              "1.3.6.1.4.1.34697.2.2",
              "1.3.6.1.4.1.34697.2.1",
              "1.3.6.1.4.1.34697.2.3",
              "1.3.6.1.4.1.34697.2.4",
              "1.2.40.0.17.1.22",
              "2.16.578.1.26.1.3.3",
              "1.3.6.1.4.1.17326.10.14.2.1.2",
              "1.3.6.1.4.1.17326.10.8.12.1.2",
              "1.3.6.1.4.1.6449.1.2.1.5.1",
              "2.16.840.1.114412.2.1",
              "2.16.528.1.1001.1.1.1.12.6.1.1.1",
              "2.16.840.1.114028.10.1.2",
              "1.3.6.1.4.1.14370.1.6",
              "1.3.6.1.4.1.4146.1.1",
              "2.16.840.1.114413.1.7.23.3",
              "1.3.6.1.4.1.14777.6.1.1",
              "1.3.6.1.4.1.14777.6.1.2",
              "1.3.6.1.4.1.22234.2.5.2.3.1",
              "1.3.6.1.4.1.782.1.2.1.8.1",
              "1.3.6.1.4.1.8024.0.2.100.1.2",
              "1.2.392.200091.100.721.1",
              "2.16.840.1.114414.1.7.23.3",
              "1.3.6.1.4.1.23223.2",
              "1.3.6.1.4.1.23223.1.1.1",
              "1.3.6.1.5.5.7.1.1",
              "2.16.756.1.89.1.2.1.1",
              "2.16.840.1.113733.1.7.48.1",
              "2.16.840.1.114404.1.1.2.4.1",
              "2.16.840.1.113733.1.7.23.6",
              "1.3.6.1.4.1.6334.1.100.1"
    );
    if (isset($certdetails['extensions']['certificatePolicies'])){
      //not sure if this will work in every case but is working against my test cases
      preg_match('/Policy: (.+)/',$certdetails['extensions']['certificatePolicies'],$policies);
      // $policies[1] is the risky part, I could iterate through all policies but I don't know
      if(isset($policies[1])){
        if (in_array($policies[1],$EVoids)){
          $evstatus = "EV";
        } else {
          $evstatus = "No EV";
        }
      }
    }
    return $evstatus;
  }
  function checkSAN($certdetails){
    //This checks the Subject Alt Name extension for wildcards and multiple SANs
    if (isset($certdetails['extensions']['subjectAltName'])){
      // look for a wildcard
      if(substr_count($certdetails['extensions']['subjectAltName'], "DNS:*")>=1){
        return "Wildcard";
      }
      // if not wildcard, then count the SANs. I arbitrarily picked 3 since www. and the apex domain
      // will show up as SANs.
      if (substr_count($certdetails['extensions']['subjectAltName'], "DNS:")>=3){
        return "SAN";
      } else {
        return "Probably Single Domain";
      }
    }
  }
