# Barangay Agri-Market Platform - Installation Guide

## Quick Start

### 1. Prerequisites

- Web server (Apache/Nginx)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Modern web browser

### 2. Installation Steps

#### Step 1: Download/Clone the Project

```bash
# If using Git
git clone [repository-url]
cd barangay-agri-market

# Or download and extract the ZIP file
```

#### Step 2: Set Up Database

1. Create a MySQL database named `barangay_agri_market`
2. Import the database schema:
   ```bash
   mysql -u root -p barangay_agri_market < database/schema.sql
   ```

#### Step 3: Configure Database Connection

Edit `config/database.php` and update the database credentials:

```php
private $host = 'localhost';
private $db_name = 'barangay_agri_market';
private $username = 'your_username';
private $password = 'your_password';
```

#### Step 4: Set Up Web Server

- Point your web server document root to the project directory
- Ensure PHP is enabled
- Make sure the `api/` directory is accessible

#### Step 5: Run Setup Script (Optional)

```bash
php setup.php
```

#### Step 6: Access the Application

- Open your web browser
- Navigate to your domain
- Default admin login:
  - Email: `admin@barangay.com`
  - Password: `admin123`

## Detailed Installation

### Apache Configuration

#### Option 1: Virtual Host (Recommended)

```apache
<VirtualHost *:80>
    ServerName agri-market.local
    DocumentRoot /path/to/barangay-agri-market
    <Directory /path/to/barangay-agri-market>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Option 2: Document Root

```apache
DocumentRoot /path/to/barangay-agri-market
<Directory /path/to/barangay-agri-market>
    AllowOverride All
    Require all granted
</Directory>
```

### Nginx Configuration

```nginx
server {
    listen 80;
    server_name agri-market.local;
    root /path/to/barangay-agri-market;
    index index.html index.php;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

### Database Setup

#### Manual Setup

1. Create database:

   ```sql
   CREATE DATABASE barangay_agri_market;
   ```

2. Import schema:

   ```bash
   mysql -u root -p barangay_agri_market < database/schema.sql
   ```

3. Create admin user (if not already created):
   ```sql
   INSERT INTO users (name, email, password, phone, user_type, is_verified, is_active)
   VALUES ('Barangay Admin', 'admin@barangay.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+63 123 456 7890', 'admin', 1, 1);
   ```

#### Using Setup Script

```bash
php setup.php
```

## Configuration

### Database Configuration

Edit `config/database.php`:

```php
private $host = 'localhost';        // Database host
private $db_name = 'barangay_agri_market';  // Database name
private $username = 'root';         // Database username
private $password = '';             // Database password
```

### Application Settings

- Update contact information in footer sections
- Modify barangay-specific information
- Customize categories in database if needed

## Security Considerations

### File Permissions

```bash
# Set appropriate permissions
chmod 755 /path/to/barangay-agri-market
chmod 644 /path/to/barangay-agri-market/*
chmod 755 /path/to/barangay-agri-market/api/
chmod 644 /path/to/barangay-agri-market/api/*
```

### Database Security

- Use strong passwords
- Limit database user permissions
- Enable SSL for database connections in production

### Web Server Security

- Enable HTTPS in production
- Keep server software updated
- Use security headers (included in .htaccess)

## Troubleshooting

### Common Issues

#### Database Connection Error

- Check database credentials in `config/database.php`
- Ensure MySQL service is running
- Verify database exists

#### 404 Errors on API Calls

- Check web server configuration
- Ensure `api/` directory is accessible
- Verify URL rewriting is enabled

#### Permission Denied

- Check file permissions
- Ensure web server can read files
- Verify directory permissions

#### Images Not Loading

- Check file paths
- Ensure `assets/images/` directory exists
- Verify image file permissions

### Debug Mode

Enable error reporting in `config/database.php`:

```php
// Add this for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Testing the Installation

### 1. Test Database Connection

Visit: `http://your-domain/api/auth.php`
Should return: `{"success":false,"message":"Method not allowed"}`

### 2. Test User Registration

1. Go to registration page
2. Fill out the form
3. Submit and check for success message

### 3. Test Admin Login

1. Go to login page
2. Use admin credentials
3. Should redirect to admin dashboard

### 4. Test Product Listing

1. Go to products page
2. Should show empty state or sample products

## Production Deployment

### 1. Environment Setup

- Use production database
- Set up SSL certificate
- Configure proper file permissions

### 2. Performance Optimization

- Enable gzip compression
- Set up caching
- Optimize images

### 3. Monitoring

- Set up error logging
- Monitor database performance
- Track user activity

### 4. Backup Strategy

- Regular database backups
- File system backups
- Test restore procedures

## Support

For technical support:

- Check this installation guide
- Review the README.md file
- Contact your system administrator

## Community

This platform is designed for community use. Feel free to:

- Customize for your barangay's needs
- Add new features
- Share improvements with the community

---

**Happy farming and trading! 🌱**
