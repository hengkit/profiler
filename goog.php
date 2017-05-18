<?php
require_once __DIR__ . '/vendor/autoload.php';

date_default_timezone_set("UTC");
putenv("GOOGLE_APPLICATION_CREDENTIALS=". __DIR__ . "/client_secret.json");
define('SCOPES', implode(' ', array(
  Google_Service_Sheets::SPREADSHEETS)
));

if (php_sapi_name() != 'cli') {
  throw new Exception('This application must be run on the command line.');
}

$client = new Google_Client();
$client->useApplicationDefaultCredentials();
$client->setScopes(SCOPES);
$service = new Google_Service_Sheets($client);
$spreadsheetId = "1-bSPqo_BJc9xQ7SqpUmvZK7iG58lbIi5H_HIRYKNnco";
$range="data";
$valueRange= new Google_Service_Sheets_ValueRange();
$valueRange->setValues(["values" => ["x", "y"]]);
$conf = ["valueInputOption" => "RAW"];
$response = $service->spreadsheets_values->append($spreadsheetId,$range,$valueRange,$conf);
