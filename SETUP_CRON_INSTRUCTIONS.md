# Event Status Automation Setup

This document explains how to set up automatic event status updates that will change "accepting_bets" to "betting_closed" 1 hour before match start.

## Option 1: Cron Job (Linux/macOS)

Add this line to your crontab to run every 5 minutes:

```bash
# Edit crontab
crontab -e

# Add this line (adjust path as needed)
*/5 * * * * /usr/bin/php /path/to/Bull_PVP/cron_event_status.php
```

## Option 2: Windows Task Scheduler

1. Open Task Scheduler
2. Create Basic Task
3. Set trigger: "Daily" → "Repeat task every 5 minutes"
4. Action: "Start a program"
   - Program/script: `php.exe` (usually in `C:\xampp\php\php.exe` or `C:\mamp\bin\php\php8.x.x\php.exe`)
   - Arguments: `C:\MAMP\htdocs\Bull_PVP\cron_event_status.php`
   - Start in: `C:\MAMP\htdocs\Bull_PVP\`

## Option 3: Manual API Call

You can call the API endpoint manually or from other scripts:

```bash
curl http://localhost/Bull_PVP/api/update_event_status.php
```

Or visit in browser:
```
http://localhost/Bull_PVP/api/update_event_status.php
```

## Option 4: JavaScript Auto-Update (For Development)

Add this to your events page to auto-update every 2 minutes (not recommended for production):

```javascript
// Auto-update event statuses every 2 minutes
setInterval(function() {
    fetch('/Bull_PVP/api/update_event_status.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && (data.updates.betting_closed > 0 || data.updates.live > 0)) {
                // Reload page if any events were updated
                location.reload();
            }
        })
        .catch(error => console.log('Auto-update error:', error));
}, 120000); // 2 minutes
```

## Testing the System

1. Create an event with a match start time 1 hour in the future
2. Set its status to "accepting_bets"
3. Run the update script manually:
   ```bash
   php cron_event_status.php
   ```
4. Check that the event status changed to "betting_closed"

## Files Created:

- `/scripts/update_event_status.php` - Main update script
- `/api/update_event_status.php` - API endpoint version
- `/api/get_event_status.php` - Check individual event status
- `/cron_event_status.php` - Cron job entry point
- `/api/get_competitor_data.php` - Enhanced with real betting data

## What the System Does:

1. **Every 5 minutes** (or when triggered):
   - Finds events with status "accepting_bets"
   - Checks if match start time is within 1 hour
   - Updates status to "betting_closed"
   - Optionally updates "betting_closed" events to "live" when match starts

2. **When users open competitor modal**:
   - Checks current event status in real-time
   - Updates the page display if status has changed
   - Shows time remaining until betting closes
   - Enables/disables betting form accordingly

3. **Displays in modal**:
   - Time remaining until betting closes
   - Real betting statistics from database
   - Match history from completed events
   - Current odds based on betting distribution