<?php

///////////////////////////////////////////////////////////

// 2020/03/03 add
//GoogleAPIのCustom Searchを使い、指定のキーワードで、
//ウィキペディアのページが検索上位何番目にいるか調査する。
//参考にさせていただきました：http://piyopiyocs.blog115.fc2.com/blog-entry-1061.html

///////////////////////////////////////////////////////////

//取得したいキーワード
$query = "iPad";
//取得開始位置
$startNum = 1;

$rank = getSearchApi($query,$startNum);

echo "「".$query."」";
echo "検索順位：".$rank."<br><br>";

///////////////////////////////////////////////////////////

function getSearchApi($query,$startNum){

try{
        //------------------------------------
        // 定数設定
        //------------------------------------
         //APIキー
         $apiKey = "★★APIキー★★";
         //検索エンジンID
         $searchEngineId = "★★検索エンジンID★★";
         // 検索用URL
         $baseUrl = "https://www.googleapis.com/customsearch/v1?";
 
 
         //------------------------------------
         // リクエストパラメータ生成
         //------------------------------------
         $paramAry = array(
                         'q' => $query,
                         'key' => $apiKey,
                         'cx' => $searchEngineId,
                         'alt' => 'json',
                         'start' => $startNum
                 );
         $param = http_build_query($paramAry);
 
         //------------------------------------
         // 実行＆結果取得
         //------------------------------------
         $reqUrl = $baseUrl . $param;
         $retJson = @file_get_contents($reqUrl, true);
         if($retJson === false){
                throw new Exception("取得失敗：" . $http_response_header[0]);
        }
         $ret = json_decode($retJson, true);
 
         //------------------------------------
         // 結果表示
         //------------------------------------
 
         //画面表示
         //var_dump($ret);
 
         //項目を画面表示
         $rank = $startNum;
         $endNum = $startNum + 10;
 
         foreach($ret['items'] as $value){
                 //検索順位にウィキペディアのURLが含まれている場合
                 if(strpos($value['link'],'wikipedia.org') !== false){
         //              echo "順位:" . $rank . "<br>\n";
         //              echo "タイトル:" . $value['title'] . "<br>\n";
         //              echo "URL:" . $value['link'] . "<br>\n";
                         //順位を取得したらループを抜ける
                         break;
                 }
                 
                 $rank++;
                 //もし10個めまで見つからない場合は、nullにする
                 if($rank == $endNum){
                         $rank = null;
                 }                
         }
         //順位を返す
         return $rank;

} catch (Exception $e) {
        echo "エラー：" . $e->getMessage()."<br><br>";
        }
}
