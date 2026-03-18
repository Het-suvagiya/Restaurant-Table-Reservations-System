# 🍽️ QuickTable — Restaurant Table Booking Platform

> A full-stack PHP web application for discovering and booking tables at the best restaurants in your city.

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?style=flat&logo=mysql&logoColor=white)
![TailwindCSS](https://img.shields.io/badge/TailwindCSS-3.x-06B6D4?style=flat&logo=tailwindcss&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=flat)

---

## 📖 About The Project

**QuickTable** is a premium restaurant table booking platform with three user roles — **Customer**, **Restaurant Manager**, and **Admin**. Customers can browse restaurants, make reservations, leave reviews, and save favorites. Restaurant owners can register, manage their profile, and track bookings. Admins oversee the entire platform through a dedicated control panel.

### ✨ Key Features

- 🔍 **Smart Search** — Search restaurants by name, cuisine, or location
- 📅 **Real-time Booking** — Time-slot conflict detection prevents double bookings
- ⭐ **Reviews & Ratings** — Submit, view, and manage restaurant reviews
- ❤️ **Favorites** — AJAX-powered, no page reload
- 🔐 **Google OAuth Login** — Sign in with Google
- 📧 **Email Notifications** — Automated emails for registration, approval, and rejection (PHPMailer + Gmail SMTP)
- 👤 **Three-Role System** — Customer / Restaurant Manager / Admin
- 🛡️ **Admin Panel** — Approve/block restaurants, manage users and bookings
- 🏪 **Manager Dashboard** — Setup restaurant profile, manage menus, track reservations
- 🎨 **Premium UI** — Glassmorphism design with smooth animations

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.x (procedural + OOP) |
| Database | MySQL 8.x via mysqli |
| Frontend | Tailwind CSS, Vanilla JavaScript |
| Email | PHPMailer (SMTP via Gmail) |
| Auth | PHP Sessions + Google OAuth 2.0 |
| Server | Apache (WAMP / XAMPP / LAMP) |
| Dependencies | Composer |

---

## 📋 Prerequisites

Before you begin, make sure you have the following installed:

- ✅ [WAMP](https://www.wampserver.com/) / [XAMPP](https://www.apachefriends.org/) / any Apache + PHP + MySQL stack
- ✅ PHP **8.0 or higher**
- ✅ MySQL **8.0 or higher**
- ✅ [Composer](https://getcomposer.org/) (PHP dependency manager)
- ✅ A **Gmail account** with an [App Password](https://myaccount.google.com/apppasswords) enabled
- ✅ A **Google Cloud Console** project with OAuth 2.0 credentials _(for Google Login)_

---

## 🚀 Setup Instructions

### Step 1 — Clone the Repository

```bash
git clone https://github.com/YOUR_USERNAME/quicktable.git
```

Move the project folder into your web server's root directory:
- **WAMP:** `C:\wamp\www\quicktable\`
- **XAMPP:** `C:\xampp\htdocs\quicktable\`
- **Linux:** `/var/www/html/quicktable/`

---

### Step 2 — Install PHP Dependencies

Open a terminal inside the project folder and run:

```bash
composer install
```

This installs **PHPMailer** and **Google API Client** from `composer.json`.

---

### Step 3 — Create the Database

1. Open **phpMyAdmin** → `http://localhost/phpmyadmin`
2. Click **New** and create a database named exactly:
   ```
   quicktable_db
   ```
3. Select the newly created database, click the **Import** tab, and upload:
   ```
   QuickTable_DB_setup.sql
   ```
   _(This file is included in the root of the repository and contains the full database schema with all required tables and a default admin account.)_

> ⚠️ **Important:** Make sure you select the `quicktable_db` database **before** importing. Do not import into the default `mysql` or `information_schema` databases.

---

### Step 4 — Configure the Application

1. Copy the example config file:
   ```bash
   # Windows
   copy config.example.php config.php

   # macOS / Linux
   cp config.example.php config.php
   ```

2. Open `config.php` and fill in your actual values:

```php
// ── Database ──────────────────────────────────────────────────────────
$host = 'localhost';
$user = 'root';           // Your MySQL username
$pass = '';               // Your MySQL password (blank for WAMP default)
$db   = 'quicktable_db';

// ── Google OAuth ──────────────────────────────────────────────────────
define('GOOGLE_CLIENT_ID',     'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');

// ── SMTP / Email ──────────────────────────────────────────────────────
define('SMTP_USER',      'your_email@gmail.com');
define('SMTP_PASS',      'YOUR_GMAIL_APP_PASSWORD'); // 16-char App Password
define('SMTP_FROM',      'your_email@gmail.com');
define('SMTP_FROM_NAME', 'QuickTable');
```

---

### Step 5 — Set Up Google OAuth (Optional but Recommended)

> Skip this step if you don't need Google login.

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Navigate to **APIs & Services → Credentials**
4. Click **Create Credentials → OAuth 2.0 Client ID**
5. Set **Application type** to **Web application**
6. Add the following **Authorized Redirect URIs**:
   ```
   http://localhost/quicktable/google-callback.php
   ```
7. Copy the **Client ID** and **Client Secret** into `config.php`

---

### Step 6 — Set Up Gmail SMTP (for Email Notifications)

1. Log in to your Google Account → [Google Account Security](https://myaccount.google.com/security)
2. Enable **2-Step Verification** (required for App Passwords)
3. Go to [App Passwords](https://myaccount.google.com/apppasswords)
4. Generate a new App Password for **Mail** → **Windows Computer** (or any)
5. Copy the 16-character password into `config.php` as `SMTP_PASS`

---

### Step 7 — Create Uploads Directory

Make sure the uploads folder exists and is writable:

```bash
# Windows — this folder is usually already there after cloning
mkdir uploads

# Linux / macOS
mkdir -p uploads && chmod 755 uploads
```

---

### Step 8 — Run the Application

1. Start your WAMP/XAMPP server (Apache + MySQL must both be running)
2. Open your browser and visit:
   ```
   http://localhost/quicktable/
   ```

---

## 👤 Default Admin Login

After importing the SQL database, you can log in as admin using:

| Field | Value |
|-------|-------|
| Email | `admin@quicktable.com` |
| Password | `admin123` _(change this immediately!)_ |

> ⚠️ **Change the default admin password** immediately after first login via the Admin Profile page.

---

## 📁 Project Structure

```
quicktable/
├── admin/                  # Admin panel pages
│   ├── dashboard.php
│   ├── applications.php    # Restaurant approval workflow
│   ├── restaurants.php
│   ├── users.php
│   └── bookings.php
├── manager/                # Restaurant manager panel
│   ├── dashboard.php
│   ├── setup.php           # Restaurant profile & menu setup
│   └── bookings.php
├── assets/                      # CSS, JS, images
├── uploads/                     # User-uploaded restaurant images (git-ignored)
├── vendor/                      # Composer dependencies (git-ignored)
├── QuickTable_DB_setup.sql      # ✅ Database schema — import this into phpMyAdmin
├── config.php                   # ⚠️ Your private config — NOT committed
├── config.example.php           # ✅ Safe template — copy this to config.php
├── index.php                    # Homepage
├── book.php                     # Booking flow
├── register.php                 # User registration
├── login.php                    # Login page
├── register_restaurant.php      # Restaurant owner registration
├── my_bookings.php              # User booking history
├── favorites.php                # Favorites API
├── header.php                   # Shared header/nav
├── footer.php                   # Shared footer
└── functions.php                # Global helper functions
```

---

## 🔐 Security Notes

- `config.php` is **excluded from Git** via `.gitignore` — your credentials are safe
- All database queries use **prepared statements** (SQL injection protected)
- Passwords are hashed with `password_hash()` (bcrypt)
- Google OAuth tokens are validated server-side
- Role-based access control enforced on every admin/manager page

---

## 🤝 Contributing

Pull requests are welcome! For major changes, please open an issue first to discuss what you would like to change.

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).

---

## 📬 Contact

Built by **Het Suvagiya** — feel free to reach out via GitHub Issues.
