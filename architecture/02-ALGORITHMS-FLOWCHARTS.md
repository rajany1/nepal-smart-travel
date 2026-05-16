# Algorithms & Flowcharts - Nepal Smart Travel & Local Intelligence Platform

## Overview

This document details the core algorithms and process flows for critical system features.

---

## 1. Report Verification & Moderation Algorithm

### Process Flow

```
┌─────────────────────────────────┐
│ Community Report Submitted      │
│ - Title, Description, Location  │
│ - Category, Images/Videos       │
└─────────────┬───────────────────┘
              │
              ▼
┌─────────────────────────────────────────┐
│ STEP 1: Data Validation                 │
├─────────────────────────────────────────┤
│ ✓ Check title length (min 10, max 255)  │
│ ✓ Check description length (min 20)     │
│ ✓ Validate GPS coordinates              │
│ ✓ Verify image format & size (<5MB)     │
│ ✓ Check required fields                 │
└─────────────┬───────────────────────────┘
              │
              ├─ [FAIL] ──> Reject with error message
              │
              ▼
┌─────────────────────────────────────────┐
│ STEP 2: AI Preliminary Check            │
├─────────────────────────────────────────┤
│ ✓ Run spam detection model              │
│ ✓ Check for duplicate reports           │
│ ✓ Validate GPS accuracy (±100m)         │
│ ✓ Content moderation check              │
│ ✓ Calculate risk score                  │
└─────────────┬───────────────────────────┘
              │
              ├─ [Spam Score > 0.8]
              │         ▼
              │  Auto-Reject as Spam
              │  (Archive, notify user)
              │
              ├─ [Duplicate found]
              │         ▼
              │  Mark as duplicate
              │  (Link to original)
              │
              ├─ [GPS invalid]
              │         ▼
              │  Flag for manual review
              │  (Increase priority)
              │
              ▼
┌─────────────────────────────────────────┐
│ STEP 3: Categorization                  │
├─────────────────────────────────────────┤
│ ✓ Classify report category              │
│ ✓ Determine priority level              │
│ ✓ Calculate confidence score            │
│ ✓ Assign tags & keywords                │
└─────────────┬───────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────┐
│ STEP 4: Moderation Queue Assignment     │
├─────────────────────────────────────────┤
│ ✓ Route by category                     │
│ ✓ Assign based on priority              │
│ ✓ Distribute to regional moderators     │
│ ✓ Set SLA (Service Level Agreement)     │
└─────────────┬───────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────┐
│ STEP 5: Manual Moderation Review        │
├─────────────────────────────────────────┤
│ Admin/Moderator:                        │
│ ✓ Review content & images               │
│ ✓ Verify facts if possible              │
│ ✓ Check GPS location validity           │
│ ✓ Cross-check with official sources     │
└─────────────┬───────────────────────────┘
              │
              ├─────────────┬──────────────┐
              │             │              │
              ▼             ▼              ▼
          [APPROVE]    [REJECT]      [FLAG FOR REVIEW]
              │             │              │
              ▼             ▼              ▼
       ┌──────────┐  ┌──────────┐  ┌──────────────┐
       │ APPROVED │  │ REJECTED │  │ ESCALATE TO  │
       │          │  │          │  │ SENIOR ADMIN │
       └─────┬────┘  └─────┬────┘  └──────┬───────┘
             │             │              │
             ▼             ▼              ▼
       ┌──────────────────────────────────────┐
       │ STEP 6: Post-Decision Actions        │
       ├──────────────────────────────────────┤
       │ [APPROVED]                           │
       │ ✓ Publish to public feed             │
       │ ✓ Award reporter XP                  │
       │ ✓ Update road/place data             │
       │ ✓ Send notifications to nearby users │
       │ ✓ Update reputation score            │
       │                                      │
       │ [REJECTED]                           │
       │ ✓ Archive in database                │
       │ ✓ Send feedback to reporter          │
       │ ✓ Flag pattern if needed             │
       │ ✓ Warning to user (if spam)          │
       │ ✓ Reduce reputation if false         │
       │                                      │
       │ [ESCALATED]                          │
       │ ✓ Keep in queue                      │
       │ ✓ Assign to senior reviewer          │
       │ ✓ Set urgent priority                │
       └──────────────────────────────────────┘
```

### Pseudocode

```python
function process_report(report):
    # Step 1: Validate
    if not validate_report_data(report):
        return reject(report, "Invalid data")
    
    # Step 2: AI Check
    ai_result = run_ai_checks(report)
    if ai_result.spam_score > 0.8:
        return auto_reject(report, "Spam detected")
    
    if check_duplicate(report):
        return mark_as_duplicate(report)
    
    # Step 3: Categorize
    report.category = classify_category(report.text)
    report.priority = calculate_priority(report)
    report.tags = extract_keywords(report.text)
    
    # Step 4: Queue Assignment
    moderator = assign_moderator(report)
    moderation_queue.add({
        report_id: report.id,
        moderator_id: moderator.id,
        priority: report.priority,
        due_date: calculate_sla(report.priority)
    })
    
    # Step 5: Notify
    notify_moderator(moderator, report)
    
    return success("Report queued for moderation")


function approve_report(report_id, reviewer_id):
    report = get_report(report_id)
    
    # Update report
    report.status = 'approved'
    report.reviewed_by = reviewer_id
    report.reviewed_at = NOW()
    report.save()
    
    # Award XP
    xp_amount = calculate_xp(report)
    award_xp(report.user_id, xp_amount, 'report_approved')
    
    # Update reputation
    update_user_reputation(report.user_id)
    
    # Publish to feed
    publish_to_feed(report)
    
    # Send notifications
    notify_nearby_users(report)
    
    # Update affected data
    if report.category == 'road_condition':
        update_road_status(report)


function reject_report(report_id, reviewer_id, reason):
    report = get_report(report_id)
    
    # Update report
    report.status = 'rejected'
    report.rejection_reason = reason
    report.reviewed_by = reviewer_id
    report.reviewed_at = NOW()
    report.save()
    
    # Notify user
    send_rejection_notification(report.user_id, reason)
    
    # Penalty for spam
    if reason == 'spam':
        penalty_xp(report.user_id, 20)
        
        # Check for ban
        if user_spam_count(report.user_id) > 5:
            flag_user_for_review(report.user_id)
```

---

## 2. XP & Reputation Algorithm

### XP Calculation Engine

```
┌──────────────────────────────────────────┐
│ User Action Triggered                    │
├──────────────────────────────────────────┤
│ • Report Approved                        │
│ • Emergency Alert Verified               │
│ • Hidden Place Discovered                │
│ • Helpful Image Uploaded                 │
│ • High Rating Received                   │
│ • Report Flagged as Most Helpful         │
└────────────┬─────────────────────────────┘
             │
             ▼
┌──────────────────────────────────────────┐
│ Calculate Base XP                        │
├──────────────────────────────────────────┤
│ base_xp = lookup_xp(action_type)         │
│                                          │
│ Example Lookup Table:                    │
│ • report_approved: 10 XP                 │
│ • emergency_verified: 25 XP              │
│ • hidden_place: 15 XP                    │
│ • helpful_image: 5 XP                    │
└────────────┬─────────────────────────────┘
             │
             ▼
┌──────────────────────────────────────────┐
│ Apply Multipliers                        │
├──────────────────────────────────────────┤
│ if user.verification_tick == 'green':    │
│   multiplier = 1.15                      │
│ elif user.verification_tick == 'blue':   │
│   multiplier = 1.25                      │
│ elif user.verification_tick == 'gold':   │
│   multiplier = 1.35                      │
│                                          │
│ adjusted_xp = base_xp * multiplier       │
└────────────┬─────────────────────────────┘
             │
             ▼
┌──────────────────────────────────────────┐
│ Quality Bonus Check                      │
├──────────────────────────────────────────┤
│ if report_accuracy > 95%:                │
│   bonus_xp = adjusted_xp * 0.1           │
│                                          │
│ if report_helpfulness_rating > 4.5/5:    │
│   bonus_xp += adjusted_xp * 0.15         │
│                                          │
│ total_xp = adjusted_xp + bonus_xp        │
└────────────┬─────────────────────────────┘
             │
             ▼
┌──────────────────────────────────────────┐
│ Apply Penalties (if applicable)          │
├──────────────────────────────────────────┤
│ if action == 'spam_detected':            │
│   penalty = 20 XP                        │
│   total_xp = total_xp - penalty          │
│                                          │
│ if total_xp < 0:                         │
│   total_xp = 0                           │
└────────────┬─────────────────────────────┘
             │
             ▼
┌──────────────────────────────────────────┐
│ Award XP & Log Transaction               │
├──────────────────────────────────────────┤
│ user.total_xp += total_xp                │
│ save_xp_transaction({                    │
│   user_id: user.id,                      │
│   amount: total_xp,                      │
│   action: action_type,                   │
│   timestamp: NOW()                       │
│ })                                       │
└────────────┬─────────────────────────────┘
             │
             ▼
┌──────────────────────────────────────────┐
│ Update User Level                        │
├──────────────────────────────────────────┤
│ new_level = calculate_level(user.total_xp)│
│                                          │
│ if new_level > user.current_level:       │
│   • Send level-up notification           │
│   • Award level-up bonus                 │
│   • Update verification tier if needed   │
└──────────────────────────────────────────┘
```

### Level Calculation

```python
def calculate_level(total_xp):
    levels = {
        1: (0, 50),          # Explorer (0-50)
        2: (50, 150),        # Explorer (50-150)
        3: (150, 300),       # Explorer (150-300)
        4: (300, 500),       # Explorer (300-500)
        5: (500, 750),       # Explorer (500-750)
        6: (750, 1100),      # Contributor (750-1100)
        7: (1100, 1500),     # Contributor
        8: (1500, 2000),     # Contributor
        # ... continues
        30: (50000, 100000), # Trusted Local (high end)
        31: (100000, 200000),# Regional Guide
        # ... continues
        51: (1000000, float('inf'))  # Community Expert
    }
    
    for level, (min_xp, max_xp) in levels.items():
        if min_xp <= total_xp < max_xp:
            return level
    
    return 51  # Legendary


def get_tier_from_level(level):
    tiers = {
        'explorer': [1, 2, 3, 4, 5],
        'contributor': [6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
        'trusted_local': [16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30],
        'regional_guide': [31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50],
        'community_expert': [51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75],
    }
    
    for tier, levels in tiers.items():
        if level in levels:
            return tier
    
    return 'explorer'
```

---

## 3. Verification Tick Assignment Algorithm

### Tick Assignment Logic

```
┌─────────────────────────────────────┐
│ User XP/Reports Updated             │
└────────────┬────────────────────────┘
             │
             ▼
┌────────────────────────────────────────────┐
│ GRAY TICK Check (Automatic)                │
├────────────────────────────────────────────┤
│ Condition: Account created successfully    │
│ Assigned: Immediately upon registration    │
│ Revoked: Never (baseline tick)             │
└────────────┬─────────────────────────────────┘
             │
             ▼
┌────────────────────────────────────────────┐
│ GREEN TICK Check                           │
├────────────────────────────────────────────┤
│ Requirement:                               │
│ • total_approved_reports >= 10             │
│ • approval_rate >= 80%                     │
│ • account_age >= 30 days                   │
│ • no_active_warnings                       │
│                                            │
│ Status: Auto-upgrade or manual verification│
└────────────┬─────────────────────────────────┘
             │
             ▼ [If GREEN eligible]
┌────────────────────────────────────────────┐
│ BLUE TICK Check                            │
├────────────────────────────────────────────┤
│ Requirement:                               │
│ • total_approved_reports >= 30             │
│ • approval_rate >= 90%                     │
│ • account_age >= 90 days                   │
│ • no_violations_last_30_days               │
│ • helpful_rating >= 4.0/5.0                │
│                                            │
│ Status: Manual verification by admin       │
└────────────┬─────────────────────────────────┘
             │
             ▼ [If BLUE eligible]
┌────────────────────────────────────────────┐
│ GOLD TICK Check                            │
├────────────────────────────────────────────┤
│ Requirement:                               │
│ • total_approved_reports >= 50             │
│ • approval_rate >= 95%                     │
│ • total_xp >= 30,000                       │
│ • in_leaderboard_top_100                   │
│ • regional_expert_status OR                │
│   consistent_high_quality_contributor      │
│                                            │
│ Status: Admin nomination & verification    │
└────────────┬─────────────────────────────────┘
             │
             ▼ [If GOLD eligible]
┌────────────────────────────────────────────┐
│ DIAMOND TICK Check                         │
├────────────────────────────────────────────┤
│ Requirement:                               │
│ • total_approved_reports >= 100            │
│ • approval_rate >= 97%                     │
│ • total_xp >= 100,000                      │
│ • consistent_excellence (12+ months)       │
│ • significant_community_contribution       │
│ • special_merit_or_expertise               │
│                                            │
│ Status: Executive admin decision            │
└────────────────────────────────────────────┘
```

### Pseudocode

```python
def check_tick_eligibility(user_id):
    user = get_user(user_id)
    reputation = get_user_reputation(user_id)
    
    # Check for tick downgrade (violations)
    if check_recent_violations(user_id):
        downgrade_tick_if_needed(user_id)
        return
    
    # Check GREEN TICK
    if (reputation.total_approved_reports >= 10 and
        reputation.approval_rate >= 0.80 and
        days_since_registration(user) >= 30):
        
        if user.verification_tick == 'gray':
            upgrade_tick(user_id, 'green')
            send_notification(user_id, "You've earned a Green Tick!")
    
    # Check BLUE TICK
    elif (reputation.total_approved_reports >= 30 and
          reputation.approval_rate >= 0.90 and
          days_since_registration(user) >= 90):
        
        if user.verification_tick == 'green':
            queue_for_manual_verification(user_id, 'blue')
    
    # Check GOLD TICK
    elif (reputation.total_approved_reports >= 50 and
          reputation.approval_rate >= 0.95 and
          reputation.total_xp >= 30000):
        
        if user.verification_tick == 'blue':
            queue_for_admin_nomination(user_id, 'gold')
    
    # Check DIAMOND TICK
    elif (reputation.total_approved_reports >= 100 and
          reputation.approval_rate >= 0.97 and
          reputation.total_xp >= 100000):
        
        if user.verification_tick == 'gold':
            queue_for_executive_review(user_id, 'diamond')


def downgrade_tick_if_needed(user_id):
    violations = get_active_violations(user_id)
    user = get_user(user_id)
    
    violation_count = count_violations(violations)
    
    if violation_count >= 3 and user.verification_tick != 'gray':
        downgrade_tick(user_id, 'gray')
        send_warning(user_id, "Your verification tick has been downgraded")
    elif violation_count >= 2 and user.verification_tick in ['gold', 'diamond']:
        downgrade_tick(user_id, 'blue')
```

---

## 4. Nearby Places Discovery Algorithm

### Algorithm Flow

```
┌────────────────────────────────────┐
│ User Opens "Nearby" Feature        │
│ Location Enabled                   │
└────────────┬───────────────────────┘
             │
             ▼
┌────────────────────────────────────────┐
│ Get User Current Location              │
├────────────────────────────────────────┤
│ • Request precise GPS coordinates      │
│ • Validate accuracy (±100m)            │
│ • Get user's district/region           │
└────────────┬───────────────────────────┘
             │
             ▼
┌────────────────────────────────────────┐
│ Define Search Radius                   │
├────────────────────────────────────────┤
│ User settings: 5km (default)           │
│ Can be adjusted: 1km - 50km            │
│ Return to cache for next 10 minutes    │
└────────────┬───────────────────────────┘
             │
             ▼
┌────────────────────────────────────────┐
│ Query Places Database                  │
├────────────────────────────────────────┤
│ SQL Query:                             │
│ SELECT * FROM places                   │
│ WHERE ST_DWithin(                      │
│   location,                            │
│   user_location,                       │
│   search_radius                        │
│ )                                      │
│ AND is_verified = true                 │
│ LIMIT 100                              │
└────────────┬───────────────────────────┘
             │
             ▼
┌────────────────────────────────────────┐
│ Apply Filters (if specified)           │
├────────────────────────────────────────┤
│ • Category filter                      │
│ • Price range                          │
│ • Rating minimum                       │
│ • Operating hours                      │
│ • Amenities                            │
└────────────┬───────────────────────────┘
             │
             ▼
┌────────────────────────────────────────┐
│ Calculate Relevance Score              │
├────────────────────────────────────────┤
│ score = (                              │
│   distance_score(50%) +                │
│   rating_score(30%) +                  │
│   popularity_score(20%)                │
│ )                                      │
│                                        │
│ distance_score = (1 - dist/radius) * 100 │
│ rating_score = average_rating * 20    │
│ popularity_score = reviews_count       │
└────────────┬───────────────────────────┘
             │
             ▼
┌────────────────────────────────────────┐
│ Sort & Rank Results                    │
├────────────────────────────────────────┤
│ 1. Sort by relevance score (DESC)      │
│ 2. Group by category                   │
│ 3. Prioritize verified listings        │
│ 4. Boost open/operating places         │
└────────────┬───────────────────────────┘
             │
             ▼
┌────────────────────────────────────────┐
│ Format & Return Results                │
├────────────────────────────────────────┤
│ • Top 30 results initially             │
│ • Pagination available                 │
│ • Include distance & ETA               │
│ • Include ratings & reviews            │
│ • Include operating status             │
└────────────────────────────────────────┘
```

### Pseudocode

```python
def find_nearby_places(user_id, radius_km=5, filters=None):
    # Get user location
    user_location = get_user_current_location(user_id)
    if not user_location:
        return error("Location permission required")
    
    # Check cache first
    cache_key = f"nearby:{user_location.lat}:{user_location.lng}"
    cached_places = cache.get(cache_key)
    if cached_places and not filters:
        return cached_places
    
    # Query database using MySQL spatial functions
    query = """
        SELECT 
            p.*,
            ST_Distance_Sphere(p.location, POINT(%s, %s)) as distance,
            CASE WHEN p.is_verified THEN 10 ELSE 0 END as verify_bonus
        FROM places p
        WHERE ST_Distance_Sphere(p.location, POINT(%s, %s)) <= %s * 1000
            AND p.is_verified = true
        ORDER BY distance ASC
        LIMIT 100
    """
    
    places = db.execute(query, [
        user_location.point,
        user_location.point,
        radius_km * 1000  # Convert to meters
    ])
    
    # Apply filters
    if filters:
        places = apply_filters(places, filters)
    
    # Calculate relevance scores
    scored_places = []
    for place in places:
        score = calculate_place_score(
            distance=place.distance,
            rating=place.average_rating,
            reviews=place.total_reviews,
            radius=radius_km * 1000
        )
        scored_places.append({
            **place,
            'relevance_score': score
        })
    
    # Sort by score
    scored_places.sort(key=lambda x: x['relevance_score'], reverse=True)
    
    # Cache results
    cache.set(cache_key, scored_places[:30], timeout=600)
    
    return scored_places[:30]


def calculate_place_score(distance, rating, reviews, radius):
    # Normalize distance (0-1, inverted)
    distance_score = max(0, 1 - (distance / radius)) * 50
    
    # Rating score
    rating_score = (rating / 5.0) * 30
    
    # Popularity score
    popularity_score = min(20, (reviews / 100) * 20)
    
    return distance_score + rating_score + popularity_score
```

---

## 5. Emergency Alert Broadcasting Algorithm

### Alert Distribution Flow

```
┌─────────────────────────────────┐
│ Critical Event Detected         │
│ (Earthquake, Flood, Accident)   │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────────┐
│ STEP 1: Verification               │
├─────────────────────────────────────┤
│ • Cross-check with official sources │
│ • Validate location coordinates     │
│ • Confirm urgency level            │
│ • Get admin approval if needed     │
└────────────┬────────────────────────┘
             │
             ▼
┌─────────────────────────────────────┐
│ STEP 2: Alert Creation             │
├─────────────────────────────────────┤
│ • Generate unique alert ID         │
│ • Set severity level               │
│ • Define affected zones            │
│ • Calculate broadcast radius       │
│ • Set expiry time                  │
└────────────┬────────────────────────┘
             │
             ▼
┌─────────────────────────────────────┐
│ STEP 3: Identify Target Users       │
├─────────────────────────────────────┤
│ Query:                              │
│ SELECT user_id FROM users           │
│ WHERE current_location IS WITHIN    │
│   broadcast_zone OR                 │
│   alert_radius_preference >= zone   │
│ AND receive_alerts = true           │
│ AND status = 'active'               │
└────────────┬────────────────────────┘
             │
             ▼
┌─────────────────────────────────────┐
│ STEP 4: Prepare Notification Content│
├─────────────────────────────────────┤
│ Title: "[URGENT] Emergency Alert"   │
│ Message: Alert summary              │
│ Metadata:                           │
│ • Alert type                        │
│ • Severity level                    │
│ • Location details                  │
│ • Action required                   │
│ • Nearest help resources            │
└────────────┬────────────────────────┘
             │
             ▼
┌─────────────────────────────────────┐
│ STEP 5: Batch Notification Send     │
├─────────────────────────────────────┤
│ ┌──────────────────────────────┐   │
│ │ Batch 1: Users with FCM token│   │
│ │ Method: Firebase Cloud       │   │
│ │ Messaging (high priority)    │   │
│ └──────────────────────────────┘   │
│                                    │
│ ┌──────────────────────────────┐   │
│ │ Batch 2: In-app users        │   │
│ │ Method: WebSocket broadcast  │   │
│ │ (real-time feed)             │   │
│ └──────────────────────────────┘   │
│                                    │
│ ┌──────────────────────────────┐   │
│ │ Batch 3: Email users         │   │
│ │ Method: Email queue job      │   │
│ │ (secondary channel)          │   │
│ └──────────────────────────────┘   │
└────────────┬────────────────────────┘
             │
             ▼
┌─────────────────────────────────────┐
│ STEP 6: Delivery Tracking           │
├─────────────────────────────────────┤
│ • Track delivery status             │
│ • Log delivery timestamps           │
│ • Monitor user engagement           │
│ • Re-send if not delivered          │
└────────────┬────────────────────────┘
             │
             ▼
┌─────────────────────────────────────┐
│ STEP 7: User Interaction Tracking   │
├─────────────────────────────────────┤
│ • Track notification views          │
│ • Monitor click-through rate        │
│ • Track user actions (SOS, etc)    │
│ • Update alert engagement stats     │
└────────────┬────────────────────────┘
             │
             ▼
┌─────────────────────────────────────┐
│ STEP 8: Real-Time Feed Update       │
├─────────────────────────────────────┤
│ • Broadcast via WebSocket           │
│ • Update main feed with alert       │
│ • Add to alert timeline             │
│ • Pin critical alerts               │
└────────────────────────────────────┘
```

### Pseudocode

```python
def broadcast_emergency_alert(alert_data, admin_user_id):
    # Step 1: Verify & validate
    if not verify_alert_legitimacy(alert_data):
        return error("Alert verification failed")
    
    # Step 2: Create alert record
    alert = create_alert({
        title: alert_data.title,
        description: alert_data.description,
        alert_type: alert_data.type,
        severity: alert_data.severity,
        location: alert_data.location,
        affected_zones: alert_data.affected_zones,
        created_by: admin_user_id,
        verified_at: NOW(),
        expires_at: NOW() + alert_data.duration
    })
    
    # Step 3: Find target users
    target_users = find_users_in_zones(
        zones=alert.affected_zones,
        radius_km=alert.broadcast_radius
    )
    
    # Step 4: Prepare content
    notification_content = prepare_alert_notification(alert)
    
    # Step 5: Send notifications in batches
    batch_size = 1000
    for i in range(0, len(target_users), batch_size):
        batch = target_users[i:i+batch_size]
        
        # Send FCM
        fcm_tokens = [u.device_token for u in batch if u.device_token]
        send_fcm_batch(fcm_tokens, notification_content, priority='high')
        
        # Send WebSocket
        for user in batch:
            if user.is_online:
                send_websocket_notification(user.id, notification_content)
        
        # Queue email
        for user in batch:
            if user.receive_email_notifications:
                queue_email_notification(user.email, notification_content)
    
    # Step 6: Log delivery
    log_alert_delivery(alert.id, len(target_users))
    
    return success(f"Alert broadcast to {len(target_users)} users")


def find_users_in_zones(zones, radius_km=None):
    query = """
        SELECT u.* FROM users u
        WHERE (
            ST_DWithin(u.current_location, %s, %s) OR
            u.district = ANY(%s::text[])
        )
        AND u.status = 'active'
        AND u.receive_alerts = true
    """
    
    return db.execute(query, [
        zones[0],  # Primary zone center
        radius_km * 1000 if radius_km else 10000,
        [z.district for z in zones]
    ])
```

---

## 6. AI Travel Assistant Query Processing

### NLP Query Processing Pipeline

```
┌──────────────────────────────────┐
│ User Types Query                 │
│ "Best places near Pokhara?"      │
└────────────┬─────────────────────┘
             │
             ▼
┌──────────────────────────────────────┐
│ STEP 1: Text Preprocessing           │
├──────────────────────────────────────┤
│ • Convert to lowercase               │
│ • Remove special characters          │
│ • Tokenize into words                │
│ • Normalize text                     │
│ • Remove stop words                  │
└────────────┬──────────────────────────┘
             │
             ▼
┌──────────────────────────────────────┐
│ STEP 2: Intent Classification        │
├──────────────────────────────────────┤
│ Intent: 'place_recommendation'       │
│ Confidence: 0.95                     │
│                                      │
│ Possible Intents:                    │
│ • place_recommendation               │
│ • route_planning                     │
│ • emergency_help                     │
│ • safety_query                       │
│ • service_discovery                  │
│ • information_request                │
│ • general_chat                       │
└────────────┬──────────────────────────┘
             │
             ▼
┌──────────────────────────────────────┐
│ STEP 3: Entity Extraction            │
├──────────────────────────────────────┤
│ Entities Found:                      │
│ • LOCATION: "Pokhara"                │
│ • CATEGORY: "places" (inferred)      │
│ • SENTIMENT: "positive"              │
│ • FILTERS: "best" (quality filter)   │
└────────────┬──────────────────────────┘
             │
             ▼
┌──────────────────────────────────────┐
│ STEP 4: Data Retrieval               │
├──────────────────────────────────────┤
│ Query Database:                      │
│ • Get Pokhara location               │
│ • Fetch top-rated places             │
│ • Get current road conditions        │
│ • Check weather for region           │
│ • Get user preferences               │
└────────────┬──────────────────────────┘
             │
             ▼
┌──────────────────────────────────────┐
│ STEP 5: Response Generation          │
├──────────────────────────────────────┤
│ Using GPT-4:                         │
│ • Create personalized response       │
│ • Include top 5 recommendations      │
│ • Add travel tips                    │
│ • Include safety information         │
│ • Add estimated costs/times          │
└────────────┬──────────────────────────┘
             │
             ▼
┌──────────────────────────────────────┐
│ STEP 6: Response Formatting          │
├──────────────────────────────────────┤
│ • Clean markup                       │
│ • Add rich formatting                │
│ • Include images if available        │
│ • Add action buttons                 │
│ • Make mobile-friendly               │
└────────────┬──────────────────────────┘
             │
             ▼
┌──────────────────────────────────────┐
│ STEP 7: Response Delivery            │
├──────────────────────────────────────┤
│ • Display in chat interface          │
│ • Show loading indicator             │
│ • Stream response if long            │
│ • Add related suggestions            │
└────────────┬──────────────────────────┘
             │
             ▼
┌──────────────────────────────────────┐
│ STEP 8: Interaction Tracking         │
├──────────────────────────────────────┤
│ • Log user query                     │
│ • Track response usefulness          │
│ • Monitor follow-up questions        │
│ • Improve AI model                   │
└────────────────────────────────────┘
```

### Pseudocode

```python
async def process_assistant_query(query, user_id):
    # Step 1: Preprocess
    processed_query = preprocess_text(query)
    
    # Step 2: Classify intent
    intent = classify_intent(processed_query)
    if intent.confidence < 0.5:
        return {"type": "clarification", "message": "Could you please clarify?"}
    
    # Step 3: Extract entities
    entities = extract_entities(processed_query)
    location = entities.get('LOCATION') or get_user_location(user_id)
    
    # Step 4: Retrieve data
    context_data = {
        'places': fetch_places_by_intent(intent, location),
        'road_conditions': fetch_road_conditions(location),
        'weather': fetch_weather(location),
        'user_preferences': get_user_preferences(user_id),
        'recent_reports': fetch_recent_reports(location)
    }
    
    # Step 5: Generate response using GPT-4
    system_prompt = generate_system_prompt(intent, context_data)
    
    response = await openai.ChatCompletion.create(
        model="gpt-4",
        messages=[
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": query}
        ],
        temperature=0.7,
        max_tokens=500
    )
    
    assistant_response = response.choices[0].message.content
    
    # Step 6: Format response
    formatted_response = format_response(
        assistant_response,
        intent,
        context_data
    )
    
    # Step 7: Deliver
    result = {
        "type": "response",
        "content": formatted_response,
        "intent": intent.name,
        "suggestions": generate_follow_up_suggestions(intent)
    }
    
    # Step 8: Track interaction
    log_assistant_interaction({
        'user_id': user_id,
        'query': query,
        'intent': intent.name,
        'response_type': result['type'],
        'timestamp': NOW()
    })
    
    return result
```

---

## 7. Report Spam Detection Algorithm

### Machine Learning-Based Spam Detection

```python
def detect_spam_probability(report):
    """
    Calculate spam probability using multiple features
    Returns: score between 0 and 1 (higher = more likely spam)
    """
    
    features = extract_features(report)
    
    # Feature scores (each 0-1)
    text_spam_score = analyze_text_content(
        report.title,
        report.description
    )  # Checks for keywords, patterns
    
    image_spam_score = analyze_images(
        report.images
    )  # Checks image quality, metadata
    
    location_spam_score = check_location_validity(
        report.location,
        report.gps_accuracy
    )  # GPS accuracy, frequency at location
    
    user_history_score = analyze_user_history(
        report.user_id
    )  # Past reports, approval rate
    
    duplicate_score = check_for_duplicates(
        report
    )  # Similar reports in area/time
    
    pattern_score = check_for_patterns(
        report.user_id
    )  # Rapid submissions, similar titles
    
    # Weighted combination
    spam_score = (
        text_spam_score * 0.25 +
        image_spam_score * 0.20 +
        location_spam_score * 0.15 +
        user_history_score * 0.20 +
        duplicate_score * 0.15 +
        pattern_score * 0.05
    )
    
    return min(1.0, spam_score)
```

---

## Performance Metrics

### Algorithm Efficiency

| Algorithm | Complexity | Optimization |
|-----------|-----------|--------------|
| Report Verification | O(1) | AI pre-check filters 80% spam |
| XP Calculation | O(1) | Cached multiplier lookup |
| Nearby Places | O(log n) | MySQL spatial index optimization |
| Emergency Broadcast | O(n) | Batch processing, async queues |
| AI Query Processing | O(n) | LRU cache for common queries |
| Spam Detection | O(n) | Parallel feature extraction |

---

**Document Version:** 1.0
**Last Updated:** May 16, 2026
