### 1. Backend Integration
- **New API**: `api/habits.php` for CRUD and completion logging.
- **Data**: Uses `Database.php` for `habits.json.enc` and `habit_completions.json.enc`.
- **AI**: Adds `suggest_habits` to `AIHelper.php` and `api/ai.php`.

### 2. Frontend Integration
- **Routing**: `index.php` will serve `views/habits.php` via `?page=habits`.
- **Navigation**: Add link to `views/partials/sidebar.php`.
- **UI**: 
    - `views/habits.php`: Main dashboard with daily checklist and calendar.
    - `views/habit-form.php`: Modal form for habit management.
- **Logic**: `app.js` will handle API calls, calendar rendering, and periodic reminder checks.

### 3. Features
- **Manual/AI Toggle**: In the habit form, users can generate habits via AI or enter them manually.
- **Calendar**: Visual streak tracking using a custom CSS Grid implementation.
- **Reminders**: In-app toast notifications triggered by a background timer in `app.js`.