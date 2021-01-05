<?php

////////////////////////////////////////
// 2020/12/9 S.nishiguchi new
//ASINで一回で取得できる件数は10件、リクエストは1秒ごと
////////////////////////////////////////

//タイムアウト時間を100分に変更
ini_set("max_execution_time",6000);

//DB接続
require( dirname(__FILE__).'/../../setting/DBsetup.php');

//初期化
$asinList = "";
$asinListCount = 0;
$i_sql = 0;

//OKテーブルの中身を削除
$deleteSql = "DELETE FROM amazon_price_ok ;";
$res = $db->query($deleteSql);

//調査するASINを取得
$Sql = 'SELECT asin FROM amazon_price';

//$Sql = 'SELECT asin FROM amazon_price LIMIT 0, 11';
$res1 = $db->query($Sql);

foreach( $res1 as $value ) {
    $asinIDlist[] = "$value[asin]";
    $asinListCount++;
}

//確認
//print_r($asinIDlist);

//10件ずつ処理していく
for ($i = 0; $i < $asinListCount; $i++){
    $asinID = $asinIDlist[$i];

    //はじめはカンマがいらない
    if(($i+1)%10 != 1){
        $asinList .= ",";
    }
    $asinList .= '"' . $asinID . '"';
    
    //10回に1回Amazon処理
    //処理する関数が最後のときも処理に入れる
    if($i == ($asinListCount-1)){
        goto asinListEnd;
    }
    if(($i+1)%10 == 0){
        asinListEnd:

        //初期化
        $insertSql = "";
        $insertSql = "INSERT INTO amazon_price_ok (`asin`, `lowestPrice`, `offerCount`, `displayName`, `salesRank`) VALUES ";

        //amazonデータ取得
        $arr = amazonGet($asinList);
        //確認
        //print_r($arr);

        //取得したデータを１件ずつ格納
        for($ii = 0; $ii < 10; $ii++){
            //初期化
            $listPrice = 0;
            $listPrice_t = 0;
            $listStock = 0;
            $listCate = "";
            $listCateRank = 0;

            //ASIN取得。ASINが空の場合は処理を終了
            if(empty($arr['ItemsResult']['Items'][$ii]['ASIN'])){
                goto listAsinEnd;
            }

            $listAsin = $arr['ItemsResult']['Items'][$ii]['ASIN'];
            
            //ランキング情報が入っていれば入れる
            if(!empty($arr['ItemsResult']['Items'][$ii]['BrowseNodeInfo']['WebsiteSalesRank']['DisplayName'])){
                $listCate = $arr['ItemsResult']['Items'][$ii]['BrowseNodeInfo']['WebsiteSalesRank']['DisplayName'];
                $listCateRank = $arr['ItemsResult']['Items'][$ii]['BrowseNodeInfo']['WebsiteSalesRank']['SalesRank'];
            }else{
                //ランキングがなければ別場所からカテゴリー名を取得
                if(!empty($arr['ItemsResult']['Items'][$ii]['BrowseNodeInfo'])){
                    $listCate = $arr['ItemsResult']['Items'][$ii]['BrowseNodeInfo']['BrowseNodes'][0]['Ancestor']['DisplayName'];                    
                }else{
                    $listCate = "0カテゴリーなし";
                }

                $listCateRank = 9999999;
            }

            //最安価格を取得
            //価格がはいっていないない場合は、-1と入れる
            if (empty($arr['ItemsResult']['Items'][$ii]['Offers'])){
                $listPrice = 0;
                $listStock = 0;
            }else{
                $listPrice = $arr['ItemsResult']['Items'][$ii]['Offers']['Summaries'][0]['LowestPrice']['Amount'];
                $listStock = $arr['ItemsResult']['Items'][$ii]['Offers']['Summaries'][0]['OfferCount'];

                //もし2つめのロープライス（中古価格）があれば、１つ目と比較して小さい方を使う
                if(!empty($arr['ItemsResult']['Items'][$ii]['Offers']['Summaries'][1]['LowestPrice']['Amount'])){
                    $listPrice_t = $arr['ItemsResult']['Items'][$ii]['Offers']['Summaries'][1]['LowestPrice']['Amount'];

                    if($listPrice > $listPrice_t){
                        $listPrice = $listPrice_t;
                        $listStock =  $arr['ItemsResult']['Items'][$ii]['Offers']['Summaries'][1]['OfferCount'];
                    }
                }
            }

            echo $listAsin;
            echo "/";
            echo $listPrice;
            echo "/";
            echo $listStock;
            echo "/";
            echo $listCate;
            echo "/";
            echo $listCateRank;

            echo "<br>";

            //insertSQL作成
            //初め以外はカンマを代入
            if ($ii != 0){
                $insertSql .= " , ";
            } 
            $insertSql .= "('" . $listAsin . "'," . $listPrice . "," . $listStock . ",'" . $listCate . "'," . $listCateRank . ") ";
        }
        listAsinEnd:
        
        //インサート実行
        $insertSql .= ";";
        $res = $db->query($insertSql);

        //リスト初期化
        $asinList = "";
        //負荷軽減のため1秒待機
        sleep(1);
    }

}






/////////////////////////////////////////////////////////////////////////////////
//------------------------------------------------------------------------
///////
///////　■amazonデータ取得
///////　update　2020/12/09　S.nishiguchi 
///////　input $asinList ASINリスト
///////
///////　outpot $arrAmazon配列データ
///////
//------------------------------------------------------------------------
/////////////////////////////////////////////////////////////////////////////////

function amazonGet($asinList){

    // Put your Secret Key in place of **********
    $serviceName="ProductAdvertisingAPI";
    $region="us-west-2";
    //Key取得
    require( dirname(__FILE__).'/../../setting/amazonKey.php');
    $payload="{"
            ." \"ItemIds\": ["
    //        ." \"B004F9PY1C\","
    //        ." \"B004LQ0586\""
    //        ." \"B004F9PY1C\", \"B004LQ0586\""
            . $asinList
            ." ],"

            ." \"Resources\": ["
            ."  \"BrowseNodeInfo.BrowseNodes.Ancestor\","
            ."  \"BrowseNodeInfo.WebsiteSalesRank\","
            ."  \"Offers.Summaries.LowestPrice\","
            ."  \"Offers.Summaries.OfferCount\""
            ." ],"

            ." \"PartnerTag\": \"ryu0v0ryu-22\","
            ." \"PartnerType\": \"Associates\","
            ." \"Marketplace\": \"www.amazon.co.jp\""
            ."}";
    $host="webservices.amazon.co.jp";
    $uriPath="/paapi5/getitems";
    $awsv4 = new AwsV4 ($accessKey, $secretKey);
    $awsv4->setRegionName($region);
    $awsv4->setServiceName($serviceName);
    $awsv4->setPath ($uriPath);
    $awsv4->setPayload ($payload);
    $awsv4->setRequestMethod ("POST");
    $awsv4->addHeader ('content-encoding', 'amz-1.0');
    $awsv4->addHeader ('content-type', 'application/json; charset=utf-8');
    $awsv4->addHeader ('host', $host);
    $awsv4->addHeader ('x-amz-target', 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.GetItems');
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
