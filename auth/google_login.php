<?php
session_start();
require "google_config.php";

$login_url = $client->createAuthUrl();
header("Location: ".$login_url);
exit;
?>
