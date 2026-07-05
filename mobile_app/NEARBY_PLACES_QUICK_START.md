# 🗺️ Nearby Places Feature - Quick Start Guide

## 🎯 What's New?

The Nearby Places feature now works like **Google Maps** - showing places with photos, interactive maps, and detailed information.

---

## 🚀 How to Get Started

### 1. **Open the App**
```
HomeScreen → Tap "Nearby" tab at bottom
```

### 2. **See Nearby Places**
- Automatically loads places near your location
- Shows first image of each place
- Displays rating, distance, and photo count

### 3. **Filter by Category**
```
Tap any category chip (Hotel, Restaurant, Hospital, etc.)
Or tap "All" to see everything
```

### 4. **Adjust Search Area**
```
Use the radius slider to search:
- 1 km nearby (tight local search)
- 5 km (most common)
- 20 km (broader area search)
```

### 5. **Interact with Places**

#### On the Map:
- **Tap marker** → Select place (highlights on list)
- **Double-tap marker** → View full details
- **Pinch zoom** → Zoom in/out
- **Zoom buttons** → Quick zoom
- **My Location** → Center on you

#### On the List:
- **Tap place** → Highlight on map
- **Long-press place** → View full details
- **Tap "View" button** → View full details

### 6. **View Full Details**
```
PlaceDetailsScreen shows:
├── Photo carousel (swipe through images)
├── Place name & verified badge
├── Rating & reviews count
├── Address → Get Directions button
├── Phone → Call button
├── Email → Compose button
├── Website → Open in browser
├── Amenities grid
└── User reviews section
```

### 7. **Tap an Image**
```
See full-screen viewer with:
- Zoom (pinch or double-tap)
- Pan (drag)
- Next/Previous buttons
- Share button
- Open in browser
```

### 8. **Take Action**
```
From place details:
- Phone: Tap to call directly
- Email: Tap to compose email
- Website: Tap to open in browser
- Address: Tap to get directions in Google Maps
- Reviews: Swipe through user photos
- Share: Share place with friends
```

---

## 💡 Tips & Tricks

### Power User Tips:
1. **Double-tap map markers** for quick details (faster than list)
2. **Drag slider** to explore much wider areas
3. **Category first, then radius** for better filtering
4. **Zoom out map** to see all nearby places at once
5. **Long-press** in list for details (keyboard-friendly)

### Feature Combinations:
- Search radius **5 km** + Category **Restaurants** = Find nearby dining
- Zoom **out** on map + Tap **All** = Overview of area
- Adjust **radius** while browsing = Test different areas

### Organization Tips:
- Use **categories** when you know what you want
- Use **radius slider** when exploring what's nearby
- Use **map** to get spatial understanding
- Use **list** for detailed information

---

## 🎨 Visual Guide

### Screen Layout:

```
┌─────────────────────────────────┐
│  Nearby Places          [...]   │  ← Header
├─────────────────────────────────┤
│ [Hotel] [Rest.] [Hosp.] [⋯]   │  ← Categories
├─────────────────────────────────┤
│ Search radius: 5.0 km [====–]  │  ← Radius
├─────────────────────────────────┤
│     ┌───────────────────────┐   │
│     │                       │   │
│     │     INTERACTIVE       │   │  ← Map
│     │       MAP             │   │  (Tap/Double-tap)
│     │                       │   │
│     └───────────────────────┘   │
├─────────────────────────────────┤
│ Places Near You (within 5.0 km) │
├─────────────────────────────────┤
│ [IMG] Hotel ABC                 │
│       ⭐ 4.5 (128) • 2.3 km    │
│       [View] [3 photos]        │  ← Long-press for details
│                                 │
│ [IMG] Restaurant XYZ            │
│       ⭐ 4.8 (256) • 1.5 km    │
│       [View] [5 photos]         │
│                                 │
│ [IMG] Hospital 123              │
│       ⭐ 4.2 (89) • 3.1 km     │
│       [View] [2 photos]         │
└─────────────────────────────────┘
```

### Place Details Layout:

```
┌─────────────────────────────────┐
│  Place Name            [Share]  │
├─────────────────────────────────┤
│ ┌───────────────────────────┐   │
│ │  [Photo 1]   [2/10]   [⛶] │  ← Tap for full screen
│ │                           │   │
│ │    Swipe ← → for more    │   │
│ └───────────────────────────┘   │
├─────────────────────────────────┤
│ ⭐ 4.5 / 5.0                    │
│ (128 reviews)                   │
├─────────────────────────────────┤
│ About                           │
│ Detailed description of place.. │
├─────────────────────────────────┤
│ Contact & Location              │
│ 📍 Address [Directions] ➜       │
│ 📞 Phone [Call] ☎️              │
│ ✉️ Email [Compose] ✍️           │
│ 🌐 Website [Open] 🔗           │
├─────────────────────────────────┤
│ Amenities                       │
│ [WiFi] [AC] [Parking]          │
│ [Restroom] [Food Court]        │
├─────────────────────────────────┤
│ Reviews (3)                     │
│ User: John ⭐⭐⭐⭐⭐           │
│ "Great place!" [Photo]         │
│ May 23, 2026                    │
└─────────────────────────────────┘
```

---

## 🔍 Common Scenarios

### Scenario 1: "Find a nearby restaurant"
```
1. Open Nearby tab
2. Tap "Restaurant" category
3. Scroll list or tap marker
4. Tap "View" to see details
5. Tap "Call" to book reservation
```

### Scenario 2: "Show me what's around me"
```
1. Open Nearby tab (all places auto-load)
2. Zoom out map with buttons
3. Scroll through list
4. Tap marker for details
```

### Scenario 3: "I want to share a place with a friend"
```
1. Open Nearby tab
2. Find place (search or tap marker)
3. Tap "View" for details
4. Tap "Share" button
5. Choose messaging app
```

### Scenario 4: "Get directions to this place"
```
1. Open Nearby tab
2. Find place
3. Tap "View" for details
4. Tap address/location
5. Opens Google Maps with directions
```

### Scenario 5: "See photos of a place"
```
1. Open Nearby tab
2. Tap "View" on place
3. Swipe through photo carousel
4. Tap any photo for full-screen
5. Pinch to zoom, drag to pan
```

---

## ❓ FAQ

### Q: How often do places update?
**A:** Places update from server each time you adjust filters or open the tab. Data is cached for performance.

### Q: Can I search by name?
**A:** Currently, filter by category first, then scroll. Text search coming in future update.

### Q: How far can I search?
**A:** Up to 20 km radius in all directions from your location.

### Q: What if location permission is denied?
**A:** App defaults to Kathmandu center. Grant permission in Settings for accurate nearby places.

### Q: Can I see photos I haven't uploaded?
**A:** Yes, these are place photos uploaded by business owners and users.

### Q: How do I call a place?
**A:** Tap phone number in details screen - your phone will open dialer app.

### Q: Can I navigate to a place?
**A:** Yes, tap address in details - opens Google Maps with directions.

### Q: Are these real places?
**A:** Yes, they're from the Nepal Smart Travel database and may include verified business information.

---

## 🎓 Understanding the UI

### Map Markers Explained:
```
Regular marker:     🔵 = Not selected place
Selected marker:    🔵 (larger + glow) = Currently selected
User location:      🔴 = Your current position

Color meanings:
🟦 Blue = Accommodation/Hotel
🟥 Red = Restaurant/Food
🟩 Green = Hospital/Medical
🟨 Yellow = Transport/Bus
🟪 Purple = Activity/Adventure
🟧 Orange = Other/General places
```

### Status Indicators:
```
⭐ Stars = Rating (0-5)
(123) = Number of reviews
"2.3 km" = Distance from you
✓ Checkmark = Verified place
[3 photos] = Number of available photos
```

---

## ⚙️ Settings & Preferences

### Location Settings:
Go to **Settings** → **Privacy** → **Location**
- Enable location for accurate nearby places
- Allow "Always" for background location updates

### App Permissions:
Required permissions:
- ✅ Location (for GPS)
- ✅ Internet (for API calls)
- ✅ Camera (for uploading reviews - optional)
- ✅ Gallery (for review photos - optional)

---

## 🆘 Troubleshooting

### Issue: Places not loading
**Solution:** 
1. Check internet connection
2. Grant location permission
3. Refresh by adjusting radius slider
4. Restart app

### Issue: Map not showing
**Solution:**
1. Wait for map tiles to load (first time)
2. Check zoom level
3. Try zooming out
4. Restart app

### Issue: Photos not loading
**Solution:**
1. Check internet connection
2. Wait for image to load
3. Try refreshing place details
4. Check if image URL is valid

### Issue: Location stuck in wrong place
**Solution:**
1. Go to Settings → Privacy → Location
2. Disable and re-enable location
3. Toggle GPS/device location services
4. Restart app

### Issue: Phone/Email/Website not working
**Solution:**
1. Ensure app has necessary permissions
2. Have phone app, email app, or browser installed
3. Check internet connection for website
4. Try again with strong signal

---

## 📱 Mobile-Specific Tips

### On Phone:
- Swipe from left edge to go back
- Double-tap image for quick zoom
- Use system back button to exit details

### On Tablet:
- Landscape mode shows map + list side-by-side
- Larger touch targets for markers
- More comfortable for browsing

### Battery Saving:
- Turn off location when not using nearby search
- Disable background location updates
- Close app when not needed

---

## 🌟 Pro Features (Coming Soon)

- Saved favorites ⭐
- Search by name 🔍
- Advanced filters 🔧
- Booking integration 📅
- Offline maps 📡
- AR place view 📸

---

## 📞 Support

For issues or feedback:
1. Check this guide first
2. Restart the app
3. Clear app cache
4. Contact support team
5. Submit bug report

---

**Happy exploring! 🗺️**

Version: 1.0  
Last Updated: May 25, 2026  
Made with ❤️ for Nepal Smart Travel
