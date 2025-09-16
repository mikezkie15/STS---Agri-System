# Barangay Agri-Market Web Platform

A comprehensive web application designed to connect local farmers and buyers within barangay communities. This platform facilitates direct trade between agricultural producers and consumers while maintaining community oversight and privacy protection.

## 🌟 Features

### Phase 1 - Core Features (MVP)

#### User Management

- **Three User Types**: Farmers/Fisherfolk (Sellers), Buyers, and Barangay Admin
- **Secure Authentication**: Password hashing and session management
- **User Registration**: Simple signup process with role selection
- **Profile Management**: Users can update their information

#### Product Listings

- **Product Management**: Farmers can list their produce with details
- **Product Categories**: Organized by vegetables, fruits, grains, livestock, etc.
- **Search & Filter**: Easy product discovery with search and category filters
- **Pricing & Inventory**: Set prices and track available quantities

#### Buyer Requests

- **Request System**: Buyers can post what they need
- **Detailed Requests**: Include quantity, unit, and maximum price preferences
- **Request Management**: Buyers can edit or deactivate their requests

#### Barangay Announcements

- **Admin Control**: Barangay admins can post community updates
- **Important Notifications**: Mark announcements as important
- **Public Access**: All users can view announcements

#### Contact System

- **Privacy Protection**: Personal contact information is protected
- **Barangay Mediation**: Contact through barangay office initially
- **Direct Communication**: After approval, direct contact is enabled

### Phase 2 - Enhanced Features

#### Verification System

- **Seller Verification**: Admin can verify farmers with ✅ badge
- **Trust Building**: Verified sellers gain community trust

#### Rating System

- **Simple Feedback**: Satisfied/Not Satisfied rating system
- **Community Reviews**: Build reputation within the community

#### Meet-up Points

- **Designated Locations**: Admin can set up meet-up points
- **Safe Transactions**: Public places for product exchanges

## 🛠️ Technology Stack

### Frontend

- **HTML5**: Semantic markup structure
- **CSS3**: Custom styling with Bootstrap 5
- **JavaScript**: Vanilla JS for interactivity
- **Bootstrap 5**: Responsive mobile-first design
- **Font Awesome**: Icons and visual elements

### Backend

- **PHP**: Server-side scripting
- **MySQL**: Database management
- **PDO**: Secure database interactions
- **RESTful API**: Clean API endpoints

### Database

- **MySQL**: Primary database
- **Normalized Schema**: Efficient data structure
- **Indexes**: Optimized for performance

## 📁 Project Structure

```
barangay-agri-market/
├── index.html                 # Homepage
├── login.html                 # User login
├── register.html              # User registration
├── products.html              # Product listings
├── requests.html              # Buyer requests
├── announcements.html         # Barangay announcements
├── dashboard.html             # User dashboard
├── assets/
│   ├── css/
│   │   └── style.css          # Custom styles
│   └── js/
│       └── main.js            # Main JavaScript
├── api/
│   ├── auth.php               # Authentication API
│   ├── products.php           # Products API
│   ├── requests.php           # Requests API
│   └── announcements.php      # Announcements API
├── config/
│   └── database.php           # Database configuration
├── database/
│   └── schema.sql             # Database schema
└── README.md                  # This file
```

## 🚀 Installation & Setup

### Prerequisites

- Web server (Apache/Nginx)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Modern web browser

### Installation Steps

1. **Clone/Download the project**

   ```bash
   git clone [repository-url]
   cd barangay-agri-market
   ```

2. **Set up the database**

   ```bash
   # Import the database schema
   mysql -u root -p < database/schema.sql
   ```

3. **Configure database connection**

   - Edit `config/database.php`
   - Update database credentials:
     ```php
     private $host = 'localhost';
     private $db_name = 'barangay_agri_market';
     private $username = 'your_username';
     private $password = 'your_password';
     ```

4. **Set up web server**

   - Point document root to project directory
   - Ensure PHP is enabled
   - Configure URL rewriting if needed

5. **Access the application**
   - Open browser and navigate to your domain
   - Default admin credentials:
     - Email: admin@barangay.com
     - Password: admin123

## 📱 Mobile Responsiveness

The application is designed with mobile-first approach:

- **Bootstrap 5**: Responsive grid system
- **Touch-friendly**: Large buttons and touch targets
- **Optimized Images**: Compressed and responsive images
- **Fast Loading**: Minimal JavaScript and CSS
- **Offline Capable**: Basic functionality works offline

## 🔒 Security Features

- **Password Hashing**: bcrypt encryption
- **Input Sanitization**: XSS prevention
- **SQL Injection Protection**: PDO prepared statements
- **Authentication Tokens**: Secure session management
- **Privacy Protection**: Contact information protection

## 🎯 User Roles & Permissions

### Farmers/Fisherfolk

- Create and manage product listings
- View buyer requests
- Update profile information
- Contact buyers through barangay

### Buyers

- Browse products
- Post buying requests
- Contact sellers through barangay
- Rate transactions

### Barangay Admin

- Manage all users
- Post announcements
- Verify sellers
- Moderate content
- Access system reports

## 📊 Database Schema

### Core Tables

- **users**: User accounts and profiles
- **products**: Product listings
- **buyer_requests**: Buyer requests
- **announcements**: Barangay announcements
- **messages**: Communication system
- **ratings**: User feedback
- **categories**: Product categories
- **meetup_points**: Designated meet-up locations

## 🔧 API Endpoints

### Authentication

- `POST /api/auth.php` - Login/Register/Validate

### Products

- `GET /api/products.php` - List products
- `POST /api/products.php` - Create product
- `PUT /api/products.php` - Update product
- `DELETE /api/products.php` - Delete product

### Requests

- `GET /api/requests.php` - List requests
- `POST /api/requests.php` - Create request
- `PUT /api/requests.php` - Update request
- `DELETE /api/requests.php` - Delete request

### Announcements

- `GET /api/announcements.php` - List announcements
- `POST /api/announcements.php` - Create announcement
- `PUT /api/announcements.php` - Update announcement
- `DELETE /api/announcements.php` - Delete announcement

## 🌱 Sustainability Plan

### Community Ownership

- **Barangay Management**: Local government oversight
- **Community Training**: Staff training for maintenance
- **Local Support**: Community-driven support system

### Scalability

- **Single Barangay**: Start with one community
- **Municipality Expansion**: Scale to multiple barangays
- **City-wide Platform**: Expand to entire city

### Maintenance

- **Regular Updates**: Security and feature updates
- **Community Feedback**: User-driven improvements
- **Technical Support**: Local technical assistance

## 📈 Future Enhancements

### Phase 3 - Advanced Features

- **SMS Integration**: Twilio for offline participation
- **Delivery System**: Tricycle driver integration
- **Payment Integration**: Digital payment options
- **Mobile App**: Native mobile application
- **Analytics Dashboard**: Detailed reporting system

### Phase 4 - Ecosystem Features

- **Cooperative Integration**: Farmer cooperative support
- **Government Integration**: Subsidy and program integration
- **Market Analysis**: Price trends and market data
- **Supply Chain**: Full supply chain management

## 🤝 Contributing

This project is designed for community use and can be customized for specific barangay needs. Contributions are welcome for:

- Bug fixes
- Feature enhancements
- Documentation improvements
- Localization support

## 📄 License

This project is open source and available under the MIT License. Feel free to use, modify, and distribute for community benefit.

## 📞 Support

For technical support or questions:

- Contact: Barangay Office
- Phone: +63 XXX XXX XXXX
- Email: admin@barangay.com

---

**Built with ❤️ for the Filipino farming community**
# STS---Agri-System
