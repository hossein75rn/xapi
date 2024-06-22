<?php

require_once "helper.php";
error_reporting(E_ALL);
ini_set('display_errors', 'On');
header('Content-Type: application/json');
$host = "localhost";
$database = "vpn";
$username = "userName";
$password = "password";
$mysqli = new mysqli($host, $username, $password, $database);
if ($mysqli->connect_errno == 0) {
    try {
        $username = $_POST["username"];
        $password = $_POST["password"];
        $uuid = uniqid();
        $query = '  SELECT * FROM users WHERE users.username = ? AND users.password = ?;  ';
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('ss', $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = array();
        $data = setDefaultData("نام کاربری یا کلمه عبور اشتباه است");

        if ($result->num_rows !== 0) {
            $row = $result->fetch_assoc();
            $loginCount = $row["login_count"];
            $Panels = $row["panel"];
            $fullName = $row['full_name'];
            $leftPayment = $row['left_payment'];
            $status = $row["status"];
            if ($loginCount == 0) {
                createNewAccountInBestServer($mysqli, $username);
                //$data = successLogin($uuid, $fullName, $leftPayment);
            }
            if ($status == 0) {
                $data = setDefaultData("اکانت توسط سیستم مسدود شده");
            } else if ($status = 1) {
                if ($loginCount <= 5) {
                    $loginCount++;
                    updateUser($uuid, $loginCount, $username, $mysqli);
                    updateConfigs($mysqli, $uuid, $row["uuid"]);
                    $data = successLogin($uuid, $fullName, $leftPayment);
                } else {
                    $data = setDefaultData("تعداد وارد شدن بیش از حد مجاز است");
                }
            } else if ($status > 1) {
                if ($loginCount < $status) {
                    updateUser($row["uuid"], $loginCount, $username, $mysqli);
                } else {
                    setDefaultData("تعداد کاربران وارد شده$status نقر اکانت تکمیل است");
                }
            }
        }
        echo json_encode($data);
    } catch (mysqli_sql_exception $e) {
        echo $e;
    }
} else {
    echo 'connection failed' . $mysqli->connect_errno;
    mysqli_connect_error();
}


function findMostOptimizeServer($mysqli)
{
    $panels = getPanels($mysqli);
    //echo json_encode($panels['1']);
    $optimizedServers = array();

    foreach ($panels as $groupPanels) {
        $tot = 0;
        $result = null;
        $group = null;

        foreach ($groupPanels as $key => $panel) {
            $group = $panel['group'];
            $url = $panel["url"];
            $parsedUrl = parse_url($url);
            $baseurl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . '/api/countClients.php';
            $posts["username"] = $panel["username"];
            $posts["password"] = $panel["password"];
            $jd = json_decode(curlInit($posts, $baseurl));
            if ($key == 0 || $jd->totalClients < $tot) {
                $tot = $jd->totalClients;
                $result["username"] = $panel["username"];
                $result["password"] = $panel["password"];
                $result["port"] = $parsedUrl["port"];
                $result["sublink"] = isset($parsedUrl["path"]) ? $parsedUrl["path"] : "";
                $result["inbound"] = $jd->inbound;
                // the email working as a temp holder of url it will change when creating user curl occurs
                $result["email"] = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
                $result["apl"] = $url;
                $optimizedServers[$group] = $result;
            }
        }
    }
    return $optimizedServers;
}

function getPanels($mysqli)
{
    $query = " SELECT * FROM panels";
    $result = $mysqli->query($query);
    $groupedPanels = array();

    if ($result->num_rows > 0) {
        // Output data of each row
        while ($row = $result->fetch_assoc()) {
            $group = $row['group'];
            if (!isset($groupedPanels[$group])) {
                $groupedPanels[$group] = array();
            }
            $groupedPanels[$group][] = $row;
        }
    }
    return $groupedPanels;
}

function updateActivePanel($mysqli, $Panel, $username)
{
    $query = "UPDATE users SET users.panel = '$Panel' WHERE users.username = '$username'";
    $mysqli->query($query);
}
function createNewAccountInBestServer($mysqli, $username)
{
    $optimizedServers = findMostOptimizeServer($mysqli);
    $panelInfo = array();
    foreach ($optimizedServers as $key => $result) {
        // result[email] is holding panel url
        $p = array();
        $p["apl"] = $result['apl'];
        $p["inbound"] = $result['inbound'];
        $p["username"] = $result['username'];
        $p["password"] = $result['password'];
        $panelInfo[] = $p;
        $url = $result["email"] . "/api/insertClient.php";
        $result["email"] = $username;
        $result["gig"] = 50;
        $result["days"] = 37;
        if ($key == 9) {
            $result["gig"] = 0;
            $result["days"] = 37;
        }
        curlInit($result, $url);
    }
    updateActivePanel($mysqli, json_encode($panelInfo), $username);
}


function setDefaultData($message)
{
    $data = array();
    $data["status"] = "failed";
    $data["message"] = $message;
    $data["uuid"] = "";
    $data["fullName"] = "";
    $data["leftPayments"] = 0;
    return $data;
}

function successLogin($uuid, $fullName, $leftPayment)
{
    $data["status"] = "success";
    $data["message"] = "اجازه ورود صادر شد ";
    $data["uuid"] = $uuid;
    $data["fullName"] = $fullName;
    $data["leftPayments"] = $leftPayment;
    return $data;
}


function updateUser($uuid, $loginCount, $username, $mysqli)
{
    $query = "UPDATE users SET users.uuid='$uuid', users.login_count = $loginCount WHERE users.username = '$username'";
    $mysqli->query($query);
}
function updateConfigs($mysqli, $uuidNew, $uuidOld)
{
    $query = "UPDATE configs SET configs.uuid='$uuidNew' WHERE configs.uuid = '$uuidOld'";
    $mysqli->query($query);
}
