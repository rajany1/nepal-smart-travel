# System Architecture - Nepal Smart Travel & Local Intelligence Platform

## High-Level Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                         CLIENT LAYER                               │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────┐         ┌──────────────┐      ┌──────────────┐   │
│  │   Mobile App │         │  Web Admin   │      │  Dashboard   │   │
│  │   (Flutter)  │         │  Dashboard   │      │  Analytics   │   │
│  └──────────────┘         └──────────────┘      └──────────────┘   │
│        │                         │                      │            │
│        └─────────────────────────┴──────────────────────┘            │
│                                  │                                   │
└──────────────────────────────────┼───────────────────────────────────┘
                                   │
                                   │ HTTPS/REST/WebSocket
                                   │
┌──────────────────────────────────┼───────────────────────────────────┐
│                      API GATEWAY LAYER                               │
├──────────────────────────────────┼───────────────────────────────────┤
│                                  │                                   │
│                    ┌─────────────▼──────────────┐                   │
│                    │   AWS API Gateway / ALB    │                   │
│                    │  • Rate Limiting          │                   │
│                    │  • Request Validation     │                   │
│                    │  • Authentication Proxy   │                   │
│                    └─────────────┬──────────────┘                   │
│                                  │                                   │
└──────────────────────────────────┼───────────────────────────────────┘
                                   │
                ┌──────────────────┼──────────────────┐
                │                  │                  │
┌───────────────▼──────┐  ┌────────▼────────┐  ┌────▼─────────────┐
│  APPLICATION LAYER   │  │   SERVICES      │  │  BACKGROUND JOBS │
├─────────────────────┤  ├─────────────────┤  ├──────────────────┤
│                     │  │                 │  │                  │
│  Laravel API        │  │ • Location Svc  │  │ • Report Process │
│  • Controllers      │  │ • Report Svc    │  │ • Email Queue    │
│  • Middleware       │  │ • AI Svc        │  │ • Notification   │
│  • Routes           │  │ • Auth Svc      │  │ • Image Process  │
│  • Validation       │  │ • Moderation    │  │ • Analytics      │
│                     │  │                 │  │                  │
└─────────────────────┘  └─────────────────┘  └──────────────────┘
        │                       │                      │
        └───────────────────────┼──────────────────────┘
                                │
┌───────────────────────────────┼──────────────────────────────────┐
│              DATA LAYER                                           │
├───────────────────────────────┼──────────────────────────────────┤
│                               │                                  │
│    ┌────────────────────────────────────────┐                   │
│    │      MySQL 8.0+ Database             │                   │
│    │  • User Data                           │                   │
│    │  • Reports & Alerts                    │                   │
│    │  • Places & Tourism                    │                   │
│    │  • Community Data                      │                   │
│    │  • Spatial Indexes                     │                   │
│    └────────────────────────────────────────┘                   │
│                               │                                  │
│    ┌────────────────────────────────────────┐                   │
│    │      Redis Cache Layer                 │                   │
│    │  • Session Data                        │                   │
│    │  • Nearby Places Cache                 │                   │
│    │  • Rate Limiting                       │                   │
│    │  • Real-Time Presence                  │                   │
│    └────────────────────────────────────────┘                   │
│                                                                  │
│    ┌────────────────────────────────────────┐                   │
│    │      AWS S3 Storage                    │                   │
│    │  • User Avatars                        │                   │
│    │  • Report Images/Videos                │                   │
│    │  • Tourism Content                     │                   │
│    └────────────────────────────────────────┘                   │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
                                │
                                │
┌───────────────────────────────┼──────────────────────────────────┐
│         EXTERNAL SERVICES                                        │
├───────────────────────────────┼──────────────────────────────────┤
│          │                    │                    │             │
│   ┌──────▼────────┐  ┌───────▼────────┐  ┌──────▼──────┐       │
│   │Google Maps API│  │OpenAI API      │  │Firebase     │       │
│   │ • Geocoding   │  │ • ChatGPT      │  │ • FCM Msgs  │       │
│   │ • Places      │  │ • Moderation   │  │ • Auth      │       │
│   │ • Directions  │  │ • Embeddings   │  │ • Storage   │       │
│   └───────────────┘  └────────────────┘  └─────────────┘       │
│          │                    │                    │             │
└──────────┼────────────────────┼────────────────────┼─────────────┘
           │                    │                    │
```

---

## Architecture Components

### 1. Frontend Layer

#### Mobile Application (Flutter)

**Architecture Pattern:** MVVM + Provider

```
lib/
├── main.dart                  # App entry point
├── config/
│   ├── app_config.dart       # Global configuration
│   ├── routes.dart           # Navigation routes
│   └── theme.dart            # App theme
├── core/
│   ├── services/             # Core services
│   │   ├── api_service.dart
│   │   ├── location_service.dart
│   │   ├── notification_service.dart
│   │   └── storage_service.dart
│   ├── models/               # Data models
│   ├── exceptions/           # Exception handling
│   └── utils/                # Utility functions
├── features/
│   ├── auth/
│   │   ├── models/
│   │   ├── providers/
│   │   ├── screens/
│   │   └── widgets/
│   ├── home/
│   ├── map/
│   ├── reporting/
│   ├── profile/
│   ├── emergency/
│   └── assistant/
├── providers/                # Global providers
│   ├── auth_provider.dart
│   ├── location_provider.dart
│   └── report_provider.dart
└── widgets/                  # Reusable widgets
```

**Key Libraries:**
- `go_router`: Navigation
- `provider`: State management
- `dio`: HTTP client
- `geolocator`: Location services
- `image_picker`: Media selection
- `firebase_messaging`: Push notifications

---

### 2. API Gateway Layer

**AWS Application Load Balancer (ALB)**

**Responsibilities:**
- Route requests to backend
- SSL/TLS termination
- Rate limiting
- Request validation
- Health checks
- Auto-scaling triggers

**Configuration:**
```yaml
Listeners:
  - Port: 443 (HTTPS)
    Target: EC2 Auto Scaling Group
    Health Check: /api/v1/health
    
  - Port: 80 (HTTP)
    Action: Redirect to HTTPS
```

---

### 3. Application Layer

#### Backend (Laravel)

**Architecture Pattern:** Service-Oriented Architecture (SOA)

```php
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   ├── AuthController.php
│   │   │   └── PasswordController.php
│   │   ├── Places/
│   │   │   ├── PlaceController.php
│   │   │   └── ReviewController.php
│   │   ├── Reports/
│   │   │   ├── ReportController.php
│   │   │   └── ModerationController.php
│   │   ├── Emergency/
│   │   └── Admin/
│   ├── Requests/
│   │   ├── StoreReportRequest.php
│   │   └── UpdatePlaceRequest.php
│   ├── Resources/
│   │   ├── PlaceResource.php
│   │   └── ReportResource.php
│   └── Middleware/
│       ├── Authenticate.php
│       ├── RateLimit.php
│       └── ValidateJSON.php
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
│   ├── AIService.php
│   └── NotificationService.php
├── Jobs/
│   ├── ProcessReportImage.php
│   ├── SendPushNotification.php
│   └── UpdateReputationScore.php
├── Events/
│   ├── ReportApproved.php
│   └── AlertBroadcasted.php
├── Listeners/
│   ├── NotifyReporterOnApproval.php
│   └── UpdateReputationOnApproval.php
└── Traits/
    ├── HasTimestamps.php
    └── Filterable.php
```

**Service Layer:**

```php
// Example: ReportService
namespace App\Services;

class ReportService {
    public function submitReport(array $data): Report {
        // Validate
        // Store report
        // Run spam check
        // Queue moderation
        return $report;
    }
    
    public function approveReport(string $reportId): void {
        // Update status
        // Award XP
        // Update reputation
        // Publish to feed
        // Notify users
    }
    
    public function detectSpam(Report $report): float {
        // Call AI API
        // Calculate score
        // Return probability
    }
}
```

---

### 4. Data Layer

#### MySQL Database

**Configuration:**
- **Version:** MySQL 8.0+
- **Admin Tool:** phpMyAdmin 5.0+
- **Replicas:** 1 primary + 2 read replicas
- **Connection Management:** Built-in MySQL connection pooling (max_connections: 1000)
- **Storage:** AWS RDS Multi-AZ with automated backups

**Key Tables:**
- `users` - User accounts and profiles
- `user_reputations` - XP and reputation tracking
- `reports` - Community reports
- `places` - Tourism places and locations
- `road_conditions` - Road status updates
- `emergency_alerts` - Emergency alerts
- `moderation_queue` - Content moderation workflow

**Optimization:**
- B-tree indexes on frequently queried columns (user_id, status, created_at)
- Spatial indexes for location queries (POINT data)
- Table partitioning by date for large tables (reports, alerts)
- Built-in query caching and optimization
- Materialized views for popular queries

#### Redis Cache

**Key-Value Patterns:**
```
user:{user_id}:session
nearby:places:{lat}:{lng}
report:spam_score:{report_id}
alert:zone:{zone_id}:active
leaderboard:{period}:{region}
```

**TTL Strategy:**
- Session data: 7 days
- Spatial queries: 10 minutes
- Leaderboards: 1 hour
- User cache: 5 minutes

#### AWS S3 Storage

**Bucket Structure:**
```
s3://nepal-smart-travel/
├── avatars/{user_id}/...
├── reports/{report_id}/...
├── places/{place_id}/...
└── tourism/{category}/...
```

**CDN:** CloudFront distribution for faster access

---

### 5. Background Processing

#### Job Queue

**Technology:** Laravel Queue with Redis

**Job Types:**
```php
// Image Processing
Jobs/ProcessReportImage.php

// Notifications
Jobs/SendPushNotification.php
Jobs/SendEmailNotification.php

// Moderation
Jobs/RunSpamDetection.php
Jobs/UpdateModerationQueue.php

// Analytics
Jobs/UpdateReputationScores.php
Jobs/GenerateReports.php
```

**Processing:**
```bash
php artisan queue:work redis --queue=default,alerts,emails
```

---

### 6. Real-Time Services

#### Firebase Cloud Messaging (FCM)

**Implementation:**
```php
// Send push notification
Notification::route('fcm', $deviceToken)
    ->notify(new ReportApprovedNotification($report));
```

**Message Types:**
- Emergency alerts (high priority)
- Report updates (normal priority)
- Community messages (low priority)

#### WebSocket Server

**Purpose:** Real-time feed updates

**Implementation:**
```php
// Laravel Broadcasting
broadcast(new AlertBroadcasted($alert))
    ->toOthers();

// Subscribe to private channel
Echo.private(`user.${userId}`)
    .notification((notification) => {
        console.log(notification);
    });
```

---

### 7. External Integrations

#### Google Maps API

**Services Used:**
- Places API (nearby searches)
- Directions API (route calculation)
- Geocoding API (address conversion)
- Distance Matrix API (travel times)

**Caching Strategy:**
```php
$cacheKey = "map_data:{$lat}:{$lng}:{$radius}";
$data = cache()->remember($cacheKey, 600, function () {
    return GoogleMaps::nearby($lat, $lng);
});
```

#### OpenAI API

**Services:**
- GPT-4 for chat/recommendations
- Moderation API for content filtering
- Embeddings for semantic search

```php
$response = OpenAI::chat()->create([
    'model' => 'gpt-4',
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userQuery],
    ],
]);
```

---

## Deployment Architecture

### Infrastructure Setup

```
┌─────────────────────────────────────────────┐
│            AWS VPC                          │
├─────────────────────────────────────────────┤
│                                             │
│  ┌───────────────────────────────────────┐  │
│  │  Public Subnet (Availability Zone A)  │  │
│  │  • ALB (Load Balancer)               │  │
│  │  • NAT Gateway                        │  │
│  └────────────┬────────────────────────┘  │
│               │                            │
│  ┌────────────┴────────────────────────┐  │
│  │  Private Subnet (Availability Zone A)│  │
│  │  • EC2 Auto Scaling Group           │  │
│  │  • RDS Read Replica                 │  │
│  │  • ElastiCache Cluster              │  │
│  └───────────────────────────────────────┘  │
│                                             │
│  ┌───────────────────────────────────────┐  │
│  │  Private Subnet (Availability Zone B)│  │
│  │  • EC2 Auto Scaling Group           │  │
│  │  • RDS (Primary)                    │  │
│  │  • Route53 Health Checks            │  │
│  └───────────────────────────────────────┘  │
│                                             │
└─────────────────────────────────────────────┘
```

### CI/CD Pipeline

**GitHub Actions Workflow:**

```yaml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: composer test

  deploy:
    needs: test
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to AWS
        run: |
          aws s3 sync . s3://app-bucket/
          aws codedeploy create-deployment ...
```

---

## Scalability Considerations

### Horizontal Scaling

1. **Application Servers**
   - Auto Scaling Group (2-10 instances)
   - Min: 2, Desired: 4, Max: 10
   - Scale up on CPU > 70%
   - Scale down on CPU < 20%

2. **Database Replicas**
   - 1 primary + 2 read replicas
   - Read replicas in different AZs
   - Automatic failover enabled

3. **Cache Cluster**
   - Redis cluster with 3 nodes
   - Sharding by hash (6GB per shard)
   - Failover enabled

### Vertical Scaling

- EC2: t3.medium → t3.large → t3.xlarge
- RDS: db.t3.medium → db.t3.large → db.t3.xlarge
- ElastiCache: cache.t3.micro → cache.t3.small

---

## Security Architecture

### Authentication & Authorization

```
┌──────────────┐
│ Login Request │
└──────┬───────┘
       │
       ▼
┌────────────────────────┐
│ OAuth 2.0 / Passport   │
│ • Verify credentials   │
│ • Generate JWT Token   │
└──────┬─────────────────┘
       │
       ▼
┌────────────────────────┐
│ Token Response         │
│ • Access Token (1hr)   │
│ • Refresh Token (7d)   │
└────────────────────────┘

Subsequent Requests:
Authorization: Bearer {token}
       │
       ▼
┌──────────────────────────────┐
│ Middleware Verification      │
│ • Decode JWT                 │
│ • Check expiry               │
│ • Verify signature           │
│ • Check permissions/scopes   │
└──────────────────────────────┘
```

### Data Encryption

- **In Transit:** TLS 1.3 (HTTPS)
- **At Rest:** AES-256 (database, S3)
- **Passwords:** bcrypt hashing + salting
- **Sensitive Data:** Encrypted fields (phone, address)

### API Security

- Rate limiting: 1000 req/hour per user
- CORS: Restricted to app domains
- CSRF: Token validation on state-changing requests
- Input validation: Server-side validation on all inputs
- SQL injection prevention: Parameterized queries

---

## Monitoring & Observability

### Metrics & Logging

**CloudWatch Dashboards:**
- API response times
- Error rates and types
- Database performance
- Cache hit rates
- User metrics

**Error Tracking:**
- Sentry integration
- Stack traces and context
- User identification
- Release tracking

**Log Aggregation:**
- CloudWatch Logs
- Structured logging (JSON format)
- Log retention: 30 days

---

## Disaster Recovery

### Backup Strategy

- Database backups: Hourly (automated)
- Backup retention: 30 days
- Cross-region backup: Daily to secondary region
- RTO (Recovery Time Objective): 1 hour
- RPO (Recovery Point Objective): 1 hour

### High Availability

- Multi-AZ deployment
- Automatic failover
- Load balancing across zones
- Database replication
- 99.95% uptime SLA

---

**Document Version:** 1.0
**Last Updated:** May 16, 2026
