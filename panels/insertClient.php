<?php

require_once "api.php";
$v2m = new V2rayManager();
$username = $_POST["username"];
$password = $_POST["password"];
$inbound = $_POST["inbound"];
$email = $_POST["email"];
$port = $_POST['port'];
$sublink = $_POST['sublink'];
$gig = $_POST['gig'];
$days = $_POST['days'];
$clientManager = new ClientManager($port, $sublink);
$clientManager->login($username, $password);
$clientManager->addnewClient($inbound, $v2m->genUuid(), $email, '', '', '', $v2m->gigabytesToBytes($gig), -$v2m->daysToMilliseconds($days), 0, 'chrome', false);
