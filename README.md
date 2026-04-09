# Backend - CryptoSecure API

Laravel-based REST API for the Stream Cipher RC4/A5 Cryptography Project.

## 🚀 Quick Start

### Prerequisites
- PHP 8.2 or higher
- Composer 2.0 or higher
- MySQL 8.0 or higher
- Redis 7.0 or higher

### Installation

```bash
# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

## Configure database in .env

# Run migrations
php artisan migrate

# Seed database (optional)
php artisan db:seed

# Start development server
php artisan serve
```

The API will be available at `http://localhost:8000`

## 📚 API Documentation

### Base URL
```
http://localhost:8000/api/v1
```

### Authentication
Uses Laravel Sanctum for API token authentication.

### Response Format
All API responses follow this structure:
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
- **POST** `/auth/register` - Register new user
- **POST** `/auth/login` - Login user
- **POST** `/auth/logout` - Logout user
- **POST** `/auth/refresh` - Refresh token
- **POST** `/auth/forgot-password` - Request password reset
- **POST** `/auth/reset-password` - Reset password
- **GET** `/auth/google` - Google OAuth redirect
- **GET** `/auth/google/callback` - Google OAuth callback

#### User
- **GET** `/user/profile` - Get user profile
- **PUT** `/user/profile` - Update profile
- **PUT** `/user/password` - Change password
- **DELETE** `/user/account` - Delete account

#### Encryption
- **POST** `/encryption/text/encrypt` - Encrypt text
- **POST** `/encryption/text/decrypt` - Decrypt text
- **POST** `/encryption/file/encrypt` - Encrypt file
- **POST** `/encryption/file/decrypt` - Decrypt file

#### Chat
- **GET** `/chat/conversations` - List conversations
- **GET** `/chat/conversations/{id}/messages` - Get messages
- **POST** `/chat/messages` - Send message
- **DELETE** `/chat/messages/{id}` - Delete message
- **PUT** `/chat/messages/{id}/read` - Mark as read

#### Files
- **GET** `/files` - List user files
- **POST** `/files/upload` - Upload file
- **GET** `/files/{id}/download` - Download file
- **POST** `/files/{id}/download-decrypted` - Download decrypted
- **DELETE** `/files/{id}` - Delete file
- **POST** `/files/share` - Share file

## 🏗️ Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── UserController.php
│   │   ├── EncryptionController.php
│   │   ├── ChatController.php
│   │   └── FileController.php
│   ├── Middleware/
│   │   └── RateLimitMiddleware.php
│   └── Requests/
│       ├── LoginRequest.php
│       ├── RegisterRequest.php
│       └── EncryptionRequest.php
├── Models/
│   ├── User.php
│   ├── Message.php
│   ├── File.php
│   └── Conversation.php
├── Services/
│   ├── EncryptionService.php
│   ├── ChatService.php
│   └── FileService.php
└── Repositories/
    ├── UserRepository.php
    ├── MessageRepository.php
    └── FileRepository.php
```

## 🔒 Security Features

- **Authentication**: Laravel Sanctum API tokens
- **Password Hashing**: Bcrypt with configurable rounds
- **Input Validation**: Form Request validation
- **SQL Injection Protection**: Eloquent ORM
- **XSS Protection**: Laravel's built-in escaping
- **CSRF Protection**: Enabled for web routes
- **Rate Limiting**: Configurable per endpoint
- **CORS**: Configured for frontend origin
- **Security Headers**: X-Frame-Options, X-Content-Type-Options, etc.
- **File Upload Security**: Type validation, size limits
- **Logging**: Security events logged without sensitive data

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=AuthenticationTest

# Run with coverage
php artisan test --coverage
```

## 🔧 Configuration

### Database
Configure in `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cryptography_app
DB_USERNAME=root
DB_PASSWORD=your_password
```

### Redis
```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### CORS
```env
CORS_ALLOWED_ORIGINS=http://localhost:4200
```

## 📦 Dependencies

### Core
- Laravel 11
- PHP 8.2+

### Authentication
- Laravel Sanctum

### Cache & Queue
- Redis (via Predis)

### HTTP Client
- Guzzle

## 🔐 Cryptographic Implementations

### RC4 Stream Cipher
Implementation of RC4 algorithm for educational purposes.

**Warning**: RC4 is deprecated and has known vulnerabilities.

### A5/1 Stream Cipher
Implementation of A5/1 algorithm used in GSM encryption.

**Warning**: A5/1 has known weaknesses and should not be used in production.

## 🚢 Deployment

### Production Checklist
- [ ] Set `APP_DEBUG=false`
- [ ] Set strong `APP_KEY`
- [ ] Configure production database
- [ ] Enable HTTPS
- [ ] Set secure session configuration
- [ ] Configure email service
- [ ] Set up queue workers
- [ ] Configure scheduled tasks
- [ ] Enable Redis password authentication
- [ ] Set up automated backups

## 📝 Database Schema

### Users
- id, name, email, password, email_verified_at, timestamps

### Messages
- id, conversation_id, sender_id, content (encrypted), algorithm, is_encrypted, timestamps

### Files
- id, user_id, filename, original_filename, size, mime_type, algorithm, is_encrypted, timestamps

### Conversations
- id, user1_id, user2_id, timestamps

## 🐛 Known Issues

- Google OAuth needs client credentials configuration
- WebSocket server needs separate process for chat
- File virus scanning not implemented

## 📖 Documentation

- [Laravel Documentation](https://laravel.com/docs)
- [Laravel Sanctum](https://laravel.com/docs/sanctum)
- [Redis Documentation](https://redis.io/documentation)

## 👥 Team

- Zahraa Salloum
- Mariam Abou Merhi
- Mohammad Nassar
- Tara Elkhoury

## ⚠️ Security Notice

This is an educational project implementing deprecated cryptographic algorithms (RC4 and A5/1). These algorithms have known vulnerabilities and should NOT be used in production systems.

## 📄 License

MIT License - Educational use only
