<?php
// functions.php
// Common helper functions used throughout the application.

// Redirect helper
function redirect($path)
{
    header("Location: $path");
    exit();
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function getUserRole()
{
    return $_SESSION['role'] ?? ROLE_USER;
}

function isAdmin()
{
    return getUserRole() === ROLE_ADMIN;
}

function isManager()
{
    return getUserRole() == ROLE_MANAGER;
}

// Utility routines previously kept in db_utils.php
function generatePasswordHash($password)
{
    // outputs a hash for development/testing purposes
    echo "Generated Hash for '$password':\n";
    echo password_hash($password, PASSWORD_DEFAULT) . "\n\n";
}

function addMaxGuestsColumn($conn)
{
    echo "Adding max_guests column...\n";
    $query = "ALTER TABLE restaurants ADD COLUMN max_guests INT DEFAULT 20 AFTER avg_price";
    if ($conn->query($query) === TRUE) {
        echo "Column max_guests added successfully\n\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n\n";
    }
}

function checkRestaurantColumns($conn)
{
    echo "Columns in 'restaurants' table:\n";
    $result = $conn->query("SHOW COLUMNS FROM restaurants");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "- " . $row['Field'] . "\n";
        }
    } else {
        echo "Error: " . $conn->error . "\n";
    }
    echo "\n";
}

?>