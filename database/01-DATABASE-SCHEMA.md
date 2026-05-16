# Database Schema - Nepal Smart Travel & Local Intelligence Platform

## Overview

Complete database schema for the Nepal Smart Travel & Local Intelligence Platform using MySQL 8.0+ with spatial indexes for geospatial data. Managed via phpMyAdmin for easy administration.

---

## Database Design Principles

1. **Normalized Structure** - Minimize data redundancy
2. **Geospatial Optimization** - Use MySQL spatial indexes for location queries
3. **Indexing Strategy** - Optimize for common queries
4. **Audit Trail** - Track changes for critical data
5. **Scalability** - Partition large tables if needed
6. **Performance** - Cache frequently accessed data

---

## Core Tables

### 1. Users Table

```sql
CREATE TABLE users (
    id CHAR(36) PRIMARY KEY COMMENT 'UUID primary key',
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP NULL,
    phone_verified_at TIMESTAMP NULL,
    
    -- Profile Information
    bio TEXT,
    avatar_url VARCHAR(500),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other', 'prefer_not_to_say'),
    
    -- Location & Address (Latitude/Longitude stored as POINT)
    current_location POINT,
    home_address VARCHAR(500),
    district VARCHAR(100),
    
    -- Account Status
    status ENUM('active', 'suspended', 'deleted', 'inactive') DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    
    -- Notification Preferences
    receive_alerts BOOLEAN DEFAULT true,
    receive_email_notifications BOOLEAN DEFAULT true,
    receive_push_notifications BOOLEAN DEFAULT true,
    language_preference VARCHAR(10) DEFAULT 'en',
    
    -- Device Information
    device_token VARCHAR(500),
    last_device_info JSON,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL COMMENT 'Soft delete',
    
    -- Indexes
    KEY idx_email (email),
    KEY idx_status (status),
    SPATIAL KEY idx_location (current_location),
    KEY idx_created_at (created_at),
    KEY idx_district (district)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. User Reputation Table

```sql
CREATE TABLE user_reputations (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL UNIQUE,
    
    -- XP & Level
    total_xp INT DEFAULT 0,
    current_level INT DEFAULT 1,
    
    -- Contribution Stats
    total_reports_submitted INT DEFAULT 0,
    total_reports_approved INT DEFAULT 0,
    total_reports_rejected INT DEFAULT 0,
    approval_rate DECIMAL(5,2) DEFAULT 0,
    
    -- Community Engagement
    total_reviews_helpful INT DEFAULT 0,
    total_reviews_reported INT DEFAULT 0,
    average_review_rating DECIMAL(3,2),
    
    -- Verification Status
    verification_tick VARCHAR(20) DEFAULT 'gray',  -- gray, green, blue, gold, diamond
    is_regional_expert BOOLEAN DEFAULT false,
    expertise_regions TEXT[] DEFAULT ARRAY[]::TEXT[],
    
    -- Badges & Achievements
    badges TEXT[] DEFAULT ARRAY[]::TEXT[],
    achievements JSONB DEFAULT '[]'::jsonb,
    
    -- Historical Data
    reports_last_7_days INTEGER DEFAULT 0,
    reports_last_30_days INTEGER DEFAULT 0,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_contribution_at TIMESTAMP
);

-- Indexes
CREATE INDEX idx_reputations_user_id ON user_reputations(user_id);
CREATE INDEX idx_reputations_level ON user_reputations(current_level);
CREATE INDEX idx_reputations_verification ON user_reputations(verification_tick);
CREATE INDEX idx_reputations_total_xp ON user_reputations(total_xp DESC);
```

### 3. XP Transaction Log

```sql
CREATE TABLE xp_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    -- Transaction Details
    amount INTEGER NOT NULL,
    action_type VARCHAR(100) NOT NULL,  -- 'report_approved', 'emergency_alert', etc.
    reference_id UUID,
    reference_type VARCHAR(100),
    
    -- Metadata
    description TEXT,
    metadata JSONB DEFAULT '{}'::jsonb,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Constraints
    CONSTRAINT positive_xp CHECK (amount > 0 OR action_type IN ('spam_detected', 'fake_report'))
);

-- Indexes
CREATE INDEX idx_xp_transactions_user_id ON xp_transactions(user_id);
CREATE INDEX idx_xp_transactions_action_type ON xp_transactions(action_type);
CREATE INDEX idx_xp_transactions_created_at ON xp_transactions(created_at DESC);
```

---

## Places & Tourism Tables

### 4. Places Table

```sql
CREATE TABLE places (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    
    -- Basic Information
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INTEGER NOT NULL REFERENCES place_categories(id),
    sub_category_id INTEGER REFERENCES place_sub_categories(id),
    
    -- Location Information
    location GEOGRAPHY(POINT, 4326) NOT NULL,
    district VARCHAR(100) NOT NULL,
    municipality VARCHAR(100),
    ward_number INTEGER,
    address VARCHAR(500),
    
    -- Contact & Operating Info
    phone VARCHAR(20),
    email VARCHAR(255),
    website VARCHAR(500),
    operating_hours JSONB,  -- { "monday": {"open": "09:00", "close": "18:00"}, ... }
    
    -- Ratings & Reviews
    average_rating DECIMAL(3,2) DEFAULT 0,
    total_reviews INTEGER DEFAULT 0,
    total_ratings INTEGER DEFAULT 0,
    
    -- Media
    primary_image_url VARCHAR(500),
    images_url TEXT[] DEFAULT ARRAY[]::TEXT[],
    videos_url TEXT[] DEFAULT ARRAY[]::TEXT[],
    
    -- Business Information
    business_owner_name VARCHAR(255),
    business_phone VARCHAR(20),
    business_type ENUM('private', 'government', 'ngo', 'community'),
    
    -- Amenities & Features
    amenities TEXT[] DEFAULT ARRAY[]::TEXT[],  -- wifi, parking, restroom, etc.
    facilities TEXT[] DEFAULT ARRAY[]::TEXT[],
    
    -- Verification
    is_verified BOOLEAN DEFAULT false,
    verification_date TIMESTAMP,
    verified_by UUID REFERENCES users(id),
    
    -- Metadata
    created_by UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

-- Indexes
CREATE INDEX idx_places_category ON places(category_id);
CREATE INDEX idx_places_location ON places USING GIST(location);
CREATE INDEX idx_places_district ON places(district);
CREATE INDEX idx_places_is_verified ON places(is_verified);
CREATE INDEX idx_places_created_at ON places(created_at DESC);
```

### 5. Place Categories Table

```sql
CREATE TABLE place_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon_url VARCHAR(500),
    color_code VARCHAR(7),
    sort_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample Data
INSERT INTO place_categories (name, description, sort_order) VALUES
('Tourist Attractions', 'Temples, monuments, viewpoints', 1),
('Accommodation', 'Hotels, guesthouses, lodges', 2),
('Food & Dining', 'Restaurants, cafes, tea houses', 3),
('Emergency Services', 'Hospitals, police, pharmacies', 4),
('Transportation', 'Bus stations, taxi stands', 5),
('Utilities', 'ATM, fuel, parking', 6),
('Recreation', 'Parks, adventure activities', 7),
('Services', 'Banks, post offices, shops', 8);
```

### 6. Place Reviews Table

```sql
CREATE TABLE place_reviews (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    place_id UUID NOT NULL REFERENCES places(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id),
    
    -- Review Content
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    rating INTEGER NOT NULL,
    
    -- Review Type
    review_type VARCHAR(50),  -- 'positive', 'negative', 'experience'
    
    -- Media
    images_url TEXT[] DEFAULT ARRAY[]::TEXT[],
    
    -- Engagement
    helpful_count INTEGER DEFAULT 0,
    unhelpful_count INTEGER DEFAULT 0,
    
    -- Verification
    is_verified BOOLEAN DEFAULT false,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Constraints
    CONSTRAINT rating_valid CHECK (rating >= 1 AND rating <= 5)
);

-- Indexes
CREATE INDEX idx_reviews_place_id ON place_reviews(place_id);
CREATE INDEX idx_reviews_user_id ON place_reviews(user_id);
CREATE INDEX idx_reviews_rating ON place_reviews(rating);
CREATE INDEX idx_reviews_created_at ON place_reviews(created_at DESC);
```

---

## Reports & Community Tables

### 7. Reports Table

```sql
CREATE TABLE reports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id),
    
    -- Report Content
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category_id INTEGER NOT NULL REFERENCES report_categories(id),
    sub_category_id INTEGER REFERENCES report_sub_categories(id),
    
    -- Location Information
    location GEOGRAPHY(POINT, 4326) NOT NULL,
    district VARCHAR(100) NOT NULL,
    municipality VARCHAR(100),
    location_description VARCHAR(500),
    
    -- Media
    images_url TEXT[] DEFAULT ARRAY[]::TEXT[],
    videos_url TEXT[] DEFAULT ARRAY[]::TEXT[],
    
    -- Status & Verification
    status ENUM('pending', 'approved', 'rejected', 'archived') DEFAULT 'pending',
    verification_status ENUM('unverified', 'verified', 'disputed') DEFAULT 'unverified',
    
    -- Admin Information
    reviewed_by UUID REFERENCES users(id),
    reviewed_at TIMESTAMP,
    admin_notes TEXT,
    rejection_reason VARCHAR(500),
    
    -- Community Engagement
    helpful_count INTEGER DEFAULT 0,
    unhelpful_count INTEGER DEFAULT 0,
    comment_count INTEGER DEFAULT 0,
    
    -- Priority
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    
    -- Expiry
    is_expired BOOLEAN DEFAULT false,
    expires_at TIMESTAMP,
    
    -- GPS Verification
    gps_accuracy DECIMAL(10,6),
    is_gps_verified BOOLEAN DEFAULT false,
    
    -- AI Detection
    ai_spam_score DECIMAL(5,3),
    ai_category_confidence DECIMAL(5,3),
    ai_processed_at TIMESTAMP,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_reports_user_id ON reports(user_id);
CREATE INDEX idx_reports_status ON reports(status);
CREATE INDEX idx_reports_category ON reports(category_id);
CREATE INDEX idx_reports_location ON reports USING GIST(location);
CREATE INDEX idx_reports_district ON reports(district);
CREATE INDEX idx_reports_priority ON reports(priority);
CREATE INDEX idx_reports_created_at ON reports(created_at DESC);
CREATE INDEX idx_reports_expires_at ON reports(expires_at);
```

### 8. Report Categories Table

```sql
CREATE TABLE report_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon_url VARCHAR(500),
    color_code VARCHAR(7),
    sort_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample Data
INSERT INTO report_categories (name, description, sort_order) VALUES
('Road & Traffic', 'Road conditions, blockages, traffic', 1),
('Safety & Hazards', 'Landslides, flooding, dangerous areas', 2),
('Weather & Conditions', 'Extreme weather, visibility', 3),
('Transportation', 'Strikes, service disruptions', 4),
('Hidden Destinations', 'New places, lesser-known attractions', 5),
('Services & Utilities', 'Fuel, power, network', 6),
('Events & Notices', 'Festivals, emergencies', 7),
('General Information', 'Other local information', 8);
```

### 9. Report Reactions Table

```sql
CREATE TABLE report_reactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    report_id UUID NOT NULL REFERENCES reports(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id),
    
    -- Reaction Type
    reaction_type ENUM('helpful', 'unhelpful', 'spam', 'incorrect') NOT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Unique constraint
    CONSTRAINT unique_reaction UNIQUE(report_id, user_id, reaction_type)
);

-- Indexes
CREATE INDEX idx_reactions_report_id ON report_reactions(report_id);
CREATE INDEX idx_reactions_user_id ON report_reactions(user_id);
```

### 10. Report Comments Table

```sql
CREATE TABLE report_comments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    report_id UUID NOT NULL REFERENCES reports(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id),
    parent_comment_id UUID REFERENCES report_comments(id) ON DELETE CASCADE,
    
    -- Content
    content TEXT NOT NULL,
    
    -- Engagement
    helpful_count INTEGER DEFAULT 0,
    
    -- Status
    is_approved BOOLEAN DEFAULT true,
    is_deleted BOOLEAN DEFAULT false,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_comments_report_id ON report_comments(report_id);
CREATE INDEX idx_comments_user_id ON report_comments(user_id);
CREATE INDEX idx_comments_parent_id ON report_comments(parent_comment_id);
```

---

## Road Conditions & Alerts Tables

### 11. Road Conditions Table

```sql
CREATE TABLE road_conditions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    
    -- Road Information
    road_name VARCHAR(255) NOT NULL,
    route_start_point VARCHAR(255),
    route_end_point VARCHAR(255),
    
    -- Location
    location GEOGRAPHY(LINESTRING, 4326),
    district VARCHAR(100),
    
    -- Condition Information
    condition_type VARCHAR(100) NOT NULL,  -- blockage, construction, hazard, etc.
    severity_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    description TEXT,
    
    -- Status
    is_active BOOLEAN DEFAULT true,
    
    -- Source Information
    source_type ENUM('community', 'official', 'traffic_authority', 'news') DEFAULT 'community',
    source_user_id UUID REFERENCES users(id),
    source_reference_id UUID,
    
    -- Timing
    reported_at TIMESTAMP NOT NULL,
    expected_clear_at TIMESTAMP,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_road_conditions_district ON road_conditions(district);
CREATE INDEX idx_road_conditions_location ON road_conditions USING GIST(location);
CREATE INDEX idx_road_conditions_is_active ON road_conditions(is_active);
CREATE INDEX idx_road_conditions_created_at ON road_conditions(created_at DESC);
```

### 12. Emergency Alerts Table

```sql
CREATE TABLE emergency_alerts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    
    -- Alert Information
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    alert_type VARCHAR(100) NOT NULL,  -- earthquake, flood, fire, etc.
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'high',
    
    -- Location
    location GEOGRAPHY(POINT, 4326),
    affected_zones GEOGRAPHY(POLYGON, 4326),
    affected_districts TEXT[] DEFAULT ARRAY[]::TEXT[],
    
    -- Status
    is_active BOOLEAN DEFAULT true,
    
    -- Source
    source_type ENUM('community', 'government', 'official', 'ai_detection'),
    created_by UUID REFERENCES users(id),
    verified_by UUID REFERENCES users(id),
    
    -- Broadcast Info
    broadcast_to_all BOOLEAN DEFAULT true,
    broadcast_radius_km INTEGER,
    
    -- Expiry
    expires_at TIMESTAMP,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_alerts_is_active ON emergency_alerts(is_active);
CREATE INDEX idx_alerts_severity ON emergency_alerts(severity);
CREATE INDEX idx_alerts_location ON emergency_alerts USING GIST(location);
CREATE INDEX idx_alerts_created_at ON emergency_alerts(created_at DESC);
```

---

## AI & Moderation Tables

### 13. Moderation Queue Table

```sql
CREATE TABLE moderation_queue (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    
    -- Content Reference
    content_type VARCHAR(100) NOT NULL,  -- report, comment, review, user
    content_id UUID NOT NULL,
    
    -- Submission Details
    submitted_by UUID REFERENCES users(id),
    
    -- AI Analysis
    ai_spam_score DECIMAL(5,3),
    ai_flags TEXT[] DEFAULT ARRAY[]::TEXT[],
    ai_processed_at TIMESTAMP,
    
    -- Status
    status ENUM('pending', 'approved', 'rejected', 'escalated') DEFAULT 'pending',
    
    -- Assignment
    assigned_to UUID REFERENCES users(id),
    assigned_at TIMESTAMP,
    
    -- Decision
    decision_reason TEXT,
    decided_by UUID REFERENCES users(id),
    decided_at TIMESTAMP,
    
    -- Priority
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_moderation_status ON moderation_queue(status);
CREATE INDEX idx_moderation_priority ON moderation_queue(priority);
CREATE INDEX idx_moderation_assigned_to ON moderation_queue(assigned_to);
CREATE INDEX idx_moderation_created_at ON moderation_queue(created_at ASC);
```

### 14. Content Flags Table

```sql
CREATE TABLE content_flags (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    
    -- Content Reference
    content_type VARCHAR(100) NOT NULL,
    content_id UUID NOT NULL,
    
    -- Flag Information
    flag_reason VARCHAR(255) NOT NULL,
    flag_description TEXT,
    flagged_by UUID NOT NULL REFERENCES users(id),
    
    -- Status
    status ENUM('pending', 'resolved', 'dismissed') DEFAULT 'pending',
    resolved_by UUID REFERENCES users(id),
    resolution_notes TEXT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP
);

-- Indexes
CREATE INDEX idx_flags_content_id ON content_flags(content_id);
CREATE INDEX idx_flags_status ON content_flags(status);
CREATE INDEX idx_flags_created_at ON content_flags(created_at DESC);
```

---

## Notification Tables

### 15. Notifications Table

```sql
CREATE TABLE notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    -- Notification Content
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    notification_type VARCHAR(100) NOT NULL,  -- alert, message, reminder, update
    
    -- References
    related_content_type VARCHAR(100),
    related_content_id UUID,
    
    -- Status
    is_read BOOLEAN DEFAULT false,
    is_archived BOOLEAN DEFAULT false,
    
    -- Delivery
    delivery_channels TEXT[] DEFAULT ARRAY['push']::TEXT[],
    sent_at TIMESTAMP,
    read_at TIMESTAMP,
    
    -- Metadata
    metadata JSONB DEFAULT '{}'::jsonb,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_notifications_is_read ON notifications(is_read);
CREATE INDEX idx_notifications_created_at ON notifications(created_at DESC);
```

---

## Tourism Content Tables

### 16. Tourism Guides Table

```sql
CREATE TABLE tourism_guides (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    
    -- Content
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    detailed_content TEXT,
    
    -- Category
    guide_type VARCHAR(100),  -- destination, trek, food, culture
    category_id INTEGER,
    
    -- Media
    featured_image_url VARCHAR(500),
    images_url TEXT[] DEFAULT ARRAY[]::TEXT[],
    videos_url TEXT[] DEFAULT ARRAY[]::TEXT[],
    
    -- Location
    location_name VARCHAR(255),
    location GEOGRAPHY(POINT, 4326),
    district VARCHAR(100),
    
    -- Guide Information
    best_season VARCHAR(100),
    duration VARCHAR(100),
    difficulty_level VARCHAR(50),
    estimated_cost VARCHAR(100),
    budget_type VARCHAR(50),  -- budget, mid-range, luxury
    
    -- Community Info
    created_by UUID REFERENCES users(id),
    is_official BOOLEAN DEFAULT false,
    is_featured BOOLEAN DEFAULT false,
    
    -- Engagement
    views_count INTEGER DEFAULT 0,
    likes_count INTEGER DEFAULT 0,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_guides_guide_type ON tourism_guides(guide_type);
CREATE INDEX idx_guides_district ON tourism_guides(district);
CREATE INDEX idx_guides_is_featured ON tourism_guides(is_featured);
CREATE INDEX idx_guides_created_at ON tourism_guides(created_at DESC);
```

---

## Medicine Information Tables

### 17. Medicines Table

```sql
CREATE TABLE medicines (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    
    -- Basic Information
    generic_name VARCHAR(255) NOT NULL UNIQUE,
    brand_names TEXT[] DEFAULT ARRAY[]::TEXT[],
    manufacturer VARCHAR(255),
    
    -- Classification
    category VARCHAR(100),
    type VARCHAR(100),
    
    -- Medical Information
    description TEXT,
    uses TEXT,
    dosage_form VARCHAR(100),  -- tablet, capsule, liquid, injection
    
    -- Dosage & Usage
    standard_dosage VARCHAR(255),
    usage_instructions TEXT,
    
    -- Safety Information
    side_effects TEXT,
    contraindications TEXT,
    drug_interactions JSONB,
    warnings TEXT,
    
    -- Availability
    is_available BOOLEAN DEFAULT true,
    requires_prescription BOOLEAN,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_medicines_generic_name ON medicines(generic_name);
CREATE INDEX idx_medicines_category ON medicines(category);
```

---

## User Preferences Tables

### 18. User Preferences Table

```sql
CREATE TABLE user_preferences (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    
    -- Notification Preferences
    alert_notifications BOOLEAN DEFAULT true,
    news_notifications BOOLEAN DEFAULT true,
    community_notifications BOOLEAN DEFAULT true,
    
    -- Content Preferences
    preferred_categories INTEGER[] DEFAULT ARRAY[]::INTEGER[],
    preferred_districts VARCHAR(100)[] DEFAULT ARRAY[]::VARCHAR[],
    
    -- Alert Radius
    alert_radius_km INTEGER DEFAULT 50,
    
    -- Language & Region
    preferred_language VARCHAR(10) DEFAULT 'en',
    temperature_unit VARCHAR(1) DEFAULT 'C',
    
    -- Privacy Settings
    location_visible BOOLEAN DEFAULT false,
    profile_public BOOLEAN DEFAULT true,
    show_profile_contributions BOOLEAN DEFAULT true,
    
    -- Updated At
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_preferences_user_id ON user_preferences(user_id);
```

---

## Admin & System Tables

### 19. Admin Logs Table

```sql
CREATE TABLE admin_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id UUID NOT NULL REFERENCES users(id),
    
    -- Action Details
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(100),
    resource_id UUID,
    
    -- Changes
    old_values JSONB,
    new_values JSONB,
    
    -- Metadata
    ip_address INET,
    user_agent TEXT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_admin_logs_admin_user_id ON admin_logs(admin_user_id);
CREATE INDEX idx_admin_logs_action ON admin_logs(action);
CREATE INDEX idx_admin_logs_created_at ON admin_logs(created_at DESC);
```

### 20. System Settings Table

```sql
CREATE TABLE system_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    setting_type VARCHAR(50),  -- string, integer, boolean, json
    description TEXT,
    is_editable BOOLEAN DEFAULT true,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample Settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('max_report_image_size', '5242880', 'integer', 'Maximum report image size in bytes'),
('report_expiry_days', '30', 'integer', 'Days until a report expires'),
('min_reports_for_green_tick', '10', 'integer', 'Minimum approved reports for green tick'),
('ai_spam_threshold', '0.7', 'string', 'AI spam detection threshold (0-1)');
```

---

## View Definitions (For Common Queries)

### Popular Places View

```sql
CREATE VIEW popular_places_view AS
SELECT
    p.id,
    p.name,
    p.location,
    p.average_rating,
    p.total_reviews,
    pc.name as category,
    COUNT(pr.id) as recent_reviews
FROM places p
JOIN place_categories pc ON p.category_id = pc.id
LEFT JOIN place_reviews pr ON p.id = pr.place_id 
    AND pr.created_at > NOW() - INTERVAL '30 days'
WHERE p.is_verified = true
GROUP BY p.id, pc.name;
```

### Active Reports View

```sql
CREATE VIEW active_reports_view AS
SELECT
    r.id,
    r.title,
    r.location,
    r.category_id,
    rc.name as category,
    r.priority,
    r.helpful_count,
    u.name as reporter_name,
    ur.verification_tick
FROM reports r
JOIN report_categories rc ON r.category_id = rc.id
JOIN users u ON r.user_id = u.id
JOIN user_reputations ur ON u.id = ur.user_id
WHERE r.status = 'approved'
    AND (r.expires_at IS NULL OR r.expires_at > NOW())
ORDER BY r.priority DESC, r.created_at DESC;
```

---

## Partitioning Strategy

For large tables (reports, notifications), implement partitioning by date:

```sql
-- Partition reports by created_at (monthly)
CREATE TABLE reports_2024_01 PARTITION OF reports
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');

CREATE TABLE reports_2024_02 PARTITION OF reports
    FOR VALUES FROM ('2024-02-01') TO ('2024-03-01');
```

---

## Performance Optimization

1. **Indexes:** Covered in each table definition
2. **Connection Pooling:** PgBouncer (20-30 connections per server)
3. **Query Optimization:** Use EXPLAIN ANALYZE for slow queries
4. **Materialized Views:** For complex aggregations
5. **Archive Strategy:** Archive old reports annually

---

**Document Version:** 1.0
**Last Updated:** May 16, 2026
