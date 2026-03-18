<?php
require_once 'config.php';
$res = $conn->query("SHOW CREATE TABLE tbl_users");
if ($res) {
    $row = $res->fetch_assoc();
    echo $row['Create Table'];
} else {
    echo "Error: " . $conn->error;
}
?>
