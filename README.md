# Bright Minds Learning Platform 🌟

**Bright Minds** is an interactive educational learning platform designed for children aged 5-12. The platform combines fun games, engaging quizzes, and educational stories with a gamification system that rewards learning through XP, levels, coins, and achievement streaks.

## 🎯 Features

### For Children
- **Interactive Games**: 5 engaging educational games
- **Dynamic Math Quizzes**: Adaptive difficulty levels (Easy, Medium, Hard) with various question types
- **Educational Stories**: Reading materials to enhance learning
- **Gamification System**:
  - Earn XP and level up
  - Collect coins
  - Maintain daily login streaks
  - Unlock achievements
  - Customizable avatars (Owl, Fox, Rabbit, Bear, Cat, Dog)
- **Personal Dashboard**: Track progress, stats, and achievements
- **Celebrations**: Animated rewards for accomplishments

### For Parents
- **Parent Dashboard**: Monitor your child's learning progress
- **Progress Tracking**: View XP, levels, coins, and activity
- **Child Management**: Link and manage multiple children's accounts
- **Activity Insights**: Track learning patterns and achievements

## 🛠️ Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+ / MariaDB
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Server**: Apache/Nginx with PHP support
- **Session Management**: PHP Sessions

## 📋 Prerequisites

Before installing, ensure you have:
- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx web server with PHP support
- PHP extensions: `mysqli`, `json`, `session`, `mbstring`

## 🚀 Installation

### 1. Clone or Download the Project

```bash
# If using git
git clone <repository-url>
cd BrightMinds-main

# Or extract the downloaded ZIP file
```

### 2. Database Setup

1. Create a MySQL database:
```sql
CREATE DATABASE bright_minds_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import the database schema:
```bash
mysql -u your_username -p bright_minds_db < database/bright_minds_enhanced.sql
```

Or use phpMyAdmin to import `database/bright_minds_enhanced.sql`

### 3. Configuration

Edit `includes/config.php` and update the database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_USER', 'your_database_username');
define('DB_PASS', 'your_database_password');
define('DB_NAME', 'bright_minds_db');
```

Also update the base URL if needed:
```php
define('BASE_URL', 'http://localhost/your-path-to-bright-minds/');
```

### 4. File Permissions

Ensure the `logs` directory is writable:
```bash
chmod 755 logs
# Or on Windows, ensure the folder has write permissions
```

### 5. Web Server Configuration

#### Apache
- Place the project in your web root (e.g., `htdocs` or `www`)
- Ensure `.htaccess` is enabled (if using)
- Access via: `http://localhost/BrightMinds-main/`

#### Nginx
- Configure your server block to point to the project directory
- Ensure PHP-FPM is configured correctly

## 📁 Project Structure

```
BrightMinds-main/
├── api/                    # PHP API endpoints
│   ├── auth.php           # Authentication endpoints
│   ├── dashboard.php      # Dashboard data endpoints
│   ├── games.php          # Games API
│   ├── parent.php         # Parent dashboard API
│   ├── questions.php      # Question generation API
│   ├── quiz.php           # Quiz management API
│   └── stories.php        # Stories API
├── css/                   # Stylesheets
│   ├── auth.css          # Authentication styles
│   ├── celebrations.css   # Celebration animations
│   ├── dashboard.css      # Dashboard styles
│   ├── main.css          # Main stylesheet
│   └── parent-dashboard.css
├── database/              # Database files
│   └── bright_minds_enhanced.sql
├── games/                 # Game HTML files
│   ├── game1.html
│   ├── game2.html
│   ├── game3.html
│   ├── game4.html
│   └── game5.html
├── includes/              # PHP includes
│   ├── auth_check.php    # Authentication verification
│   ├── config.php        # Configuration file
│   └── QuestionGenerator.php
├── js/                    # JavaScript files
│   ├── auth.js           # Authentication logic
│   ├── celebrations.js   # Celebration animations
│   ├── dashboard.js      # Dashboard functionality
│   ├── parent-dashboard.js
│   └── protect.js        # Route protection
├── logs/                  # Application logs
├── index.html             # Landing page
├── dashboard.html          # Child dashboard
├── parent-auth.html       # Parent authentication
├── parent-dashboard.html  # Parent dashboard
├── quiz.html              # Quiz selection
├── quiz-take.html         # Quiz interface
├── stories.html           # Stories library
└── story-read.html        # Story reader
```

## 🎮 Usage

### For Children

1. **Sign Up**: Visit the landing page and create an account
   - Choose a username, email, and password
   - Pick an avatar
   - Set your display name

2. **Login**: Use your credentials to access your dashboard

3. **Explore**:
   - Play games from the dashboard
   - Take quizzes to earn XP and coins
   - Read stories to enhance learning
   - Track your progress and achievements

### For Parents

1. **Access Parent Portal**: Click "Parent Access" on the landing page
2. **Login/Register**: Use parent credentials
3. **Link Children**: Connect your children's accounts
4. **Monitor Progress**: View detailed analytics and achievements

## 🔧 Configuration Options

In `includes/config.php`, you can customize:

- **Gamification Settings**:
  - `XP_PER_LEVEL`: XP required per level (default: 100)
  - `LEVEL_MULTIPLIER`: Level progression multiplier (default: 1.2)
  - `MAX_STREAK_BONUS`: Maximum streak bonus (default: 50)

- **Security Settings**:
  - `PASSWORD_MIN_LENGTH`: Minimum password length (default: 6)
  - `MAX_LOGIN_ATTEMPTS`: Maximum login attempts (default: 5)
  - `LOGIN_TIMEOUT`: Lockout duration in seconds (default: 300)

- **Session Settings**:
  - `SESSION_LIFETIME`: Session duration in seconds (default: 86400)

## 📊 Database Schema

The database includes the following main tables:
- `users`: User accounts (parents and children)
- `children`: Child profiles with gamification data
- `sessions`: Authentication sessions
- `games`: Game records
- `quizzes`: Quiz attempts and results
- `questions`: Question bank
- `stories`: Story content
- `achievements`: Achievement definitions
- `user_achievements`: User achievement records

## 🔐 Security Features

- Password hashing using bcrypt
- Session-based authentication
- SQL injection prevention (prepared statements)
- XSS protection (input sanitization)
- CSRF protection
- Login attempt limiting
- Secure password requirements

## 🐛 Troubleshooting

### Database Connection Issues
- Verify database credentials in `includes/config.php`
- Ensure MySQL service is running
- Check database exists and user has proper permissions

### Session Issues
- Ensure `logs` directory is writable
- Check PHP session configuration
- Verify session storage path permissions

### API Errors
- Check PHP error logs in `logs/error_YYYY-MM-DD.log`
- Verify all required PHP extensions are installed
- Check web server error logs

## 📝 Logging

The application logs activities and errors to the `logs/` directory:
- `auth_YYYY-MM-DD.log`: Authentication events
- `error_YYYY-MM-DD.log`: Error messages

## 🎨 Customization

### Adding New Games
1. Create HTML file in `games/` directory
2. Add game entry to database
3. Update dashboard to include new game

### Adding New Question Types
Edit `includes/QuestionGenerator.php` to add new question types or difficulty levels.

### Styling
Modify CSS files in `css/` directory to customize the appearance.

## 📄 License

This project is provided as-is for educational purposes.

## 👥 Credits

**Bright Minds Learning Platform** - Version 2.0

## 🔄 Version History

- **v2.0**: Enhanced gamification, parent dashboard, dynamic quiz generation
- **v1.0**: Initial release with basic features

## 📞 Support

For issues or questions, please check the logs directory for error messages or contact the development team.

---

**Happy Learning! 🌟📚🎮**

