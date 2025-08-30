<?php
/**
 * Cron Job Entry Point for Event Status Updates
 * 
 * This file should be called every 5-10 minutes by your cron job or task scheduler
 * 
 * Cron job example (runs every 5 minutes):
 * */5 * * * * /usr/bin/php /path/to/Bull_PVP/cron_event_status.php
 * 
 * Windows Task Scheduler:
 * Program: php.exe
 * Arguments: C:\MAMP\htdocs\Bull_PVP\cron_event_status.php
 * Start in: C:\MAMP\htdocs\Bull_PVP\
 * Trigger: Every 5 minutes
 */

// Change to the script directory
chdir(__DIR__);

// Include the actual update script
require_once 'scripts/update_event_status.php';

// Optional: You can also call the API endpoint instead
// $result = file_get_contents('http://localhost/Bull_PVP/api/update_event_status.php');
// echo $result;
?>