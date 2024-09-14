<?php

require_once "helper.php";
error_reporting(E_ALL);
ini_set('display_errors', 'On');

header('Content-Type: application/json');

$host = "localhost";
$database = "mine";
$username = "hossein75rn";
$password = "Abc4050d";

$mysqli = new mysqli($host, $username, $password, $database);
$mysqli->set_charset('utf8');

if (isDatabaseConnected($mysqli)) {
    try {
        processRequest($mysqli);
    } catch (mysqli_sql_exception $e) {
        respondWithError($e->getMessage());
    }
} else {
    respondWithError('دیتابیس متصل نشد: ' . $mysqli->connect_error);
}

function isDatabaseConnected($mysqli)
{
    return $mysqli->connect_error == 0;
}

function processRequest($mysqli)
{
    $uuid = getUUIDFromRequest();
    $up = fetchUsernameAndPanels($mysqli, $uuid);
    $sc = fetchSconfigs($mysqli, $uuid);
    $generalConfigs = fetchGeneralConfigs($mysqli);
    if ($up->num_rows > 0) {
        $sconfigs = array();
        if ($sc != null) {
            while ($row = $sc->fetch_assoc()) {
                $sconfigs[] = $row["config"];
            }
        }
        if ($generalConfigs != null) {
            while ($row = $generalConfigs->fetch_assoc()) {
                $sconfigs[] = $row["config"];
            }
        }
        $result = $up->fetch_assoc();
        $xconfigs = fetchXConfigs($result["panel"], $result["username"]);

        respondWithSuccess("اطلاعات دریافت شد", $sconfigs, $xconfigs);
    } else {
        respondWithError("No data found for the given UUID");
    }
}
function processResults($arrays)
{
    $sconfigs = [];
    $panel = [];
    $email = '';

    foreach ($arrays as $array) {
        if (isset($array['panel'])) {
            $panel = $array['panel'];
        }
        if (isset($array['username'])) {
            $email = $array['username'];
        }
        if (isset($array['config'])) {
            $sconfigs[] = $array['config'];
        }
    }

    return [$sconfigs, $panel, $email];
}
function getUUIDFromRequest()
{
    if (!isUUIDPresent()) {
        respondWithError("UUID is missing in the request");
    }
    return $_POST["uuid"];
}

function isUUIDPresent()
{
    return isset($_POST["uuid"]) && !empty($_POST["uuid"]);
}

function fetchUsernameAndPanels($mysqli, $uuid)
{
    $stmt = prepareStatement($mysqli, 'SELECT users.panel, users.username FROM users WHERE users.uuid = ?;');
    bindUUID($stmt, $uuid);
    executeStatement($stmt);
    $result = $stmt->get_result();

    return $result;
}
function fetchSconfigs($mysqli, $uuid)
{
    $stmt = prepareStatement($mysqli, 'SELECT configs.config FROM configs WHERE configs.uuid = ?;');
    bindUUID($stmt, $uuid);
    executeStatement($stmt);
    $result =  $stmt->get_result();
    if ($result->num_rows > 0)
        return $result;
    return null;
}
function fetchGeneralConfigs($mysqli)
{
    $stmt = prepareStatement($mysqli, 'SELECT *  FROM configs WHERE configs.uuid LIKE "general"');
    executeStatement($stmt);
    $result =  $stmt->get_result();
    if ($result->num_rows > 0)
        return $result;
    return null;
}

function prepareStatement($mysqli, $query)
{
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        respondWithError("Failed to prepare statement: " . $mysqli->error);
    }
    return $stmt;
}

function bindUUID($stmt, $uuid)
{
    if (!$stmt->bind_param("s", $uuid)) {
        respondWithError("Failed to bind parameters: " . $stmt->error);
    }
}

function executeStatement($stmt)
{
    if (!$stmt->execute()) {
        respondWithError("Query execution failed: " . $stmt->error);
    }
}

function isResultNotEmpty($result)
{
    return $result->num_rows !== 0;
}

function parseResult($result)
{
    $sconfigs = [];
    $panel = null;
    $email = null;
    while ($row = $result->fetch_assoc()) {
        if (isPanelNull($panel)) {
            $panel = $row["panel"];
            $email = $row["username"];
        }
        $sconfigs[] = $row["config"];
    }
    return [$sconfigs, $panel, $email];
}

function isPanelNull($panel)
{
    return $panel === null;
}

function fetchXConfigs($panel, $email)
{
    if (isPanelNotNull($panel)) {
        return fetchXConfigsFromPanel($panel, $email);
    }
    return null;
}

function isPanelNotNull($panel)
{
    return $panel != null;
}

function fetchXConfigsFromPanel($panel, $email)
{
    $data = jsonDecodePanel($panel);
    $xconfigs = [];
    foreach ($data as $panelItem) {
        $xconfigs[] = fetchXConfigForPanelItem($panelItem, $email);
    }
    $decodedArray = [];
    foreach ($xconfigs as $jsonString) {
        $decodedArray[] = json_decode($jsonString, true);
    }
    return $decodedArray;
}

function jsonDecodePanel($panel)
{
    $data = json_decode($panel, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        respondWithError("Failed to decode JSON panel data: " . json_last_error_msg());
    }
    return $data;
}

function fetchXConfigForPanelItem($panelItem, $email)
{
    $panelUrl = $panelItem['apl'];
    $baseurl = buildBaseUrl($panelUrl);
    $posts = buildPostData($panelItem, $email);
    return curlInit($posts, $baseurl);
}

function buildBaseUrl($panelUrl)
{
    $parsedUrl = parse_url($panelUrl);
    if ($parsedUrl === false) {
        respondWithError("Failed to parse URL: " . $panelUrl);
    }
    return $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . '/api/findClient.php';
}

function buildPostData($panelItem, $email)
{
    $parsedUrl = parse_url($panelItem['apl']);
    return [
        "username" => $panelItem['username'],
        "password" => $panelItem['password'],
        "serverUrl" => $parsedUrl['host'],
        "inbound" => $panelItem['inbound'],
        "email" => $email
    ];
}

function respondWithError($message)
{
    $configs = array();
    echo json_encode(setData("error", $message, $configs, $configs));
    exit();
}

function respondWithSuccess($message, $sconfigs, $xconfigs)
{
    echo json_encode(setData("success", $message, $sconfigs, $xconfigs), JSON_PRETTY_PRINT);
}

function setData($status, $message, $sconfigs, $xconfigs)
{
    return [
        "status" => $status,
        "message" => $message,
        "sconfigs" => $sconfigs,
        "xconfigs" => $xconfigs,
    ];
}
