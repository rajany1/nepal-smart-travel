# API Endpoints Documentation - Nepal Smart Travel & Local Intelligence Platform

## Base URL
```
Production: https://api.nepalsmart travel.com/api/v1
Staging: https://staging-api.nepalsmart travel.com/api/v1
Development: http://localhost:8000/api/v1
```

## Authentication

### JWT Token Headers
```
Authorization: Bearer {access_token}
Accept: application/json
Content-Type: application/json
```

### Token Expiry
- Access Token: 1 hour
- Refresh Token: 7 days

---

## 1. Authentication Endpoints

### Register User
```
POST /auth/register
Content-Type: application/json

Request:
{
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+977-98xxxxxxxx",
    "password": "secure_password_123",
    "password_confirmation": "secure_password_123"
}

Response: 201 Created
{
    "success": true,
    "message": "User registered successfully",
    "data": {
        "user_id": "uuid",
        "email": "john@example.com",
        "access_token": "eyJ...",
        "token_type": "Bearer",
        "expires_in": 3600
    }
}
```

### Login
```
POST /auth/login
Content-Type: application/json

Request:
{
    "email": "john@example.com",
    "password": "secure_password_123"
}

Response: 200 OK
{
    "success": true,
    "data": {
        "user_id": "uuid",
        "access_token": "eyJ...",
        "refresh_token": "eyJ...",
        "expires_in": 3600
    }
}
```

### Refresh Token
```
POST /auth/refresh
Authorization: Bearer {refresh_token}

Response: 200 OK
{
    "access_token": "eyJ...",
    "expires_in": 3600
}
```

### Logout
```
POST /auth/logout
Authorization: Bearer {access_token}

Response: 200 OK
{
    "success": true,
    "message": "Logged out successfully"
}
```

---

## 2. User Endpoints

### Get Current User Profile
```
GET /users/me
Authorization: Bearer {access_token}

Response: 200 OK
{
    "user_id": "uuid",
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+977-98xxxxxxxx",
    "avatar_url": "https://...",
    "bio": "Travel enthusiast",
    "total_xp": 1250,
    "current_level": 6,
    "verification_tick": "green",
    "badges": ["map_master", "safety_hero"],
    "created_at": "2024-01-15T10:30:00Z",
    "status": "active"
}
```

### Update User Profile
```
PUT /users/me
Authorization: Bearer {access_token}
Content-Type: application/json

Request:
{
    "name": "John Doe Updated",
    "bio": "Updated bio",
    "avatar_url": "https://..."
}

Response: 200 OK
{
    "success": true,
    "data": { user object }
}
```

### Get User Reputation
```
GET /users/{user_id}/reputation
Authorization: Bearer {access_token}

Response: 200 OK
{
    "user_id": "uuid",
    "total_xp": 1250,
    "current_level": 6,
    "verification_tick": "green",
    "total_reports": 50,
    "approved_reports": 48,
    "approval_rate": 96,
    "badges": [...],
    "rank": 45,
    "expertise_regions": ["Pokhara", "Kathmandu"],
    "last_contribution_at": "2024-01-15T10:30:00Z"
}
```

---

## 3. Places Endpoints

### Get Nearby Places
```
GET /places/nearby
Authorization: Bearer {access_token}
Query Parameters:
  - lat: latitude (required)
  - lng: longitude (required)
  - radius_km: 5 (default, optional)
  - category_id: 1 (optional)
  - min_rating: 3.5 (optional)
  - limit: 30 (optional)
  - offset: 0 (optional)

Response: 200 OK
{
    "success": true,
    "data": [
        {
            "place_id": "uuid",
            "name": "Eiffel Tower",
            "category": "Tourist Attraction",
            "location": {
                "lat": 27.7172,
                "lng": 85.3240
            },
            "distance_km": 2.5,
            "average_rating": 4.5,
            "total_reviews": 150,
            "image_url": "https://...",
            "phone": "+977-1-xxxxxxx",
            "is_verified": true
        }
    ],
    "pagination": {
        "total": 156,
        "per_page": 30,
        "current_page": 1,
        "last_page": 6
    }
}
```

### Get Place Details
```
GET /places/{place_id}
Authorization: Bearer {access_token}

Response: 200 OK
{
    "place_id": "uuid",
    "name": "Local Restaurant",
    "description": "Traditional Nepali restaurant...",
    "category": "Food & Dining",
    "location": { lat, lng },
    "address": "Thamel, Kathmandu",
    "district": "Kathmandu",
    "phone": "+977-1-xxxxxxx",
    "email": "contact@restaurant.com",
    "website": "https://restaurant.com",
    "operating_hours": {
        "monday": { "open": "09:00", "close": "21:00" },
        "tuesday": { "open": "09:00", "close": "21:00" }
    },
    "average_rating": 4.3,
    "total_reviews": 87,
    "images": ["https://...", "https://..."],
    "amenities": ["wifi", "parking", "restroom"],
    "is_verified": true
}
```

### Add Place Review
```
POST /places/{place_id}/reviews
Authorization: Bearer {access_token}
Content-Type: application/json

Request:
{
    "title": "Great food and service",
    "description": "Excellent traditional dishes with friendly staff...",
    "rating": 5,
    "images": ["base64_image_1", "base64_image_2"]
}

Response: 201 Created
{
    "review_id": "uuid",
    "place_id": "uuid",
    "user_id": "uuid",
    "rating": 5,
    "created_at": "2024-01-15T10:30:00Z"
}
```

---

## 4. Reports Endpoints

### Submit Report
```
POST /reports
Authorization: Bearer {access_token}
Content-Type: multipart/form-data

Request:
{
    "title": "Road blockage on Prithvi Highway",
    "description": "Major landslide blocking traffic near Malekhu...",
    "category_id": 1,
    "location_lat": 27.6500,
    "location_lng": 85.2500,
    "images": [file1, file2],
    "videos": [file3]
}

Response: 201 Created
{
    "report_id": "uuid",
    "status": "pending",
    "message": "Report submitted successfully",
    "created_at": "2024-01-15T10:30:00Z"
}
```

### Get Reports List
```
GET /reports
Authorization: Bearer {access_token}
Query Parameters:
  - status: approved (optional)
  - category_id: 1 (optional)
  - district: Kathmandu (optional)
  - lat: 27.7172 (optional)
  - lng: 85.3240 (optional)
  - radius_km: 10 (optional)
  - sort_by: created_at (optional)
  - limit: 20
  - offset: 0

Response: 200 OK
{
    "success": true,
    "data": [
        {
            "report_id": "uuid",
            "title": "Road blockage",
            "description": "...",
            "category": "Road & Traffic",
            "priority": "high",
            "location": { lat, lng },
            "status": "approved",
            "reporter": {
                "user_id": "uuid",
                "name": "John Doe",
                "verification_tick": "green"
            },
            "helpful_count": 45,
            "created_at": "2024-01-15T10:30:00Z"
        }
    ],
    "pagination": {...}
}
```

### Get Report Details
```
GET /reports/{report_id}
Authorization: Bearer {access_token}

Response: 200 OK
{
    "report_id": "uuid",
    "title": "Road blockage on Prithvi Highway",
    "description": "Major landslide blocking traffic...",
    "category": "Road & Traffic",
    "priority": "high",
    "status": "approved",
    "location": { lat, lng },
    "images": ["https://...", "https://..."],
    "videos": ["https://..."],
    "reporter": {...},
    "helpful_count": 45,
    "comments_count": 12,
    "verified_by": {...},
    "verified_at": "2024-01-15T11:00:00Z",
    "created_at": "2024-01-15T10:30:00Z"
}
```

### Update Report (User's own report)
```
PUT /reports/{report_id}
Authorization: Bearer {access_token}
Content-Type: application/json

Request:
{
    "title": "Updated title",
    "description": "Updated description"
}

Response: 200 OK
{
    "success": true,
    "data": { updated report }
}
```

### Add Report Comment
```
POST /reports/{report_id}/comments
Authorization: Bearer {access_token}
Content-Type: application/json

Request:
{
    "content": "Update: Road cleared at 2 PM",
    "parent_comment_id": "uuid" (optional)
}

Response: 201 Created
{
    "comment_id": "uuid",
    "content": "...",
    "created_at": "2024-01-15T10:30:00Z"
}
```

### React to Report
```
POST /reports/{report_id}/reactions
Authorization: Bearer {access_token}
Content-Type: application/json

Request:
{
    "reaction_type": "helpful"  // helpful, unhelpful, spam, incorrect
}

Response: 201 Created
{
    "success": true,
    "message": "Reaction recorded"
}
```

---

## 5. Road Conditions Endpoints

### Get Road Conditions
```
GET /road-conditions
Authorization: Bearer {access_token}
Query Parameters:
  - district: Kathmandu (optional)
  - severity: high (optional)
  - lat, lng, radius_km (optional)

Response: 200 OK
{
    "success": true,
    "data": [
        {
            "condition_id": "uuid",
            "road_name": "Prithvi Highway",
            "condition_type": "blockage",
            "severity": "high",
            "description": "Landslide blocking traffic...",
            "location": { lat, lng },
            "is_active": true,
            "source": "community",
            "reported_at": "2024-01-15T09:00:00Z",
            "expected_clear_at": "2024-01-15T18:00:00Z"
        }
    ]
}
```

---

## 6. Emergency Alerts Endpoints

### Get Active Alerts
```
GET /alerts
Authorization: Bearer {access_token}
Query Parameters:
  - severity: critical (optional)
  - affected_district: Kathmandu (optional)
  - lat, lng, radius_km (optional)

Response: 200 OK
{
    "success": true,
    "data": [
        {
            "alert_id": "uuid",
            "title": "[URGENT] Earthquake Alert",
            "description": "...",
            "alert_type": "earthquake",
            "severity": "critical",
            "location": { lat, lng },
            "affected_districts": ["Kathmandu", "Bhaktapur"],
            "created_at": "2024-01-15T10:30:00Z",
            "expires_at": "2024-01-15T12:30:00Z"
        }
    ]
}
```

---

## 7. AI Assistant Endpoints

### Chat with AI Assistant
```
POST /assistant/chat
Authorization: Bearer {access_token}
Content-Type: application/json

Request:
{
    "message": "Best places to visit near Pokhara",
    "context": {
        "lat": 28.2096,
        "lng": 83.9856
    }
}

Response: 200 OK
{
    "success": true,
    "data": {
        "response": "Here are the best places to visit near Pokhara: ...",
        "suggestions": [
            "Phewa Lake",
            "Sarangkot",
            "Devi's Fall"
        ],
        "type": "recommendation"
    }
}
```

---

## 8. Admin Endpoints

### Get Moderation Queue
```
GET /admin/moderation/queue
Authorization: Bearer {admin_token}
Query Parameters:
  - status: pending
  - priority: high
  - limit: 20
  - offset: 0

Response: 200 OK
{
    "success": true,
    "data": [
        {
            "queue_id": "uuid",
            "content_type": "report",
            "content_id": "uuid",
            "submitted_by": {...},
            "ai_spam_score": 0.25,
            "status": "pending",
            "priority": "medium",
            "created_at": "2024-01-15T10:30:00Z"
        }
    ]
}
```

### Approve Content
```
POST /admin/moderation/{queue_id}/approve
Authorization: Bearer {admin_token}
Content-Type: application/json

Response: 200 OK
{
    "success": true,
    "message": "Content approved and published"
}
```

### Reject Content
```
POST /admin/moderation/{queue_id}/reject
Authorization: Bearer {admin_token}
Content-Type: application/json

Request:
{
    "reason": "Spam content"
}

Response: 200 OK
{
    "success": true,
    "message": "Content rejected"
}
```

---

## 9. Analytics Endpoints

### User Statistics
```
GET /admin/analytics/users
Authorization: Bearer {admin_token}
Query Parameters:
  - period: month  // day, week, month, year
  - date_from: 2024-01-01
  - date_to: 2024-01-31

Response: 200 OK
{
    "total_users": 50000,
    "active_users": 30000,
    "new_users": 5000,
    "user_growth": 10,
    "by_region": {...}
}
```

---

## Error Responses

### 400 Bad Request
```json
{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Validation failed",
        "details": {
            "email": ["The email field is required"],
            "phone": ["Phone must be valid"]
        }
    }
}
```

### 401 Unauthorized
```json
{
    "success": false,
    "error": {
        "code": "UNAUTHORIZED",
        "message": "Invalid or expired token"
    }
}
```

### 403 Forbidden
```json
{
    "success": false,
    "error": {
        "code": "FORBIDDEN",
        "message": "You don't have permission to access this resource"
    }
}
```

### 404 Not Found
```json
{
    "success": false,
    "error": {
        "code": "NOT_FOUND",
        "message": "Resource not found"
    }
}
```

### 429 Too Many Requests
```json
{
    "success": false,
    "error": {
        "code": "RATE_LIMITED",
        "message": "Too many requests. Please try again later",
        "retry_after": 60
    }
}
```

### 500 Server Error
```json
{
    "success": false,
    "error": {
        "code": "SERVER_ERROR",
        "message": "An unexpected error occurred"
    }
}
```

---

## Rate Limiting

**Limits per endpoint:**
- Authentication: 5 requests/minute per IP
- Search/List: 100 requests/minute per user
- Create: 50 requests/minute per user
- Update: 50 requests/minute per user
- Admin endpoints: 1000 requests/minute per admin

**Headers:**
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1642310400
```

---

## Pagination

All list endpoints support pagination:

**Query Parameters:**
- `limit`: Items per page (default: 20, max: 100)
- `offset`: Starting position (default: 0)
- `sort_by`: Field to sort by (default: created_at)
- `sort_order`: asc or desc (default: desc)

**Response:**
```json
{
    "data": [...],
    "pagination": {
        "total": 1500,
        "per_page": 20,
        "current_page": 1,
        "last_page": 75,
        "has_more": true
    }
}
```

---

**Document Version:** 1.0
**Last Updated:** May 16, 2026
