<?php


if ($mysqli->connect_errno == 0)
    $username = $_POST["username"];

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

function updateActivePanel($mysqli, $Panel, $username)
{
    $query = "UPDATE users SET users.panel = '$Panel' WHERE users.username = '$username'";
    $mysqli->query($query);
}

function findMostOptimizeServer($mysqli)
{
    $result = getPanels($mysqli);
    $panels = categorizingPanels($result);
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
    return $result;
}

function categorizingPanels($result)
{
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
