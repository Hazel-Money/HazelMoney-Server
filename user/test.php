<?php
$env = parse_ini_file("../.env");
echo $env["default_pfp_url"];
$image_data = file_get_contents($env["default_pfp_url"]);
echo file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/default.png", $image_data);