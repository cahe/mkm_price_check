<?php
/**
 * Run this script from command line
 * Data is in format CARD NAME;EXPANSION CODE, semicolon delimited
 */
require_once 'rest.php';
require_once 'config.php';

if( !array_key_exists(1, $argv) ){
    die('Need an input file name. Run as php parse_and_load.php <file_name>');
}

$db = new SQLite3($config['dbFile']);

fetchExpansions();

$data = file($argv[1]);
$statement = $db->prepare("SELECT name FROM expansions WHERE code = :1");
foreach($data as $line) {
    $card = explode(";", $line);
    $name = trim($card[0]);
    $code = trim($card[1]);

    echo("Parsing $name\n");

    $statement->bindValue(':1', $code);
    $result = $statement->execute();
    $exp = $result->fetchArray(SQLITE3_ASSOC);
    $id = findCardId($name, $exp['name']);

    if($id) {
        echo("Found id $id\n");
        $articles = getArticleData($id);

        $lowest = 0;
        $avg = 0;
        $count = 0;
        $cheapestInCountry = 0;
        $myPrice = 0;

        foreach($articles as $idx=>$article) {
            if($article['isPlayset']) $article['price'] /= 4;
            if($article['seller']['address']['country'] == $config['country'] && $article['seller']['username'] <> $config['mkm_login'] && $cheapestInCountry == 0) {
                $cheapestInCountry = $article['price'];
            }
            if($article['seller']['address']['country'] == $config['country'] && $article['seller']['username'] == $config['mkm_login']) {
                $myPrice = $article['price'];
            }
            $count++;
            if( $lowest == 0 ) {
                $lowest = $article['price'];
            }
            $avg += $article['price'];
        }

        if( $count < 1 ) //no data parsed, ignore
            continue;

        $avg /= $count;
        echo("avg: " . round($avg,2));
        echo("\ncheapest in Poland: $cheapestInCountry (my price: $myPrice)");
        echo("\nlowest: $lowest");
        echo("\n\n");

        file_put_contents("log\\" . $name . ".log", var_export($articles, true));

        $data = $db->prepare("INSERT OR REPLACE INTO stock (id, name, low, avg, cheapest_in_country, my_price, expansion_code) values (:id, :name, :low, :avg, :cheapest_in_country, :my_price, :expansion_code)");
        $data->bindValue(':id', $id);
        $data->bindValue(':name', $name);
        $data->bindValue(':low', $lowest);
        $data->bindValue(':avg', $avg);
        $data->bindValue(':cheapest_in_country', $cheapestInCountry);
        $data->bindValue(':my_price', $myPrice);
        $data->bindValue(':expansion_code', $exp['name']);
        $data->execute();
        $data->close();
        
        //$priceData = getPriceData($id);
        //var_dump($priceData);
    }

    $statement->reset();
}