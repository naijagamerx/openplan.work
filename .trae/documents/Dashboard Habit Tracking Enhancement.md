## Implementation Plan: Dashboard Habit Tracking Enhancement

### Phase 1: Data Model Enhancements
**File**: `api/habits.php` (modify) & Database (new collections)
- Add `targetDuration` field to habits collection (in minutes)
- Create `habit_timer_sessions` collection to track timer data:
  - id, habitId, startTime, endTime, duration, status (running/completed/manual), createdAt
- Add `linkedHabitId` field to tasks (for task-habit associations)
- Implement API endpoints:
  - POST `action=start_timer` / `action=stop_timer` / `action=manual_log`
  - GET `action=timer_stats` (for historical data)

### Phase 2: Habit Overview Section
**File**: `views/dashboard.php` (modify)
- Add Habit Overview card to existing stats grid (expand from 4 to 5 cards)
- Display: Total Habits, Active Today, Completed Today
- Add circular progress chart showing completion rate
- Include streak indicator with fire icon

### Phase 3: Habit Timer Integration
**File**: `views/dashboard.php` (modify - add new section below Recent Tasks)
- Create "Today's Habits with Timer" section (lg:col-span-2)
- For each habit display:
  - Habit name and category
  - Timer controls (Play/Pause/Stop buttons)
  - Live duration display (HH:MM:SS format)
  - Target duration progress bar
  - Manual time entry button (opens modal)
- Implement JavaScript timer system:
  - Real-time duration updates
  - Timer state persistence (localStorage + server)
  - Auto-save timer sessions on page unload
  - Smart suggestions based on historical averages

### Phase 4: Task-Habit Association
**File**: `views/dashboard.php` (modify - Recent Tasks section)
- Add visual indicators to task items:
  - Habit icon badge if task is linked to a habit
  - Color-coded border: Green (habit-based), Blue (regular), Orange (time-sensitive)
  - Tooltip showing linked habit name
- Update task item rendering to check `linkedHabitId` field

### Phase 5: UI/UX Enhancements
**Files**: `views/dashboard.php` (modify)
- Add tooltip library or custom tooltips for:
  - Timer controls explanation
  - Habit association indicators
  - Progress metrics
- Ensure responsive design:
  - Stack habit items on mobile
  - Collapse timer controls on small screens
  - Optimize progress bars for touch
- Add animations:
  - Timer progress animations
  - Habit completion celebrations
  - Smooth transitions

### Phase 6: Export Functionality
**File**: `api/export.php` (modify) or create new endpoint
- Add habit statistics export:
  - CSV format: habit name, completion rate, streak, avg. duration, total time
  - JSON format: Full habit data with timer sessions
  - PDF export option (using existing export patterns)
- Add "Export Habit Stats" button to dashboard Quick Actions

### Testing Strategy
1. Cross-browser timer accuracy (Chrome, Firefox, Safari, Edge)
2. Data persistence after page refresh/navigation
3. Timer state recovery (if left running, continue on reload)
4. Visualizations with 0, 1, 5, 10+ habits
5. Mobile responsiveness testing
6. Concurrent timer handling (multiple habits running)

### Key Implementation Details
- Use `localStorage` for immediate timer state backup
- Server-side validation for manual time entries
- Debounce API calls for timer updates (every 30s)
- Use `requestAnimationFrame` for smooth timer display
- Implement "resume detection" - if page reloaded with active timer, restore it