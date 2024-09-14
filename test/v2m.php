<?php

require_once "helper.php";
error_reporting(E_ALL);
ini_set('display_errors', 'On');

header('Content-Type: application/json');

class ClientManager
{
    private $loginUrl;
    private $addClientUrl;
    private $cookieData = '';
    public function __construct($port, $sublink)
    {
        $baseUrl = "http://localhost:$port/$sublink";
        $this->loginUrl = rtrim($baseUrl, '/') . '/login';
        $this->addClientUrl = rtrim($baseUrl, '/') . '/panel/inbound/addClient';
    }

    public function login($username, $password)
    {
        // Initialize cURL session
        $ch = curl_init();
        // Set the URL and other options for cURL
        curl_setopt($ch, CURLOPT_URL, $this->loginUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['username' => $username, 'password' => $password]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        // Execute the login request
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        // Close the cURL session
        curl_close($ch);

        // Extract cookies from the header
        if (preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $matches)) {
            $this->cookieData = implode('; ', $matches[1]);
        } else {
            die('Login failed');
        }
    }

    public function addNewClient($id, $uuid, $email, $subId = '', $tgId = '', $flow = '', $totalgb = 0, $eT = 0, $limitIp = 0, $fingerprint = 'chrome', $isTrojan = false)
    {
        $subId = $subId == '' ? uniqid() : $subId;
        $settings = json_encode(['clients' => [[
            $isTrojan ? 'password' : 'id' => $uuid,
            'enable' => true,
            'flow' => $flow,
            'email' => $email,
            'totalGB' => $totalgb,
            'limitIp' => $limitIp,
            'expiryTime' => $eT,
            'fingerprint' => $fingerprint,
            'tgId' => $tgId,
            'subId' => $subId
        ]]]);

        // Post data for the second request
        $addClientPostFields = [
            'id' => $id,
            'settings' => $settings
        ];

        // Initialize another cURL session
        $ch = curl_init();

        // Set the URL and other options for cURL
        curl_setopt($ch, CURLOPT_URL, $this->addClientUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($addClientPostFields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: $this->cookieData"]);

        // Execute the second request
        $response = curl_exec($ch);

        // Close the cURL session
        curl_close($ch);

        // Output the response from the second request
        echo $response;
    }
}
