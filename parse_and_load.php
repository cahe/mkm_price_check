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

$db = new SQLite3($dbFile);

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
        $cheapestPolish = 0;
        $myPrice = 0;

        foreach($articles as $idx=>$article) {
            if($article['isPlayset']) $article['price'] /= 4;
            if($article['seller']['address']['country'] == 'PL' && $article['seller']['username'] <> 'mjanowski' && $cheapestPolish == 0) {
                $cheapestPolish = $article['price'];
            }
            if($article['seller']['address']['country'] == 'PL' && $article['seller']['username'] == 'mjanowski') {
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
        echo("\ncheapest in Poland: $cheapestPolish (my price: $myPrice)");
        echo("\nlowest: $lowest");
        echo("\n\n");

        file_put_contents("log\\" . $name . ".log", var_export($articles, true));

        $data = $db->prepare("INSERT OR REPLACE INTO stock (id, name, low, avg, cheapest_polish, my_price) values (:id, :name, :low, :avg, :cheapest_polish, :my_price)");
        $data->bindValue(':id', $id);
        $data->bindValue(':name', $name);
        $data->bindValue(':low', $lowest);
        $data->bindValue(':avg', $avg);
        $data->bindValue(':cheapest_polish', $cheapestPolish);
        $data->bindValue(':my_price', $myPrice);
        $data->execute();
        $data->close();
        
        //$priceData = getPriceData($id);
        //var_dump($priceData);
    }

    $statement->reset();
}