# Backend - CryptoSecure API

Laravel-based REST API for the Stream Cipher RC4/A5 Cryptography Project.

## 🚀 Quick Start

### Prerequisites
- PHP 8.2 or higher
- Composer 2.0 or higher
- MySQL 8.0 or higher

### Installation

```bash
# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env

# Run migrations
php artisan migrate

# Start development server
php artisan serve
```

The API will be available at `http://localhost:8000`

## 📚 API Documentation

### Base URL
```
http://localhost:8000/api
```

### Authentication
Uses Laravel Sanctum for API token authentication.

### Response Format
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {},
  "errors": {}
}
```

### Endpoints

#### Authentication
- **POST** `/api/auth/register` - Register new user
- **POST** `/api/auth/login` - Login user
- **POST** `/api/auth/logout` - Logout user (requires auth)
- **POST** `/api/auth/refresh` - Refresh token (requires auth)
- **GET** `/api/auth/me` - Get current user (requires auth)
- **POST** `/api/auth/forgot-password` - Send password reset email
- **POST** `/api/auth/reset-password` - Reset password with token

#### User
- **GET** `/api/user/profile` - Get profile (requires auth)
- **PUT** `/api/user/profile` - Update name/avatar (requires auth)
- **PUT** `/api/user/password` - Change password (requires auth)
- **DELETE** `/api/user/account` - Delete account (requires auth)

#### Chat
- **GET** `/api/chat/conversations` - List conversations
- **POST** `/api/chat/conversations` - Start a conversation
- **GET** `/api/chat/conversations/{id}/messages` - Get messages
- **POST** `/api/chat/conversations/{id}/messages` - Send encrypted message
- **POST** `/api/chat/conversations/{id}/mark-read` - Mark messages as read

#### Files
- **GET** `/api/files` - List user files
- **POST** `/api/files/upload` - Upload encrypted file
- **GET** `/api/files/{id}/download` - Download encrypted file
- **DELETE** `/api/files/{id}` - Delete file

#### Admin (requires admin role)
- **GET** `/api/admin/stats` - Platform statistics
- **GET** `/api/admin/users` - List all users
- **POST** `/api/admin/users` - Create a user
- **POST** `/api/admin/users/{id}/ban` - Ban a user
- **POST** `/api/admin/users/{id}/unban` - Unban a user

## 🏗️ Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── UserController.php
│   │   ├── EncryptionController.php
│   │   ├── ChatController.php
│   │   ├── FileController.php
│   │   └── AdminController.php
│   ├── Middleware/
│   │   └── AdminMiddleware.php
│   └── Requests/
│       ├── LoginRequest.php
│       └── RegisterRequest.php
├── Models/
│   ├── User.php
│   ├── UserType.php
│   ├── BannedEmail.php
│   ├── Message.php
│   ├── Conversation.php
│   └── EncryptedFile.php
```

## 🔒 Security Features

- **Authentication**: Laravel Sanctum API tokens
- **Password Hashing**: Bcrypt
- **Password Policy**: Min 8 chars, uppercase, number, special character
- **Input Validation**: Form Request classes
- **SQL Injection Protection**: Eloquent ORM
- **CORS**: Configured for frontend origin
- **Rate Limiting**: Applied on API routes
- **Ban System**: Banned emails cannot register or login
- **Role-Based Access**: Admin middleware on admin routes
- **Client-Side Encryption**: Encryption keys never stored on server
- **Token Rotation**: `/auth/refresh` revokes old token and issues a new one

## ⚙️ Configuration

### Database
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cryptography_app
DB_USERNAME=root
DB_PASSWORD=your_password
```

### Mail
```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
```

### Frontend URL (for password reset links)
```env
APP_FRONTEND_URL=http://localhost:4200
```

## 📦 Key Dependencies

- Laravel 11
- PHP 8.2+
- Laravel Sanctum
- Guzzle HTTP

## 🗄️ Database Schema

### users
- id, name, email, password, avatar, user_type_id, is_banned, encryption_count, timestamps

### user_types
- id (1=admin, 2=user), name

### banned_emails
- id, email, banned_by, reason, timestamps

### conversations
- id, user1_id, user2_id, encryption_key (client-side), algorithm, timestamps

### messages
- id, conversation_id, sender_id, encrypted_content, nonce (per-message, used to derive unique keystream), algorithm, timestamps

### encrypted_files
- id, user_id, original_filename, encrypted_filename, original_size, algorithm, timestamps

## 🚢 Deployment

Deployed on **Render** (backend) with **Clever Cloud** (MySQL) and **Vercel** (frontend).

Production checklist:
- [x] `APP_DEBUG=false`
- [x] Strong `APP_KEY` set
- [x] Production database configured
- [x] CORS set to Vercel frontend URL
- [x] Mail configured via Mailtrap

## 👥 Team

- Zahraa Salloum
- Mariam Abou Merhi
- Mohammad Nassar
- Tara Elkhoury

## ⚠️ Security Notice

This is an educational project implementing deprecated cryptographic algorithms (RC4 and A5/1). These algorithms have known vulnerabilities and should **NOT** be used in production systems.

## 📄 License

MIT License - Educational use only