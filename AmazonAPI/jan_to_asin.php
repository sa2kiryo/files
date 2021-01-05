<?php

////////////////////////////////////////
// 2020/12/21 S.nishiguchi new
//JAN1つにつき取得できる検索結果は10件、リクエストは1秒ごと
////////////////////////////////////////

//タイムアウト時間を100分に変更
ini_set("max_execution_time",6000);

//DB接続
require( dirname(__FILE__).'/../../setting/DBsetup.php');


//調査するASINを取得
$Sql = 'SELECT jan FROM amazon_jan_asin';
//$Sql = 'SELECT jan FROM amazon_jan_asin LIMIT 0, 2';
$res1 = $db->query($Sql);

foreach( $res1 as $value ) {

    //初期化
    $getJan = "";
    $searchJan = "";
    $asin = "";
    $insertSql = "";

    //調査JAN
    $jan = "$value[jan]";
    $searchJan = '"' . $jan . '"';
    //確認
    echo $jan ."<br>";
    
    //amazonデータ取得
    $arr = asinGet($searchJan);
    //確認
    //print_r($arr);

    for ($ii = 0; $ii < 10; $ii++){
        if(!empty($arr['SearchResult']['Items'][$ii]['ItemInfo'])){
            $getJan = $arr['SearchResult']['Items'][$ii]['ItemInfo']['ExternalIds']['EANs']['DisplayValues'][0];

            if(intval($getJan) == intval($jan)){
                $asin = $arr['SearchResult']['Items'][$ii]['ASIN'];
                
                //確認
                echo "JAN：". $jan . " / ASIN：" .$asin."<br>";

                //一致したものを拾ったら、ASINを更新してループ完了
                $insertSql = "INSERT INTO amazon_jan_asin_ok (`jan`, `asin`) VALUES ";
                $insertSql .= "('" . $jan . "','" . $asin ."');";
                $res = $db->query($insertSql);

                //ASINを更新したものは、調査テーブルから除外する
                $deleteSql = "DELETE FROM amazon_jan_asin WHERE jan = '". $jan . "';";
                $res1 = $db->query($deleteSql);

                goto janToAsinEnd;

            }else{
                //一致したものがなかった場合は、echoだけ行う
                echo "★未取得 ：" . $jan . "<br>";
            }
        }
    }
    janToAsinEnd:

    //負荷軽減のため1秒待機
    sleep(1);

}

/////////////////////////////////////////////////////////////////////////////////
//------------------------------------------------------------------------
///////
///////　■amazonデータ取得
///////　update　2020/12/09　S.nishiguchi 
///////　input $searchJan JANコード（１つ）
///////
///////　outpot $arrAmazon配列データ
///////
//------------------------------------------------------------------------
/////////////////////////////////////////////////////////////////////////////////

function asinGet($searchJan){

    $serviceName="ProductAdvertisingAPI";
    $region="us-west-2";
    //Key取得
    require( dirname(__FILE__).'/../../setting/amazonKey.php');
    $payload="{"
    //        ." \"Keywords\": \"4997766201467\","
            ." \"Keywords\":". $searchJan .","
            ." \"Resources\": ["
            ."  \"ItemInfo.ExternalIds\""
            ." ],"
            ." \"PartnerTag\": \"ryu0v0ryu-22\","
            ." \"PartnerType\": \"Associates\","
            ." \"Marketplace\": \"www.amazon.co.jp\""
            ."}";
    $host="webservices.amazon.co.jp";
    $uriPath="/paapi5/searchitems";
    $awsv4 = new AwsV4 ($accessKey, $secretKey);
    $awsv4->setRegionName($region);
    $awsv4->setServiceName($serviceName);
    $awsv4->setPath ($uriPath);
    $awsv4->setPayload ($payload);
    $awsv4->setRequestMethod ("POST");
    $awsv4->addHeader ('content-encoding', 'amz-1.0');
    $awsv4->addHeader ('content-type', 'application/json; charset=utf-8');
    $awsv4->addHeader ('host', $host);
    $awsv4->addHeader ('x-amz-target', 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.SearchItems');
    $headers = $awsv4->getHeaders ();
    $headerString = "";
    foreach ( $headers as $key => $value ) {
        $headerString .= $key . ': ' . $value . "\r\n";
    }
    $params = array (
            'http' => array (
                'header' => $headerString,
                'method' => 'POST',
                'content' => $payload
            )
        );
    $stream = stream_context_create ( $params );

    $fp = @fopen ( 'https://'.$host.$uriPath, 'rb', false, $stream );

    if (! $fp) {
        throw new Exception ( "Exception Occured" );
    }
    $response = @stream_get_contents ( $fp );
    if ($response === false) {
        throw new Exception ( "Exception Occured" );
    }
    //echo $response;

    //JSON配列を連想配列にする
    $arr = json_decode($response,true);
    return $arr;

}

//--------------------------------------------------------//

class AwsV4 {

    private $accessKey = null;
    private $secretKey = null;
    private $path = null;
    private $regionName = null;
    private $serviceName = null;
    private $httpMethodName = null;
    private $queryParametes = array ();
    private $awsHeaders = array ();
    private $payload = "";

    private $HMACAlgorithm = "AWS4-HMAC-SHA256";
    private $aws4Request = "aws4_request";
    private $strSignedHeader = null;
    private $xAmzDate = null;
    private $currentDate = null;

    public function __construct($accessKey, $secretKey) {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->xAmzDate = $this->getTimeStamp ();
        $this->currentDate = $this->getDate ();
    }

    function setPath($path) {
        $this->path = $path;
    }

    function setServiceName($serviceName) {
        $this->serviceName = $serviceName;
    }

    function setRegionName($regionName) {
        $this->regionName = $regionName;
    }

    function setPayload($payload) {
        $this->payload = $payload;
    }

    function setRequestMethod($method) {
        $this->httpMethodName = $method;
    }

    function addHeader($headerName, $headerValue) {
        $this->awsHeaders [$headerName] = $headerValue;
    }

    private function prepareCanonicalRequest() {
        $canonicalURL = "";
        $canonicalURL .= $this->httpMethodName . "\n";
        $canonicalURL .= $this->path . "\n" . "\n";
        $signedHeaders = '';
        foreach ( $this->awsHeaders as $key => $value ) {
            $signedHeaders .= $key . ";";
            $canonicalURL .= $key . ":" . $value . "\n";
        }
        $canonicalURL .= "\n";
        $this->strSignedHeader = substr ( $signedHeaders, 0, - 1 );
        $canonicalURL .= $this->strSignedHeader . "\n";
        $canonicalURL .= $this->generateHex ( $this->payload );
        return $canonicalURL;
    }

    private function prepareStringToSign($canonicalURL) {
        $stringToSign = '';
        $stringToSign .= $this->HMACAlgorithm . "\n";
        $stringToSign .= $this->xAmzDate . "\n";
        $stringToSign .= $this->currentDate . "/" . $this->regionName . "/" . $this->serviceName . "/" . $this->aws4Request . "\n";
        $stringToSign .= $this->generateHex ( $canonicalURL );
        return $stringToSign;
    }

    private function calculateSignature($stringToSign) {
        $signatureKey = $this->getSignatureKey ( $this->secretKey, $this->currentDate, $this->regionName, $this->serviceName );
        $signature = hash_hmac ( "sha256", $stringToSign, $signatureKey, true );
        $strHexSignature = strtolower ( bin2hex ( $signature ) );
        return $strHexSignature;
    }

    public function getHeaders() {
        $this->awsHeaders ['x-amz-date'] = $this->xAmzDate;
        ksort ( $this->awsHeaders );

        // Step 1: CREATE A CANONICAL REQUEST
        $canonicalURL = $this->prepareCanonicalRequest ();

        // Step 2: CREATE THE STRING TO SIGN
        $stringToSign = $this->prepareStringToSign ( $canonicalURL );

        // Step 3: CALCULATE THE SIGNATURE
        $signature = $this->calculateSignature ( $stringToSign );

        // Step 4: CALCULATE AUTHORIZATION HEADER
        if ($signature) {
            $this->awsHeaders ['Authorization'] = $this->buildAuthorizationString ( $signature );
            return $this->awsHeaders;
        }
    }

    private function buildAuthorizationString($strSignature) {
        return $this->HMACAlgorithm . " " . "Credential=" . $this->accessKey . "/" . $this->getDate () . "/" . $this->regionName . "/" . $this->serviceName . "/" . $this->aws4Request . "," . "SignedHeaders=" . $this->strSignedHeader . "," . "Signature=" . $strSignature;
    }

    private function generateHex($data) {
        return strtolower ( bin2hex ( hash ( "sha256", $data, true ) ) );
    }

    private function getSignatureKey($key, $date, $regionName, $serviceName) {
        $kSecret = "AWS4" . $key;
        $kDate = hash_hmac ( "sha256", $date, $kSecret, true );
        $kRegion = hash_hmac ( "sha256", $regionName, $kDate, true );
        $kService = hash_hmac ( "sha256", $serviceName, $kRegion, true );
        $kSigning = hash_hmac ( "sha256", $this->aws4Request, $kService, true );

        return $kSigning;
    }

    private function getTimeStamp() {
        return gmdate ( "Ymd\THis\Z" );
    }

    private function getDate() {
        return gmdate ( "Ymd" );
    }
}

