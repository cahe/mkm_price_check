<?php
require_once 'config.php';
function _paramsToUrl( $params ){
	$query = null;
	foreach( $params as $key=>$value)
			$query .= (($query == null)?'?':'&') . rawurlencode($key) . '=' . rawurlencode($value);
		
	return $query;
}

function restRequest( $url, $par ) {
	//params
	global $config;
    $method = "GET";

    $nonce = uniqid();
    $timestamp = time();
    $signatureMethod = "HMAC-SHA1";
    $version = "1.0";
    $params = array(
        'realm' => $url,
        'oauth_consumer_key' => $config['appToken'],
        'oauth_token' => $config['accessToken'],
        'oauth_nonce' => $nonce,
        'oauth_timestamp' => $timestamp,
        'oauth_signature_method' => $signatureMethod,
        'oauth_version' => $version,
    );
    
	$params = array_merge($params, $par);
	
    $baseString = strtoupper($method) . "&" . rawurlencode($url) . "&";
    //encode params alpha
    $encodedParams = array();
    foreach ($params as $key => $value) {
        if ("realm" != $key) {
            $encodedParams[rawurlencode($key)] = rawurlencode($value);
        }
    }
    ksort($encodedParams);
    //add params to URL
    $values = array();
    foreach ($encodedParams as $key => $value) {
        $values[] = $key . "=" . $value;
    }
    $paramsString = rawurlencode(implode("&", $values));
    $baseString .= $paramsString;
    //encode to sigkey
    $signatureKey = rawurlencode($config['appSecret']) . "&" . rawurlencode($config['accessSecret']);
    //widget app doesn't attach the access Secret, uncomment to use it
    //$signatureKey = rawurlencode($appSecret) . "&";
    $rawSignature = hash_hmac("sha1", $baseString, $signatureKey, true);
    $oAuthSignature = base64_encode($rawSignature);
    $params['oauth_signature'] = $oAuthSignature;
    //header
    $header = "Authorization: OAuth ";
    $headerParams = array();
    foreach ($params as $key => $value) {
        $headerParams[] = $key . "=\"" . $value . "\"";
    }
    $header .= implode(", ", $headerParams);
    //don't forget to attach params to GET url
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, $url . _paramsToUrl($par));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array($header));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // Execute
    $content = curl_exec($ch);
    //$content            = curl_exec($curlHandle);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if( __DEBUG__ ) {
        var_dump($info);
        var_dump($content);
    }

    //Error check if you need to debug or use for any purpose
    //if($info['http_code'] != '200') { echo "error: " . $info['http_code'] . "<br>"; }
    $decoded = json_decode($content, true);
	return $decoded;
}

function findCardId( $cardname, $edition ) {
	$params = array('search'=>$cardname, 'exact'=>'false', 'idGame'=>1, 'idLanguage'=>'1');
    $url = 'https://api.cardmarket.com/ws/v2.0/output.json/products/find';
	$result = restRequest($url, $params);

    foreach( $result['product'] as $product ) {
        if( $product['expansionName'] == $edition ) {
            return $product['idProduct'];
        }
    }

	return false;
}

function getExpansions(){
	$params = array();
    $url = 'https://api.cardmarket.com/ws/v2.0/output.json/games/1/expansions';
	$result = restRequest($url, $params);
	
	$output = array();
	//var_dump($result);
	foreach($result['expansion'] as $value){
		$output[] = array('idExpansion'=>$value['idExpansion'], 'name'=>$value['enName'], 'code'=>$value['abbreviation']);
	}

	return $output;
    
}

function getArticleData( $id, $language = '1', $minCondition = 'EX' ) {
    $url = "https://api.cardmarket.com/ws/v2.0/output.json/articles/$id";
    $params = array('idLanguage' => $language, 'minCondition' => $minCondition, 'isFoil'=>'false', 'start'=>0, 'maxResults'=>1000);
    $result = restRequest($url, $params);

    if(!array_key_exists('article', $result)) {
        echo("Dump for $id");
        var_dump($result);
    }

    return $result['article'];
}

function getPriceData( $id ) {
    $url = "https://api.cardmarket.com/ws/v2.0/output.json/products/$id";
    $params = array();
    $result = restRequest($url, $params);
    
    return $result['product']['priceGuide'];
}

function getMCMinfo($cardname = null, $edition = null, $productID = null)
{
    //check wich one is to do
    //############### search for cards with name and edition
	$url = null;
	$params = null;
	
    if (isset($cardname)) {
		$params = array('search'=>$cardname, 'exact'=>'false', 'idGame'=>1, 'idLanguage'=>'1');
        $url = 'https://api.cardmarket.com/ws/v2.0/output.json/products/find';
    } //################# get info with card id
    else {
        $url = "https://api.cardmarket.com/ws/v2.0/output.json/products/$productID";
		$params = array();
    }
    
	$decoded = restRequest($url, $params);
	

    $result = array();
    //################## search for card according to edition
    if (isset($cardname)) {
        $achei = false;
        //missed the card for some reason (wrong name or edition? do your checks)
        if (!isset($decoded['product'])) {
            echo "Missing card search for $cardname<br>\n";
            die();
        }
        foreach ($decoded['product'] as $value) {
            $thisexp = $value['expansionName'];
            //this kind of depends on how you pass the edition i remove the ' (example urza's sage)
            if (!$achei && strtolower(str_replace("'", "", $edition)) == strtolower(str_replace("'", "", $thisexp))) {
                $result['id'] = $value['idProduct'];
                $achei = true;
            }
        }
        if (!$achei) {
            echo "Missing search for $cardname with edition $edition<br>";
            // Want to check the editions returned? a print_r could be a better option
            // foreach ($decoded['product'] as $value) {
            //   echo "Editions returned: " . $value['expansionName'] . "<br>\n";
            // }
        }
    } //####################### get info by productid
    else {
        if (!isset($decoded['product'])) {
            echo "missed search for " . $product['idProduct'] . " <br>";
        }
        $result = $decoded['product'];
        $result['priceinfo'] = $product['priceGuide'];
        //check for whatever info you want :)
    }
    //returns card id or array with price info
    return $result;
}

function fetchExpansions(){
    $db = new SQLite3($dbFile);
    $expansions = getExpansions();
    $db->query("DELETE FROM expansions");
    echo("Adding expansions...");
    foreach( $expansions as $expansion ) {
        $statement = $db->prepare("INSERT INTO expansions VALUES (:1, :2, :3)");
        $statement->bindValue(':1', $expansion['idExpansion']);
        $statement->bindValue(':2', $expansion['code']);
        $statement->bindValue(':3', $expansion['name']);
        $statement->execute();
    }
}