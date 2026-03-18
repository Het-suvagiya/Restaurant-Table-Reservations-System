<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

// If user is already logged in, redirect home
if (isLoggedIn()) {
    redirect(BASE_URL . 'index.php');
}

$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope('email');
$client->addScope('profile');

// Disable SSL Verification for local WAMP testing
$guzzleClient = new \GuzzleHttp\Client(['verify' => false]);
$client->setHttpClient($guzzleClient);

if (isset($_GET['code'])) {

    // Exchange the authorization code for an access token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        header('Location: ' . BASE_URL . 'login.php?error=Google login failed. ' . htmlspecialchars($token['error_description'] ?? ''));
        exit();
    }

    $client->setAccessToken($token['access_token']);

    // Get user profile information from Google
    $google_oauth = new Google_Service_Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();

    $google_id = $google_account_info->id;
    $email = $google_account_info->email;
    $given_name = $google_account_info->givenName;
    $family_name = $google_account_info->familyName ?? ''; // Handle missing last name
    $picture = $google_account_info->picture;

    // Check if the user exists as a manager (in case they were promoted)
    $stmt = $conn->prepare("SELECT m_id, m_firstname, m_lastname, m_email FROM tbl_manager WHERE google_id = ?");
    $stmt->bind_param("s", $google_id);
    $stmt->execute();
    $manager_result = $stmt->get_result();

    if ($manager_result->num_rows > 0) {
        // User exists as a manager, log them in with manager role
        $manager = $manager_result->fetch_assoc();
        $_SESSION['user_id'] = $manager['m_id'];
        $_SESSION['first_name'] = $manager['m_firstname'];
        $_SESSION['last_name'] = $manager['m_lastname'];
        $_SESSION['email'] = $manager['m_email'];
        $_SESSION['role'] = ROLE_MANAGER;

        $stmt->close();
        redirect(BASE_URL . 'manager/dashboard.php');
    }
    $stmt->close();

    // Check if the user already exists in tbl_users by google_id
    $stmt = $conn->prepare("SELECT u_id, u_firstname, u_lastname, u_email FROM tbl_users WHERE google_id = ?");
    $stmt->bind_param("s", $google_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // User exists via google_id, log them in
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['u_id'];
        $_SESSION['first_name'] = $user['u_firstname'];
        $_SESSION['last_name'] = $user['u_lastname'];
        $_SESSION['email'] = $user['u_email'];
        $_SESSION['role'] = ROLE_USER;

        $stmt->close();
        redirect(BASE_URL . 'index.php');
    } else {
        $stmt->close();

        // Wait, what if they exist as a manager by email but no google_id?
        // Check tbl_manager by email first
        $stmt = $conn->prepare("SELECT m_id, m_firstname, m_lastname, m_email FROM tbl_manager WHERE m_email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $manager_email_result = $stmt->get_result();

        if ($manager_email_result->num_rows > 0) {
            // Found by email in manager table, link google_id to it
            $manager = $manager_email_result->fetch_assoc();
            $stmt->close();

            $update_stmt = $conn->prepare("UPDATE tbl_manager SET google_id = ? WHERE m_id = ?");
            $update_stmt->bind_param("si", $google_id, $manager['m_id']);
            $update_stmt->execute();
            $update_stmt->close();

            // Log them in as manager
            $_SESSION['user_id'] = $manager['m_id'];
            $_SESSION['first_name'] = $manager['m_firstname'];
            $_SESSION['last_name'] = $manager['m_lastname'];
            $_SESSION['email'] = $manager['m_email'];
            $_SESSION['role'] = ROLE_MANAGER;

            redirect(BASE_URL . 'manager/dashboard.php');
        } else {
            $stmt->close();

            // Check if they exist in tbl_users by email
            $stmt = $conn->prepare("SELECT u_id, u_firstname, u_lastname, u_email FROM tbl_users WHERE u_email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $email_result = $stmt->get_result();

            if ($email_result->num_rows > 0) {
                // Found by email, link google_id to existing account
                $user = $email_result->fetch_assoc();
                $stmt->close();

                $update_stmt = $conn->prepare("UPDATE tbl_users SET google_id = ? WHERE u_id = ?");
                $update_stmt->bind_param("si", $google_id, $user['u_id']);
                $update_stmt->execute();
                $update_stmt->close();

                // Log them in
                $_SESSION['user_id'] = $user['u_id'];
                $_SESSION['first_name'] = $user['u_firstname'];
                $_SESSION['last_name'] = $user['u_lastname'];
                $_SESSION['email'] = $user['u_email'];
                $_SESSION['role'] = ROLE_USER;

                redirect(BASE_URL . 'index.php');
            } else {
                $stmt->close();

                // Completely new user! Insert them into tbl_users.
                // Since password is now nullable, we pass NULL or leave it empty.
                // Gender and Dob can be NULL initially since Google doesn't provide them standardly.
                if (isset($picture)) {
                    // Strip the `=s96-c` (or similar) parameter to get the original high-resolution image
                    $pictureUrl = preg_replace('/=s\d+-c/', '', $picture);
                } else {
                    $pictureUrl = null;
                }

                $insert_stmt = $conn->prepare("INSERT INTO tbl_users (u_firstname, u_lastname, u_email, u_password, google_id, u_image) VALUES (?, ?, ?, NULL, ?, ?)");
                $insert_stmt->bind_param("sssss", $given_name, $family_name, $email, $google_id, $pictureUrl);

                if ($insert_stmt->execute()) {
                    $new_user_id = $insert_stmt->insert_id;
                    $insert_stmt->close();

                    // Log them in
                    $_SESSION['user_id'] = $new_user_id;
                    $_SESSION['first_name'] = $given_name;
                    $_SESSION['last_name'] = $family_name;
                    $_SESSION['email'] = $email;
                    $_SESSION['role'] = ROLE_USER;

                    // Send Welcome Email
                    require_once 'includes/mail_helper.php';
                    $subject = "Welcome to QuickTable!";
                    $body = "<h1>Welcome to QuickTable</h1><p>Hi " . htmlspecialchars($given_name) . ",</p><p>Thank you for signing up with Google. Start exploring the best restaurants and make your bookings easily.</p>";
                    sendMail($email, $subject, $body);

                    redirect(BASE_URL . 'index.php');
                } else {
                    header('Location: ' . BASE_URL . 'login.php?error=Database error during Google Registration.');
                    exit();
                }
            }
        }
    }
} else {
    // If someone visits the callback without a code parameter
    redirect(BASE_URL . 'login.php');
}
