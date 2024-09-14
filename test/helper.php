<?php

function connectToDataBase()
{
    $host = "localhost";
    $database = "mine";
    $username = "hossein75rn";
    $password = "Abc4050d";
    $mysqli = new mysqli($host, $username, $password, $database);
    return $mysqli;
}

function curlInit($posts, $url)
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "$url",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => http_build_query($posts),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
