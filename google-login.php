<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

// Initialize the Google Client
$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);

// Request access to the user's email and profile
$client->addScope('email');
$client->addScope('profile');

// Generate the login URL
$auth_url = $client->createAuthUrl();

// Redirect to Google's consent screen
header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
exit();
