<?php
// Created by Josh Willcock 2015
// Created for CL Consortium LTD
    unset($CFG);
    global $CFG;
    date_default_timezone_set('UTC');
    $CFG = new stdClass();
    $CFG->sitename = 'DEBUGNAME';                            // Site name - for debugging purposes
    $CFG->address = '/home/mylocation/inbound/';       // Address of Zipped Archives
    $CFG->zipFileName = date('Ymd').'_*.zip';                // Name of Archive
    $CFG->extractedFileName = date('Ymd').'_*.csv';          // Name of CSV inside Archive
    $CFG->delimiter = ',';                                   // CSV Delimter (Usually ,)
    $CFG->host = '127.0.0.1';     // Database Host
    $CFG->dbName = 'DATABASENAME';                      // Database Name
    $CFG->user ='DATABASEUSERNAME';                                 // Database Username
    $CFG->password ='DATABASEPASSWORD';                              // Database Password
    $CFG->debugging = true;                                  // Create Logs
    $CFG->debuggingroot = '/home/mylocation/logs/';    // Location to write log
    $CFG->emailaddress = 'me@mydomain.com';     // Email address to send log to if write fails
    $CFG->forcesendemail = false;                            // Force Email To Send Even With Saved Log
?>