<?php
// Mocking the POST data
$_POST['first_name'] = 'Test';
$_POST['last_name'] = 'User';
$_POST['email'] = 'test_' . time() . '@example.com';
$_POST['password'] = 'password123';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

// Capture output
ob_start();
include 'register.php';
$output = ob_get_clean();

echo "Raw Output:\n";
echo "-----------\n";
echo $output;
echo "\n-----------\n";

// Try to decode to see if it's valid JSON
$json = json_decode($output, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "Valid JSON detected!\n";
    print_r($json);
} else {
    echo "INVALID JSON! Error: " . json_last_error_msg() . "\n";
}
?>
