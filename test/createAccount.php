<?php

require_once "helper.php";
error_reporting(E_ALL);
ini_set('display_errors', 'On');
header('Content-Type: application/json');
$mysqli = connectToDataBase();
if ($mysqli->connect_errno == 0) {
    try {
        $username = $_POST["username"];
        $password = $_POST["password"];
        $fullName = $_POST["full_name"];
        $uuid = uniqid();
        $query = '
        INSERT INTO users (users.uuid, users.username, users.password, users.full_name,
         users.left_payment, users.login_count, users.panel, users.status)
         VALUES (NULL, "?", "?", "?", "0", "0", NULL, "1")
         ';
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('ss', $username, $password, $fullName);
        $stmt->execute();
        echo $stmt->errno;
        $result = $stmt->get_result();

        if (thereWasResults($result)) {
            $row = $result->fetch_assoc();
            $loginCount = $row["login_count"];
            $Panels = $row["panel"];
            $fullName = $row['full_name'];
            $leftPayment = $row['left_payment'];
            $status = $row["status"];
            if ($status = 1) {
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

// functions 

function thereWasResults($result)
{
    return $result->num_rows !== 0;
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
