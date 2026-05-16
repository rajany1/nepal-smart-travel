# Technology Stack & Architecture - Nepal Smart Travel & Local Intelligence Platform

## Overview

This document defines the technology stack and architectural decisions for the Nepal Smart Travel & Local Intelligence Platform.

---

## Technology Stack Summary

| Layer | Technology | Justification |
|-------|-----------|---------------|
| **Mobile Frontend** | Flutter | Cross-platform (iOS/Android), fast development, excellent performance |
| **Backend API** | Laravel 10+ | Robust, scalable, excellent ecosystem, rich features |
| **Database** | MySQL 8.0+ (Primary) | Reliable, scalable, JSON support, geospatial functions, industry-standard |  
| **Admin Panel** | phpMyAdmin | Database management, visual interface, query builder |  
| **Database** | Redis | Caching, real-time features, session management |
| **Maps & Location** | Google Maps API | Comprehensive, reliable, good documentation |
| **Authentication** | Laravel Passport + JWT | Secure, industry-standard, good integration |
| **Real-Time** | Firebase Cloud Messaging | Push notifications, reliable delivery |
| **Real-Time** | WebSockets | Live updates, real-time data sync |
| **Storage** | AWS S3 / Cloudinary | Image/video storage, CDN distribution |
| **Admin Dashboard** | Laravel Blade + Tailwind | Server-side rendering, easy maintenance |
| **AI Integration** | OpenAI API | State-of-art NLP, easy integration |
| **Hosting** | AWS / DigitalOcean | Scalable, reliable, good documentation |
| **CI/CD** | GitHub Actions | Simple, integrated, free for public repos |
| **Monitoring** | DataDog / New Relic | Performance monitoring, error tracking |
| **Analytics** | Mixpanel / Amplitude | Event tracking, user analytics |

---

## Detailed Technology Breakdown

### 1. Mobile Application - Flutter

**Why Flutter?**
- ✅ Cross-platform (iOS & Android from single codebase)
- ✅ Excellent performance (Dart compilation to native)
- ✅ Rich UI components with Material Design
- ✅ Hot reload for faster development
- ✅ Growing ecosystem and community
- ✅ Google-backed and well-maintained
- ✅ Excellent for map-based applications

**Key Packages:**
```yaml
dependencies:
  flutter: sdk: flutter
  
  # Navigation & Routing
  go_router: ^latest
  
  # State Management
  provider: ^latest
  riverpod: ^latest
  
  # API & Networking
  dio: ^latest
  http: ^latest
  
  # Maps & Location
  google_maps_flutter: ^latest
  geolocator: ^latest
  
  # Local Storage
  shared_preferences: ^latest
  hive: ^latest
  
  # Media
  image_picker: ^latest
  video_player: ^latest
  cached_network_image: ^latest
  
  # Authentication
  firebase_auth: ^latest
  flutter_secure_storage: ^latest
  
  # Database
  sqflite: ^latest
  
  # Push Notifications
  firebase_messaging: ^latest
  
  # AI & NLP
  flutter_tflite: ^latest
  
  # UI & Design
  get: ^latest
  flutter_screenutil: ^latest
  
  # Utils
  intl: ^latest
  uuid: ^latest
```

**Project Structure:**
```
lib/
├── main.dart
├── config/
│   ├── app_config.dart
│   ├── routes/
│   ├── themes/
│   └── constants/
├── core/
│   ├── api/
│   ├── models/
│   ├── services/
│   └── utils/
├── features/
│   ├── auth/
│   ├── map/
│   ├── reporting/
│   ├── profile/
│   ├── emergency/
│   └── assistant/
├── providers/
│   ├── auth_provider.dart
│   ├── location_provider.dart
│   └── report_provider.dart
└── widgets/
    ├── common/
    ├── cards/
    └── dialogs/
```

**Development & Release:**
- Development: `flutter run -d chrome` (debugging)
- Release: 
  ```bash
  flutter build apk --release
  flutter build ios --release
  ```

---

### 2. Backend API - Laravel

**Why Laravel?**
- ✅ Full-featured framework with excellent documentation
- ✅ Built-in migration and seeding system
- ✅ Eloquent ORM for database operations
- ✅ Robust routing and middleware system
- ✅ Queue system for background jobs
- ✅ Excellent testing framework (PHPUnit)
- ✅ Laravel Passport for API authentication
- ✅ Strong ecosystem and packages (Composer)

**Laravel Version:** 10+

**Key Packages:**
```json
{
  "require": {
    "php": "^8.2",
    "laravel/framework": "^10.0",
    "laravel/passport": "^12.0",
    "laravel/tinker": "^2.0",
    "guzzlehttp/guzzle": "^7.0",
    
    "doctrine/dbal": "^3.0",
    "predis/predis": "^2.0",
    "aws/aws-sdk-php": "^3.0",
    "intervention/image": "^2.0",
    "firebase/php-jwt": "^6.0",
    "openai-php/client": "^0.7",
    
    "spatie/laravel-query-builder": "^5.0",
    "spatie/laravel-media-library": "^10.0",
    "spatie/laravel-activitylog": "^4.0",
    "maatwebsite/excel": "^3.0",
    
    "league/flysystem-aws-s3-v3": "^3.0",
    "laravel/sanctum": "^3.0"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.0",
    "pestphp/pest": "^2.0",
    "laravel/pint": "^1.0",
    "barryvdh/laravel-ide-helper": "^2.0"
  }
}
```

**Project Structure:**
```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   ├── Places/
│   │   ├── Reports/
│   │   ├── Emergency/
│   │   └── Admin/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
├── Models/
│   ├── User.php
│   ├── Report.php
│   ├── Place.php
│   ├── UserReputation.php
│   └── Alert.php
├── Services/
│   ├── LocationService.php
│   ├── ReportService.php
│   ├── ModerationService.php
│   └── AIService.php
├── Jobs/
│   ├── ProcessReportImage.php
│   ├── VerifyReport.php
│   └── SendNotification.php
├── Events/
├── Listeners/
├── Exceptions/
└── Traits/

database/
├── migrations/
│   ├── create_users_table.php
│   ├── create_reports_table.php
│   ├── create_places_table.php
│   └── ...
├── seeders/
│   ├── DatabaseSeeder.php
│   ├── PlaceSeeder.php
│   └── UserSeeder.php
└── factories/

routes/
├── api.php
└── web.php

tests/
├── Feature/
│   ├── Auth/
│   ├── Reports/
│   └── Places/
└── Unit/
```

**API Structure:**
```
/api/v1/
├── /auth
│   ├── POST /register
│   ├── POST /login
│   └── POST /logout
├── /users
│   ├── GET /me
│   ├── PUT /me
│   └── GET /{id}/reputation
├── /places
│   ├── GET /nearby
│   ├── GET /{id}
│   └── POST /{id}/review
├── /reports
│   ├── POST / (create)
│   ├── GET /{id}
│   ├── GET / (list with filters)
│   └── PUT /{id} (update)
└── /admin
    ├── /reports
    │   ├── GET /pending
    │   ├── POST /{id}/approve
    │   └── POST /{id}/reject
    └── /analytics
        └── GET /dashboard
```

**Configuration Files:**
- `config/app.php` - Application configuration
- `config/database.php` - Database connections
- `config/cache.php` - Caching configuration
- `.env` - Environment variables

---

### 3. Database - MySQL

**Why MySQL?**
- ✅ Industry-standard, reliable, widely-used database
- ✅ JSON support for flexible data structures
- ✅ Geospatial functions (ST_Distance, ST_GeomFromText, etc.)
- ✅ ACID compliance and transaction support
- ✅ Excellent scalability and performance
- ✅ Strong security features and authentication
- ✅ Easy to manage with phpMyAdmin GUI
- ✅ Large community and extensive documentation

**Version:** MySQL 8.0+

**Admin Tool:** phpMyAdmin
- Visual database management
- Query builder and execution
- Data import/export capabilities
- User privilege management
- Backup and restore functionality

**Key Features:**
- `Spatial Indexes` - Efficient geospatial queries
- `JSON Functions` - Document-style data storage
- `Full-Text Search` - Text search capabilities
- `Partitioning` - Large table optimization

**Database Design:**
```sql
-- Tables with geospatial data
CREATE TABLE places (
  id CHAR(36) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  location POINT NOT NULL,  -- MySQL Geospatial
  category_id INT,
  rating DECIMAL(3,2),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  SPATIAL INDEX idx_location (location)
);

CREATE TABLE reports (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  location POINT,  -- MySQL Geospatial
  category_id INT,
  status ENUM('pending', 'approved', 'rejected'),
  admin_notes TEXT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  INDEX idx_status (status),
  SPATIAL INDEX idx_location (location),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Backup Strategy:**
- Automated daily backups via phpMyAdmin or AWS RDS automated backups
- Point-in-time recovery capability
- Replicated backups to secondary location
- Monthly full backups archived
- Binary log files for transaction replay

---

### 4. Caching - Redis

**Why Redis?**
- ✅ High-performance in-memory caching
- ✅ Support for various data structures
- ✅ Pub/Sub messaging
- ✅ Session management
- ✅ Real-time features
- ✅ Excellent for geospatial queries caching

**Configuration:**
```php
// config/cache.php
'redis' => [
    'driver' => 'redis',
    'connection' => 'cache',
],

// Cache keys structure
'cache_keys' => [
    'place:nearby:{lat}:{lng}' => 3600,  // 1 hour
    'weather:{lat}:{lng}' => 1800,  // 30 minutes
    'alerts:{region}' => 300,  // 5 minutes
    'user:{id}:reputation' => 86400,  // 1 day
]
```

---

### 5. Maps & Location - Google Maps API

**Why Google Maps API?**
- ✅ Comprehensive and accurate
- ✅ Excellent documentation
- ✅ Multiple services (Maps, Directions, Places, Geocoding)
- ✅ Good performance
- ✅ Reliable 99.9% uptime

**Services Used:**
- **Maps SDK** - Map display and interaction
- **Places API** - Place search and details
- **Directions API** - Route calculation
- **Geocoding API** - Address to coordinates
- **Distance Matrix** - Travel time calculation
- **Static Maps** - Map images

**API Keys:**
- Android API Key
- iOS API Key
- Web API Key
- Server API Key

**Cost Optimization:**
- Caching results
- Request aggregation
- Limits on requests
- Alternative provider (Mapbox) as fallback

---

### 6. Authentication - Laravel Passport + JWT

**Why?**
- ✅ OAuth 2.0 implementation
- ✅ JWT tokens
- ✅ Scopes for permission management
- ✅ Built-in token refresh
- ✅ Secure and industry-standard

**Authentication Flow:**
```
User Login
    ↓
Laravel Passport OAuth2
    ↓
Generate JWT Token
    ↓
Mobile App Stores Token (Secure Storage)
    ↓
Subsequent Requests Include Token in Header
    ↓
Server Validates Token
    ↓
Request Processed or Rejected
```

**Token Configuration:**
```php
'passport' => [
    'token_expiry' => 60 * 60 * 24 * 365,  // 1 year
    'refresh_token_expiry' => 60 * 60 * 24 * 365 * 5,  // 5 years
    'hash_personal_access_clients' => true,
],
```

---

### 7. Real-Time Services - Firebase + WebSockets

**Firebase Cloud Messaging (FCM):**
- Push notifications
- Multi-platform support (iOS, Android, Web)
- Reliable delivery
- Scheduling capabilities

**WebSockets (for real-time features):**
- Live alert feeds
- Real-time user presence
- Live chat support (future)
- Instant notifications

**Implementation:**
```php
// Laravel Broadcasting
'broadcasting' => [
    'default' => 'pusher',  // or use Laravel's built-in broadcaster
    'connections' => [
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
        ],
    ],
],
```

---

### 8. Media Storage - AWS S3 + Cloudinary

**Why S3?**
- ✅ Scalable storage
- ✅ CDN integration
- ✅ Access control
- ✅ Versioning

**Why Cloudinary?**
- ✅ Image optimization
- ✅ Automatic resizing
- ✅ Format conversion
- ✅ Fast delivery

**Storage Structure:**
```
s3://bucket-name/
├── users/
│   └── {user_id}/avatar.jpg
├── reports/
│   └── {report_id}/
│       ├── image_1.jpg
│       ├── image_2.jpg
│       └── video.mp4
└── places/
    └── {place_id}/
        └── image.jpg
```

---

### 9. Admin Dashboard - Laravel Blade + Tailwind CSS

**Why?**
- ✅ Server-side rendering (simpler maintenance)
- ✅ Tight Laravel integration
- ✅ Rapid development
- ✅ Excellent for admin tools

**Tech Stack:**
- **Blade Templates** - View rendering
- **Tailwind CSS** - Styling
- **Alpine.js** - Light JavaScript interactions
- **Chart.js** - Data visualization
- **DataTables** - Data management

---

### 10. AI Integration - OpenAI API

**Services Used:**
- **GPT-4** - Main conversational AI
- **Embeddings** - For semantic search
- **Moderation API** - Content filtering

**Implementation:**
```php
use OpenAI\Client;

$client = new Client($apiKey);

// Travel recommendation
$response = $client->chat()->create([
    'model' => 'gpt-4',
    'messages' => [
        ['role' => 'system', 'content' => 'You are a travel expert for Nepal...'],
        ['role' => 'user', 'content' => $userQuery],
    ],
    'temperature' => 0.7,
    'max_tokens' => 500,
]);
```

---

### 11. Hosting & Infrastructure - AWS / DigitalOcean

**Recommended: AWS**

**Services:**
- **EC2** - Application servers
- **RDS** - MySQL 8.0+ database with automated backups
- **ElastiCache** - Redis
- **S3** - Media storage
- **CloudFront** - CDN
- **CloudWatch** - Monitoring
- **RDS Aurora** - Database backup and replication
- **Elastic Load Balancer** - Traffic distribution

**Architecture:**
```
Clients (Mobile App / Web)
    ↓
CloudFront (CDN)
    ↓
Application Load Balancer
    ↓
Auto Scaling Group (EC2 Instances)
    ├── Laravel Application
    ├── Job Queue Workers
    └── WebSocket Server
    ↓
RDS (MySQL 8.0+)
    ↓
ElastiCache (Redis)
    ↓
S3 (Media Storage)
```

**Deployment:**
- **Container:** Docker
- **Orchestration:** Kubernetes (future scaling) or ECS
- **Infrastructure as Code:** Terraform

---

### 12. CI/CD - GitHub Actions

**Pipeline:**
```yaml
name: CI/CD Pipeline

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Run Tests
        run: composer test
  
  deploy:
    needs: test
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to Production
        run: |
          # Deployment script
```

---

### 13. Monitoring & Logging

**Error Tracking:** Sentry
**Performance:** New Relic / DataDog
**Logging:** CloudWatch / Stack Driver
**Uptime Monitoring:** Statuspage.io

---

### 14. Testing Strategy

**Unit Tests:** PHPUnit / PestPHP
**Integration Tests:** Feature tests in Laravel
**Mobile Testing:** Flutter integration tests
**API Testing:** Postman collections / REST client

**Target Coverage:** >80% code coverage

---

## Architecture Decisions

### Microservices vs Monolithic
**Decision:** Start with **Monolithic** (Single Laravel app)
- **Rationale:** Simpler to develop initially, easier to maintain
- **Future:** Can evolve to microservices if needed (Job Queue, Separate Services)

### Database Choice
**Decision:** **MySQL 8.0+** with phpMyAdmin as primary database solution
- **Rationale:** Better geospatial support, advanced features, ACID compliance
- **Fallback:** MySQL/MariaDB supported through Laravel compatibility

### Caching Strategy
**Decision:** **Multi-layer caching**
1. Client-side caching (HTTP cache headers)
2. Redis cache for frequently accessed data
3. Database query optimization
4. CDN for static assets

### API Design
**Decision:** **RESTful API** with proper versioning
- Endpoints: `/api/v1/...`
- Rate limiting: 1000 requests/hour per user
- Pagination: 20-50 items per page

---

## Scalability Considerations

### Horizontal Scaling
- Load balancer distribution
- Stateless application servers
- Shared cache (Redis)
- Shared database (RDS with read replicas)

### Vertical Scaling
- Increase EC2 instance size
- Increase Redis memory
- Add read replicas to database

### Database Optimization
- Connection pooling
- Query optimization
- Indexing strategy
- Partitioning for large tables

### Caching Strategy
- Cache frequently accessed data
- Implement cache invalidation
- Use CDN for static assets
- Compress responses

---

## Security Measures

1. **Authentication:** OAuth 2.0 with JWT
2. **Encryption:** TLS 1.3 for all communications
3. **Database:** Encrypted at-rest and in-transit
4. **API Rate Limiting:** Prevent abuse
5. **Input Validation:** Server-side validation
6. **CORS:** Proper CORS headers
7. **HTTPS:** Enforced everywhere
8. **Secrets Management:** AWS Secrets Manager

---

## Development Environment Setup

**Prerequisites:**
- PHP 8.2+
- Node.js 18+
- MySQL 8.0+
- phpMyAdmin 5.0+ (for database administration)
- Redis 6+
- Docker (recommended)

**Setup Commands:**
```bash
# Clone repository
git clone <repo-url>

# Install dependencies
composer install
npm install

# Copy environment file
cp .env.example .env

# Generate app key
php artisan key:generate

# Create database
php artisan migrate --seed

# Start development server
php artisan serve

# Start job queue
php artisan queue:work
```

---

**Document Version:** 1.0
**Last Updated:** May 16, 2026
