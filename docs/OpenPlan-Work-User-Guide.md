# OpenPlan Work - Complete User Documentation

## Version 1.0.0

---

## Table of Contents

1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Dashboard & Navigation](#dashboard--navigation)
4. [Task Management](#task-management)
5. [Project Management](#project-management)
6. [Client Management (CRM)](#client-management-crm)
7. [Invoicing](#invoicing)
8. [Finance Tracking](#finance-tracking)
9. [Inventory Management](#inventory-management)
10. [Notes](#notes)
11. [Habit Tracker](#habit-tracker)
12. [Water Tracker](#water-tracker)
13. [Pomodoro Timer](#pomodoro-timer)
14. [AI Assistant](#ai-assistant)
15. [Knowledge Base](#knowledge-base)
16. [Settings & Configuration](#settings--configuration)
17. [Backup & Data Management](#backup--data-management)
18. [Security & Privacy](#security--privacy)
19. [API Reference](#api-reference)
20. [Troubleshooting](#troubleshooting)

---

## Introduction

**OpenPlan Work** is a comprehensive, encrypted workspace designed for teams and solo builders. It combines task management, project tracking, CRM, invoicing, finance management, and AI-powered features in a single, secure application.

### Key Features

- **Task & Project Management**: Organize work with priorities, deadlines, and Kanban boards
- **CRM**: Manage client contacts and relationships
- **Invoicing**: Create professional invoices with multi-currency support
- **Finance Tracking**: Monitor revenue, expenses, and financial health
- **Inventory Management**: Track stock levels and product information
- **AI Integration**: Autonomous AI assistant for task generation and automation
- **Encrypted Storage**: Military-grade AES-256-GCM encryption for all data
- **MCP Support**: Native Model Context Protocol for AI tool integration

### System Requirements

- PHP 8.0 or newer
- Extensions: json, mbstring, curl, openssl
- Modern web browser (Chrome, Firefox, Safari, Edge)

---

## Getting Started

### First-Time Setup

1. **Navigate to the Setup Page**
   - Open your browser and go to `/?page=setup`
   - This will guide you through creating your admin account

2. **Create Admin Account**
   - Enter your email address
   - Create a secure password
   - Set a **Master Password** for data encryption
   - **Important**: Save your master password securely - it cannot be recovered

3. **Login**
   - Use your email and password to log in
   - Enter your master password to decrypt your data

### Daily Login

Each time you access OpenPlan Work:
1. Enter your email and password
2. Enter your master password to decrypt your workspace
3. Your session lasts 1 hour (configurable in settings)

---

## Dashboard & Navigation

### Main Dashboard

The dashboard provides an overview of your workspace:

- **Quick Stats**: Tasks due today, active projects, recent activity
- **Backup Reminders**: Alerts when backups are needed
- **Quick Actions**: Create tasks, projects, or invoices
- **Recent Items**: Recently accessed notes, tasks, and projects

### Navigation Structure

**Top Navigation Bar:**
- Home / Dashboard
- Tasks
- Projects
- Clients
- Invoices
- Finance
- Inventory
- Notes
- Habits
- AI Assistant
- Settings

**Mobile Navigation:**
- Access via hamburger menu (three horizontal lines)
- Optimized for touch interaction
- All features available on mobile

---

## Task Management

### Creating Tasks

1. Click **"New Task"** button or go to Tasks page
2. Enter task title (required)
3. Optional: Add description, due date, priority, estimated time
4. Link to a project (optional)
5. Click **"Create Task"**

### Task Priorities

- **Urgent**: Critical tasks requiring immediate attention
- **High**: Important tasks with near-term deadlines
- **Medium**: Standard priority tasks
- **Low**: Tasks that can be deferred

### Task Status

- **Backlog**: Not yet started
- **Todo**: Ready to work on
- **In Progress**: Currently being worked on
- **Review**: Completed, awaiting review
- **Done**: Fully completed

### Subtasks

Break down complex tasks:
1. Open a task detail view
2. Click **"Add Subtask"**
3. Enter subtask title
4. Mark subtasks complete as you finish them

### Time Tracking

Track time spent on tasks:
1. Open task detail view
2. Click **"Start Timer"** to begin tracking
3. Click **"Stop Timer"** when done
4. View total time in task statistics

### Task Templates

Save tasks as templates for reuse:
1. Create a task with your standard structure
2. Click **"Save as Template"**
3. Use template when creating new tasks

---

## Project Management

### Creating Projects

1. Go to **Projects** page
2. Click **"Create Project"**
3. Enter project name (required)
4. Optional: Add description, link client, set status, choose color
5. Click **"Create"**

### Project Status

- **Active**: Currently being worked on
- **Completed**: Finished successfully
- **On Hold**: Paused temporarily
- **Cancelled**: No longer proceeding

### Project Tasks

Tasks are embedded within projects:
- View all project tasks on the project detail page
- Kanban board view for visual task management
- Progress tracking based on task completion

### Kanban Board

Visual task management:
1. Open a project
2. Click **"Kanban Board"** tab
3. Drag tasks between columns (Backlog → Todo → In Progress → Review → Done)
4. Tasks automatically update status when moved

### Project Colors

Assign colors to projects for visual organization:
- Choose from preset colors or enter custom hex code
- Colors appear in project lists and task views

---

## Client Management (CRM)

### Adding Clients

1. Go to **Clients** page
2. Click **"Add Client"**
3. Enter client name (required)
4. Optional: Add email, phone, company, address
5. Click **"Create"**

### Client Details

View comprehensive client information:
- Contact details
- Linked projects
- Invoices history
- Notes and communication

### Linking Clients

Link clients to projects and invoices:
- When creating a project, select client from dropdown
- When creating an invoice, select client
- All linked items appear in client detail view

---

## Invoicing

### Creating Invoices

1. Go to **Invoices** page
2. Click **"Create Invoice"**
3. Select client (required)
4. Optional: Link to project
5. Add line items:
   - Description
   - Quantity
   - Unit price
6. System calculates subtotal, tax, and total automatically
7. Click **"Create Invoice"**

### Invoice Status

- **Draft**: Being prepared
- **Sent**: Delivered to client
- **Paid**: Payment received
- **Overdue**: Past due date, unpaid
- **Cancelled**: Voided

### Advanced Invoices

Create professional invoices with:
- Custom company header (logo, address, contact)
- Contract dates
- Multiple line items with dates and references
- Custom payment details
- Footer text (terms and conditions)

### Invoice Actions

- **Preview**: See how invoice looks before sending
- **Download**: Generate PDF
- **Send**: Mark as sent and notify client
- **Mark Paid**: Record payment received
- **Duplicate**: Copy for similar invoices
- **Delete**: Remove invoice (only draft status)

### Multi-Currency Support

Create invoices in:
- USD (US Dollar)
- EUR (Euro)
- GBP (British Pound)
- ZAR (South African Rand)

---

## Finance Tracking

### Recording Transactions

1. Go to **Finance** page
2. Click **"Add Transaction"**
3. Select type: **Revenue** or **Expense**
4. Enter amount and description
5. Optional: Select category, link to client/project
6. Click **"Add"**

### Transaction Categories

Organize finances with categories:
- Revenue: Sales, Services, Other Income
- Expenses: Supplies, Services, Utilities, Rent, etc.

### Financial Summary

View financial health at a glance:
- Total income
- Total expenses
- Net balance
- Filter by date range

---

## Inventory Management

### Adding Items

1. Go to **Inventory** page
2. Click **"Add Item"**
3. Enter item name (required)
4. Optional: SKU, category, description, quantity
5. Enter cost price and unit price for margin tracking
6. Set reorder point for low-stock alerts
7. Click **"Add Item"**

### Stock Management

Update stock levels:
- **Direct Update**: Set exact quantity
- **Adjust**: Add or subtract from current stock
- **Low Stock Alerts**: Visual indicators when below reorder point

### Inventory Categories

Organize items with categories:
- Create custom categories
- Filter inventory by category
- Category-based reports

---

## Notes

### Creating Notes

1. Go to **Notes** page
2. Three-pane interface: Folders | Note List | Editor
3. Click **"New Note"** or **"+"** button
4. Enter title
5. Write content in the editor
6. Auto-saves as you type

### Note Features

- **Rich Text Editing**: Format text with bold, italic, lists
- **Tags**: Organize notes with custom tags
- **Colors**: Set background color for visual organization
- **Pinning**: Pin important notes to the top
- **Favorites**: Mark frequently accessed notes
- **Auto-save**: Changes saved automatically

### Tag Management

Organize notes with tags:
1. Click **"Add Tag"** in note editor
2. Select existing tag or create new one
3. Click tag in sidebar to filter notes
4. Manage all tags via **"Manage Tags"** button

### AI Note Editing

Use AI to improve notes:
1. Open a note
2. Click **AI Edit** button
3. Choose action:
   - **Rewrite**: Improve clarity and tone
   - **Improve**: Fix grammar and readability
   - **Expand**: Add more detail
   - **Summarize**: Create bullet points

### Linking Notes

Link notes to tasks and projects:
- When viewing a task/project, create linked note
- Linked notes appear in entity detail view

---

## Habit Tracker

### Creating Habits

1. Go to **Habits** page
2. Click **"New Habit"**
3. Enter habit name (required)
4. Optional: Set category, frequency (daily/weekly/monthly)
5. Set reminder time
6. Set target duration (for timed habits)
7. Click **"Create"**

### Tracking Habits

Mark habits complete:
- Click **checkbox** to mark complete for today
- View completion history in habit detail
- Visual calendar shows completion streaks

### Habit Timer

Time your habit sessions:
1. Open habit detail
2. Click **"Start Timer"**
3. Timer runs in background
4. Click **"Stop"** when done
5. Time automatically recorded

### Statistics

View habit performance:
- Completion streak
- Total completions
- Completion rate
- Weekly/monthly trends

---

## Water Tracker

### Daily Tracking

1. Go to **Water Tracker** page
2. See today's progress toward goal
3. Click **"+"** button to log glasses
4. Click **"-"** to remove if logged incorrectly

### Setting Goals

Set daily water intake goal:
1. Click **"Set Goal"**
2. Enter number of glasses
3. Goal applies to all future days

### AI Hydration Plan

Generate personalized hydration plan:
1. Click **"Generate Plan"**
2. Enter optional details:
   - Weight
   - Activity level (sedentary/moderate/active/athlete)
   - Climate (temperate/hot/humid/cold)
   - Wake/sleep times
3. AI generates recommended daily intake
4. Follow schedule with reminders throughout day

### History

View past water intake:
- Calendar view of daily completion
- Weekly and monthly summaries
- Trends and patterns

---

## Pomodoro Timer

### Using the Timer

1. Go to **Pomodoro** page
2. Set focus duration (default: 25 minutes)
3. Set break duration (default: 5 minutes)
4. Click **"Start"** to begin focus session

### Timer Controls

- **Start**: Begin timer
- **Pause**: Pause current session
- **Reset**: Reset to initial time
- **Skip**: Skip to next phase (focus/break)

### Focus Music

Play background music during sessions:
1. Select track from **Focus Music** dropdown
2. Check **"Play while running"** for auto-play
3. Check **"Loop"** to repeat track
4. Adjust volume with slider

### Custom Durations

Set custom timer lengths:
- Click **"Custom"** button
- Set focus and break durations
- Save as default for future sessions

### Session Tracking

View productivity stats:
- Completed sessions today
- Total focus time
- Session history

---

## AI Assistant

### Chat Interface

1. Go to **AI Assistant** page
2. Type your request in natural language
3. AI responds and can execute actions

### Example Requests

**Task Management:**
- "Create a task called 'Review Q3 Report' due tomorrow, high priority"
- "Generate 5 tasks for a website redesign project"
- "Create a project called 'Marketing Campaign' linked to client ABC Corp"

**Invoicing:**
- "Create an invoice for Client XYZ for $500"
- "Generate an invoice for project 'Website Design' with 3 line items"

**Habits:**
- "Create a daily habit for reading 30 minutes"
- "Generate a hydration plan for someone who weighs 70kg and exercises daily"

**Notes:**
- "Create a note titled 'Meeting Notes' with today's date"
- "Summarize my note about project requirements"

### Agent Mode

Enable autonomous action execution:
1. Toggle **"Agent Mode"** on
2. AI can directly create, update, and delete items
3. Review actions before confirming
4. See detailed action summaries

### Model Selection

Choose AI provider:
- **Groq**: Fast, cost-effective (Llama 3.3, Mixtral)
- **OpenRouter**: Access to multiple models (Claude, GPT-4, Llama)

Configure API keys in **Settings → AI Configuration**

### Clear History

Reset conversation:
- Click **"Clear History"** to start fresh
- Previous context is forgotten
- Useful when switching topics

---

## Knowledge Base

### Folder Structure

Organize files in folders:
1. Go to **Knowledge Base** page
2. Click **"New Folder"** to create folder
3. Enter folder name
4. Navigate into folder

### Uploading Files

Add files to Knowledge Base:
1. Navigate to folder
2. Click **"Upload"** or drag files
3. Supported formats: Markdown (.md), XML (.xml), images
4. Files are stored encrypted

### Batch Upload

Upload multiple files at once:
1. Click **"Batch Upload"**
2. Select multiple files
3. All files upload simultaneously

### File Actions

- **View**: Open file content
- **Download**: Save to computer
- **Move**: Change folder
- **Rename**: Change filename
- **Delete**: Remove file

### Search

Find files quickly:
- Use search bar to find by filename
- Search across all folders
- Results show file location

---

## Settings & Configuration

### User Settings

Access via **Settings** page:
- Profile information
- Email and password
- Master password (view only)
- Notification preferences

### Business Settings

Configure business information:
- Business name
- Email address
- Phone number
- Physical address
- Currency default
- Tax rate

### AI Configuration

Set up AI providers:
- Groq API key
- OpenRouter API key
- Default model selection
- Response preferences

### MCP Configuration

Configure MCP access:
- Generate API token
- View connection details
- Revoke access if needed
- Use with Claude Code, Cursor, etc.

### Appearance

Customize interface:
- Theme selection (Light/Dark)
- Font size preferences
- Date format
- Time format

---

## Backup & Data Management

### Creating Backups

1. Go to **Settings → Backup**
2. Click **"Create Backup"**
3. System creates encrypted backup file
4. Download backup to safe location

### Restoring Backups

1. Go to **Settings → Backup**
2. Click **"Restore"** on desired backup
3. Confirm restoration
4. System restores data from backup

### Export Data

Export for migration:
1. Go to **Data Management** (or Import/Export page)
2. Choose format: JSON or ZIP
3. Download export file
4. Import into another OpenPlan instance

### Import Data

Import from another system:
1. Go to **Data Management**
2. Select import file (JSON or ZIP)
3. Review items before importing
4. Confirm import

### Automated Backups

Configure automatic backups:
- Set backup frequency (daily, weekly)
- Configure retention (number of backups to keep)
- System creates backups automatically

---

## Security & Privacy

### Data Encryption

All data is encrypted using AES-256-GCM:
- Encryption key derived from your master password
- Data encrypted before storage
- Even database administrators cannot read your data

### Master Password

Critical security element:
- Required to decrypt your data
- Never stored on server
- Cannot be reset or recovered
- **Keep it safe and backed up**

### Session Security

- Sessions expire after 1 hour of inactivity
- HTTP-only cookies prevent JavaScript access
- CSRF protection on all forms
- Secure headers prevent XSS attacks

### Password Requirements

User passwords must:
- Be at least 8 characters
- Contain uppercase and lowercase letters
- Include numbers
- Include special characters

### Audit Logging

Track all activities:
- View in **Settings → Audit Logs**
- See who did what and when
- Export logs for compliance
- Filter by action type and date

---

## API Reference

### Authentication

All API endpoints require authentication via session cookie.

### Response Format

```json
{
    "success": true,
    "data": {},
    "message": "Operation completed",
    "timestamp": "2024-12-30T10:00:00Z"
}
```

### Common Endpoints

**Tasks**
- `GET api/tasks.php` - List tasks
- `POST api/tasks.php?action=add` - Create task
- `PUT api/tasks.php?id={id}` - Update task
- `DELETE api/tasks.php?id={id}` - Delete task

**Projects**
- `GET api/projects.php?action=list` - List projects
- `POST api/projects.php?action=create` - Create project
- `PUT api/projects.php?id={id}` - Update project
- `DELETE api/projects.php?id={id}` - Delete project

**Clients**
- `GET api/clients.php?action=list` - List clients
- `POST api/clients.php?action=create` - Create client
- `PUT api/clients.php?id={id}` - Update client
- `DELETE api/clients.php?id={id}` - Delete client

**Invoices**
- `GET api/invoices.php?action=list` - List invoices
- `POST api/invoices.php?action=create` - Create invoice
- `PUT api/invoices.php?id={id}` - Update invoice
- `DELETE api/invoices.php?id={id}` - Delete invoice

**Notes**
- `GET api/notes.php?action=list` - List notes
- `POST api/notes.php?action=create` - Create note
- `PUT api/notes.php?id={id}` - Update note
- `DELETE api/notes.php?id={id}` - Delete note

**Habits**
- `GET api/habits.php?action=list` - List habits
- `POST api/habits.php?action=create` - Create habit
- `POST api/habits.php?action=complete` - Mark complete
- `PUT api/habits.php?id={id}` - Update habit

**Export/Import**
- `GET api/export.php?format=json` - Export JSON
- `GET api/export.php?format=zip` - Export ZIP
- `POST api/export.php?action=import` - Import data

### CSRF Protection

All POST, PUT, DELETE requests require CSRF token:
```javascript
{
    "csrf_token": "your_token_here",
    // other data
}
```

---

## Troubleshooting

### Cannot Login

**Problem**: "Invalid credentials" error
- Check email and password spelling
- Verify caps lock is off
- Try password reset if forgotten

### Master Password Not Working

**Problem**: "Decryption failed" error
- Double-check master password entry
- Verify you're using correct master password
- **Important**: Master password cannot be recovered

### Session Expired

**Problem**: Redirected to login unexpectedly
- Sessions expire after 1 hour
- Simply log in again
- Check "Remember me" for 30-day session

### Data Not Saving

**Problem**: Changes not persisting
- Check CSRF token is included
- Verify stable internet connection
- Check browser console for errors
- Clear browser cache and retry

### AI Not Responding

**Problem**: AI assistant shows errors
- Verify API key is configured
- Check API key validity
- Verify internet connection
- Check rate limits not exceeded

### Backup Fails

**Problem**: Cannot create backup
- Verify data directory is writable
- Check disk space available
- Try manual export as alternative

### Slow Performance

**Problem**: Pages loading slowly
- Clear browser cache
- Check internet connection
- Large datasets may take time to decrypt
- Consider archiving old data

### Mobile Issues

**Problem**: Layout issues on mobile
- Use mobile-optimized version
- Access via `mobile/index.php`
- Ensure browser is updated
- Try desktop mode if needed

### Contact Support

For issues not resolved:
- Check system status at `api/health.php`
- Review error logs in browser console
- Contact administrator with specific error messages

---

## Keyboard Shortcuts

### Global Shortcuts

- `Ctrl/Cmd + N` - Create new note
- `Ctrl/Cmd + I` - Open AI edit modal
- `Escape` - Close modals, deselect items

### Task Management

- `Enter` - Save task when editing
- `Escape` - Cancel editing

### Notes Editor

- `Ctrl/Cmd + B` - Bold text
- `Ctrl/Cmd + I` - Italic text
- `Tab` - Indent list item

---

**OpenPlan Work v1.0.0**

*Built with security and productivity in mind*
