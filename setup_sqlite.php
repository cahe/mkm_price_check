<?php
require_once 'config.php';

$db = new SQLite3($config['dbFile']);
$db->query("CREATE TABLE expansions (id INT, code VARCHAR(10), name VARCHAR(255))");
$db->query("CREATE TABLE stock (id INT, name VARCHAR(255), low DECIMAL(10,2), stock INTEGER, expansion_code varchar(10), avg DECIMAL(10,2), my_price DECIMAL(10,2), cheapest_in_country DECIMAL(10,2))");