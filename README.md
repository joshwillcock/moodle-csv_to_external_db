# CSV Import for Moodle Users
Task to generate table and pull list of users from a CSV into an external database for Moodle.

###### How to use
1. Input your information in the config.php file
2. Setup an automated method to run the syncuser.php script (usually once a day).

The convertAccounts will require the mdl database details to be filled out, this will go through all users inside Moodle and change their auth type.
! Please note this may cause passwords to be reset after !
