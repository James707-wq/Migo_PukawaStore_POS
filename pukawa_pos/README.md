# Pukawa Store POS System
### Convenience Store Point of Sale — XAMPP Edition
**Version 1.0.0 | Built with PHP, MySQL, Bootstrap 5, JavaScript**

---

## 📁 File Structure

```
pos_system/
├── index.php              ← Entry point (redirects to login or dashboard)
├── login.php              ← Authentication page
├── logout.php             ← Session destroyer
├── dashboard.php          ← Main dashboard with stats & charts
├── products.php           ← Product inventory CRUD
├── pos.php                ← POS / Cashier interface
├── reports.php            ← Sales reports with charts
├── users.php              ← User management (admin only)
├── config.php             ← DB connection, constants, helpers
│
├── api/
│   ├── products.php       ← JSON API: list, search, barcode lookup
│   └── checkout.php       ← JSON API: process transaction, deduct stock
│
├── includes/
│   ├── header.php         ← HTML head, sidebar, topbar
│   └── footer.php         ← Scripts, closing tags
│
├── css/
│   └── app.css            ← All custom styles
│
├── js/
│   ├── app.js             ← Shared utilities (toast, confirm, API helpers)
│   └── pos.js             ← Full POS cart logic, scanner, checkout
│
├── database.sql           ← Complete MySQL schema + seed data
└── README.md              ← This file
```

---

## ⚙️ XAMPP Installation Guide (Step by Step)

### Step 1 — Download & Install XAMPP

1. Download XAMPP from **https://www.apachefriends.org/**
2. Choose the version with **PHP 8.1+**
3. Run the installer (Windows: `xampp-windows-installer.exe`)
4. Install to `C:\xampp` (default)
5. Launch **XAMPP Control Panel**

### Step 2 — Start Apache & MySQL

1. Open **XAMPP Control Panel**
2. Click **Start** next to **Apache**
3. Click **Start** next to **MySQL**
4. Both should show green "Running" status

### Step 3 — Copy Project Files

1. Open your file explorer
2. Navigate to: `C:\xampp\htdocs\`
3. Create a new folder named: **`pos_system`**
4. Copy ALL project files into: `C:\xampp\htdocs\pos_system\`

Your final path should look like:
```
C:\xampp\htdocs\pos_system\index.php
C:\xampp\htdocs\pos_system\login.php
C:\xampp\htdocs\pos_system\dashboard.php
... etc
```

### Step 4 — Create the Database

**Option A: Using phpMyAdmin (Recommended)**

1. Open your browser, go to: `http://localhost/phpmyadmin`
2. Click **"New"** in the left sidebar
3. Type database name: `quickmart_pos`
4. Click **"Create"**
5. Click on `quickmart_pos` in the left sidebar
6. Click the **"SQL"** tab at the top
7. Open the `database.sql` file from the project folder
8. Copy all its contents
9. Paste into the SQL editor
10. Click **"Go"** to execute
11. You should see all tables and sample data created ✓

**Option B: Using MySQL CLI**
```bash
cd C:\xampp\mysql\bin
mysql -u root -p
# (press Enter when asked for password — blank by default)
source C:/xampp/htdocs/pos_system/database.sql
exit
```

### Step 5 — Configure Database Connection

Open `config.php` and verify/update these lines:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');     // Your MySQL username
define('DB_PASS', '');         // Your MySQL password (blank for XAMPP default)
define('DB_NAME', 'quickmart_pos');
```

Also update store information:
```php
define('STORE_NAME',    'Your Store Name Here');
define('STORE_ADDRESS', 'Your Store Address');
define('STORE_PHONE',   'Your Phone Number');
define('STORE_TIN',     'Your TIN Number');
```

### Step 6 — Access the System

Open your browser and go to:
```
http://localhost/pos_system/
```

You will be redirected to the **Login page**.

---

## 🔑 Default Login Credentials

| Role     | Username   | Password     |
|----------|------------|--------------|
| Admin    | `admin`    | `admin123`   |
| Cashier  | `cashier`  | `cashier123` |

> ⚠️ **IMPORTANT**: Change these passwords immediately after first login in the Users section!

---

## 🔧 Features Overview

### 1. Authentication
- Secure login with PHP sessions
- Password hashing using `password_hash()` (bcrypt)
- Role-based access: Admin vs Cashier
- Session lifetime: 8 hours (configurable in `config.php`)

### 2. Dashboard
- Today's sales & transaction count
- Total products & low stock alerts
- Monthly revenue
- Last 7-day revenue bar chart
- Recent transactions list
- Low stock alerts table
- Quick action buttons

### 3. Product Inventory
- Full CRUD (Create, Read, Update, Delete)
- Fields: barcode, name, category, price, cost, stock, low-stock level, expiry date
- Search by name or barcode
- Filter by category
- Low stock highlight
- Expiry date warning (≤30 days)
- Barcode scanner in product form

### 4. POS / Cashier Page
- Product grid with search & category filter
- Barcode scanner (uses phone/webcam camera)
- Manual barcode entry
- Cart with real-time qty adjustments
- Auto-computed subtotal, discount, grand total
- Quick denomination buttons (₱20, ₱50, ₱100, ₱200, ₱500, ₱1000)
- Payment & change calculator
- Prevents checkout if payment insufficient
- Supports Cash, GCash, Card

### 5. Barcode Scanning
- Uses **html5-qrcode** library (CDN, no install needed)
- Works with mobile camera and laptop webcam
- Auto-detects barcode → auto-adds to cart
- Also available in product add/edit form

### 6. Receipt
- Auto-generated after successful checkout
- Shows: store info, TXN#, date/time, items, totals, payment, change
- Print button (browser print dialog)
- Proper receipt format

### 7. Sales Reports (Admin only)
- Quick ranges: Today, This Week, This Month, Custom Date Range
- Summary: revenue, transactions, average sale, discounts
- Revenue line chart
- Payment method doughnut chart
- Best-selling products table
- Daily breakdown table

### 8. User Management (Admin only)
- Add/Edit users
- Assign roles (Admin/Cashier)
- Activate/Deactivate accounts
- Transaction count per user
- Secure password management

---

## 🗄️ Database Schema

| Table               | Purpose                              |
|---------------------|--------------------------------------|
| `users`             | System users with roles              |
| `categories`        | Product categories                   |
| `products`          | Product inventory                    |
| `transactions`      | Transaction headers                  |
| `transaction_items` | Line items per transaction           |
| `v_daily_sales`     | View for daily sales reporting       |
| `v_product_sales`   | View for product sales reporting     |

---

## 📱 Barcode Scanner Setup (Mobile)

To use barcode scanning with your **mobile phone**:

1. Make sure your phone and PC are on the **same WiFi network**
2. Find your PC's local IP: open CMD → type `ipconfig` → look for IPv4 Address (e.g., `192.168.1.5`)
3. On your phone browser, go to: `http://192.168.1.5/pos_system/pos.php`
4. When prompted, allow camera access
5. Click the **Scan** button and point camera at barcode

> Note: Some browsers require HTTPS for camera access. For local testing, Chrome on Android allows HTTP on local networks.

---

## 🐛 Troubleshooting

**"Connection refused" / can't access the site**
- Make sure Apache and MySQL are RUNNING in XAMPP Control Panel

**"Access denied for user 'root'"**
- Check `config.php` DB_PASS setting
- In phpMyAdmin, verify root user has no password set

**"Table doesn't exist" errors**
- Re-run `database.sql` in phpMyAdmin

**Camera not working for barcode scan**
- Allow camera permission in browser
- Try Google Chrome
- On mobile, use the local IP address (not localhost)

**Low stock alerts not showing**
- Check that `low_stock_level` column is set in products table
- Verify product stock is at or below that level

---

## 👨‍💻 Technology Stack

| Layer      | Technology                   |
|------------|------------------------------|
| Backend    | PHP 8.1+ (PDO, Sessions)     |
| Database   | MySQL 8.0 (via XAMPP)        |
| Frontend   | HTML5, CSS3, JavaScript ES6+ |
| UI Library | Bootstrap 5.3                |
| Icons      | Bootstrap Icons 1.11         |
| Charts     | Chart.js 4.4                 |
| Scanner    | html5-qrcode 2.3.8           |
| Fonts      | Google Fonts (Syne, DM Sans) |
| Server     | Apache (XAMPP)               |

---

## 📝 Group Project Notes

This project was built as a **college group project** demonstrating:
- Full-stack web development with PHP & MySQL
- RESTful API design with JSON responses
- Session-based authentication with role access control
- Real-time JavaScript cart management
- Mobile-compatible barcode scanning via WebRTC
- Database transactions with rollback safety
- Bootstrap 5 responsive design

---

*Pukawa Store POS System v1.0.0 — Built for educational purposes*
