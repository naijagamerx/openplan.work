## Implementation Plan: Water Drinking Reminder & Dashboard Habits Limit

### Part 1: Limit Habits on Dashboard
**File**: `views/dashboard.php`
- Modify "Today's Habits" section to show max 3 habits
- Add "View All" button/link to habits page when more habits exist
- Show message like "Showing 3 of X habits" when more exist

### Part 2: Water Drinking Reminder System

#### A. Data Model
**File**: `api/habits.php` (new collection)
- Create `water_tracker` collection:
  - id, date, glasses, goal (default 8), reminderInterval (minutes), createdAt, updatedAt

#### B. Water Tracker API Endpoints
**File**: `api/habits.php` (add actions)
- `GET action=get_water_tracker` - Get today's water intake
- `POST action=add_water_glass` - Increment glass count
- `POST action=set_water_goal` - Set daily goal
- `POST action=set_water_reminder` - Set reminder interval

#### C. Water Tracker UI on Dashboard
**File**: `views/dashboard.php` (new section)
Add "Water Intake Tracker" card:
- **Visual Display**:
  - Water droplet icons (8 by default) showing progress
  - Fill icons as user drinks water
  - Progress bar showing glasses/goal
  - Animated droplet filling effect
- **Controls**:
  - Quick "Add Glass" button (+1)
  - "Quick Add" dropdown (+2, +3 glasses)
  - "Set Goal" button (modal to change daily goal)
  - "Set Reminder" button (modal for interval)
- **Statistics**:
  - Today's intake
  - Percentage of goal
  - Weekly/monthly average

#### D. Browser Notifications
**File**: `views/dashboard.php` (JavaScript)
- Implement Notification API integration
- Request permission on first use
- Show desktop notification at reminder interval
- Notification includes sound/vibration support
- Snooze option in notification

#### E. Water Tracker History View
**File**: `views/water-tracker.php` (new file)
- Daily calendar showing water intake
- Statistics: daily average, best day, streak
- Charts: weekly/monthly trends
- Export functionality

#### F. Settings Integration
**File**: `views/settings.php` (modify)
- Add water reminder settings section:
  - Default daily goal
  - Reminder interval (30min, 1hr, 2hr options)
  - Enable/disable notifications
  - Reset time (when to reset daily count)

### Implementation Order
1. Create `water_tracker` data structure and API endpoints
2. Build dashboard water tracker widget with UI
3. Implement browser notification system
4. Add reminder JavaScript logic (check every minute)
5. Limit dashboard habits to 3 max
6. Create dedicated water tracker page
7. Update settings page with water options

### Key Features
- ✅ Visual water droplet icons showing progress
- ✅ Quick add buttons for easy tracking
- ✅ Desktop notifications for reminders
- ✅ Customizable daily goals
- ✅ History tracking and statistics
- ✅ Responsive design for mobile
- ✅ Data persistence between sessions