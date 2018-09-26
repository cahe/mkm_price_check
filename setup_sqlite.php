<?php
require_once 'config.php';

$db = new SQLite3($dbFile);
$db->query("CREATE TABLE expansions (id INT, code VARCHAR(10), name VARCHAR(255))");
$db->query("CREATE TABLE stock (id INT, name VARCHAR(255), low DECIMAL(10,2), avg DECIMAL(10,2), trend DECIMAL(10,2))");