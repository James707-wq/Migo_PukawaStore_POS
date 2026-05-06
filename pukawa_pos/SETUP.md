# Pukawa POS System - Setup Guide

## Installation Steps

### 1. Database Setup
- Create a MySQL database named `pukawa_pos`
- Import `database.sql` into your database
- Update credentials in `config.php` if needed

### 2. Web Server Configuration
The application is designed to work in any folder. Just extract files and access via:
```
http://yourdomain.com/path/to/pukawa_pos/
```

### 3. Default Login Credentials
- **Admin User**: username=`admin` | password=`admin123`
- **Cashier User**: username=`cashier` | password=`cashier123`

### 4. Folder Permissions
Make sure the following directories are writable (if logging is enabled):
- `./includes/` - for any dynamic includes
- `./api/` - for API responses

## Troubleshooting "URL Not Found"

If you see "url not found" errors when navigating:

1. **Check your actual URL path**
   - Open your browser and check the address bar
   - Example: `http://localhost/pukawa_pos_system/pukawa_pos/`

2. **Verify all PHP files exist**
   - login.php ✓
   - signup.php ✓
   - dashboard.php ✓
   - users.php ✓
   - products.php ✓
   - pos.php ✓
   - reports.php ✓

3. **Clear browser cache**
   - Press Ctrl+Shift+Delete to clear cache
   - Then try accessing the page again

4. **Check web server error logs**
   - Apache: Look in `apache_error.log`
   - Nginx: Look in `error.log`

## File Structure
```
pukawa_pos/
├── config.php              (Database & global settings)
├── index.php               (Router - auto redirects)
├── login.php               (Login page)
├── signup.php              (User registration)
├── dashboard.php           (Admin dashboard)
├── pos.php                 (Point of Sale)
├── products.php            (Product management)
├── users.php               (User management)
├── reports.php             (Sales reports)
├── database.sql            (Database schema)
├── .htaccess               (Web server config)
├── api/                    (REST API endpoints)
│   ├── checkout.php
│   └── products.php
├── includes/               (Template files)
│   ├── header.php
│   └── footer.php
├── css/
│   └── app.css
├── js/
│   ├── app.js
│   └── pos.js
└── img/                    (Store logo & images)
```
