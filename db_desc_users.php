<?php
require_once 'config.php';
$columns = $conn->query("DESC tbl_users");
if ($columns) {
    while ($row = $columns->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Error describing tbl_users: " . $conn->error;
}
?>
