<?php

//Set keys

$apiKey = '7216db0161f82c6337c5f6ae7d96d05a';
$insttoken = '06d854b13701f22287228210264ee7b2';

function dbconnect() {
//  Set connection parameters
    $servername = "localhost";
    $username = "root";
    $password = "123";
    $dbname = "awards";
    $conn = new mysqli($servername, $username, $password, $dbname);
//  Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

function lev($str1, $str2) {
    
    $levenshtein = levenshtein($str1, $str2);
    $lengthdifference = max($str1, $str2) - min($str1, $str2);
    $levenshtein-=$lengthdifference;
    $similarity = $levenshtein / strlen(min($str1, $str2));
    return $similarity;
}

?>
