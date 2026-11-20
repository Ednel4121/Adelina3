<?php
// Binubuksan ang koneksyon sa MySQL database na ginagamit ng HOA application.
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "adelina3_hoa";

$conn = new mysqli($host, $user, $pass, $dbname);

// Itinitigil ang request kung hindi maaabot ang database upang hindi magpatuloy nang walang data.
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
