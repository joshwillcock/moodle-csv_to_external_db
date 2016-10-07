<?php
// Created by Josh Willcock 2015
// Created for CL Consortium LTD
class debug_log {
    private $logFile;
    private $emailmessage;
    // Add message to log
    public function addMsg($msg) {
            $this->emailmessage .= $msg."\n";
        fwrite($this->logFile, $msg."\n");
    }
    // Creates and opens new file
    public function open() {
        global $CFG;
        $this->logFile = fopen($CFG->debuggingroot.date('Ymd_gi').'_log.txt', "w");
        return true;
    }
    // Closes new file
    public function close() {
        global $CFG;
        $result = fclose($this->logFile);
        if ($result==FALSE or $CFG->forcesendemail==TRUE) {
            $emailsent = mail($CFG->emailaddress, 'Cron Debug Log: Failed to save: '.$CFG->sitename, $this->emailmessage);
            if ($emailsent==false) {
                echo 'Unable to send email';
            }else{
            echo 'Failed to save log - email sent to '.$CFG->emailaddress.PHP_EOL;
            }
        }
        return true;
    }
}
// Changes users from manual to DB auth type inside moodle
class convert_users {
    public function __construct(debug_log $log) {
        $this->log = $log;
    }

    // Process to update users Master function
    public function execute() {
        global $CFG;
        if ($CFG->debugging) {
            $this->log->open();
        }
        $this->msg("Beginning authentication type update");
        $listOfUsers = $this->getUsers();
        $outcome = new stdClass();
        $outcome->success = 0;
        $outcome->failed = 0;
        foreach($listOfUsers as $user) {
            $response = $this->updateUser($user);
            if ($response) {
                $outcome->success++;
            } else {
                $outcome->failed++;
            }
        }
        unset($listOfUsers);
        $this->msg('Users Updated: '.$outcome->success);
        $this->msg('Users not found or failed to update '.$outcome->failed);
        if ($CFG->debugging) {
            $this->log->close();
        }
    }

    // Gets list of users from the external DB and puts them in an array
    private function getUsers() {
        global $CFG;
         $conn = new mysqli($CFG->host, $CFG->user, $CFG->password, $CFG->dbName);
        if ($conn->connect_error) {
            die("Connection Failed: ".$conn->connect_error);
        }
        $this->msg('Database Connection Established: Get Users');
        $result = $conn->query('SELECT username, email FROM `userimport`');
        // $resultArray = array();
        while ($row = $result->fetch_assoc()) {
            $userObj = new stdClass();
            $userObj->username = $row['username'];
            $userObj->email = $row['email'];
            $resultArray[] = $userObj;
        }
        $conn->close();
        return $resultArray;

    }
    // Updates an individual user returns outcome as bool
    private function updateUser($user) {
        global $CFG;
        $conn = new mysqli($CFG->mdlhost, $CFG->mdluser, $CFG->mdlpassword, $CFG->mdldbName);
        if ($conn->connect_error) {
            die("Connection Failed: ".$conn->connect_error);
        }
        $response = $conn->query('UPDATE mdl_user SET auth="db" WHERE username="'.$user->username.'" AND email="'.$user->email.'"');
        return $response;

    }
    private function msg($msg) {
        global $CFG;
        echo $msg.PHP_EOL;
        if ($CFG->debugging) {
            $this->log->addMsg($msg);
        }
    } // Close msg
}
class user_sync {
    private $log;
    public $rejectedEmailUsers = 0;
    // Class constructor creates logging object
    public function __construct(debug_log $log) {
        $this->log = $log;
    }
    // Finds the Zip - Unzip the file and finds the file returns filename
    private function findFile($zip = TRUE) {
        global $CFG;
        if($zip){
            $zipFileName = glob($CFG->address . $CFG->zipFileName);
            $this->msg('Looking for file: '.$CFG->address . $CFG->zipFileName.'');
            $zip = new ZipArchive;
            $result = $zip->open($zipFileName[0]);
            if ($result === TRUE) {
                $this->msg('Found file beginning unzip ');
                $zip->extractTo($CFG->address);
                $zip->close();
                $this->msg('Unzipped archive successfully');
            }else{
                $this->msg('Unable to locate zipped archive');
                exit;
            }
        }
        $filename = glob($CFG->address . $CFG->extractedFileName);
        $this->msg('Looking for file: '.$CFG->address.$CFG->extractedFileName);
        $this->msg('Found file: '.$filename[0]);
        return $filename[0];
    } // Close find file

    // Outputs debug message to screen
    private function msg($msg) {
        global $CFG;
        echo $msg.PHP_EOL;
        if ($CFG->debugging) {
            $this->log->addMsg($msg);
        }
    } // Close msg

    // Converts CSV to array
    private function csv_to_array($filename) {
        global $CFG;
        if (!file_exists($filename) || !is_readable($filename)) {
            return FALSE;
        }else{
            $header = NULL;
            $data = array();
            if (($handle = fopen($filename, 'r')) !== FALSE) {
                while (($row = fgetcsv($handle, 1000, $CFG->delimiter)) !== FALSE) {
                    if (!$header)
                        $header = $row;
                    else
                        $data[] = array_combine($header, $row);
                    }
                    fclose($handle);
                }
                $this->msg('Rows found: '.count($data));
                return $data;
        }
    } // Close CSV to array

    // Processes the data
    private function processData($data) {
        global $CFG;
        $firstuser = true;
        $outcome = new stdClass();
        $outcome->success=0;
        $outcome->failure=0;
        $conn = new mysqli($CFG->host, $CFG->user, $CFG->password, $CFG->dbName);
        if ($conn->connect_error) {
            die("Connection Failed: ".$conn->connect_error);
        }
        $this->msg('Database Connection Established: Process Data');
        foreach($data as $user) {
            if ($firstuser==true) {
                $getKeys = array_keys($user);
                $chkcol = mysqli_query($conn, "SELECT * FROM `userimport` LIMIT 1");
                $mycol = mysqli_fetch_array($chkcol, MYSQLI_NUM);
                foreach($getKeys as $key) {
                    if (!isset($mycol[$key])) {
                        if ($mycol[$key]=="username" or $mycol[$key]=="email") {
                            $conn->query("ALTER TABLE `userimport` ADD UNIQUE ".$key." varchar(100) ");
                        }else{
                            $conn->query("ALTER TABLE `userimport` ADD ".$key." varchar(100) ");
                        }
                    }
                }
                $indexraw = mysqli_query("SELECT COUNT(1) IndexIsThere FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema=DATABASE() AND table_name='userimport' AND index_name='userimport'");
                $index = mysqli_fetch_array($indexraw, MYSQLI_NUM);
                if ($index->IndexIsThere==0) {
                    mysqli_query("ALTER TABLE `userimport` ADD UNIQUE INDEX `Unique` (`username`, `email`)");
                }
                $firstuser = false;
                $this->msg("Dropped all users from Table");
                $conn->query("DELETE FROM `userimport`");
            }
            $queryBuild = 'INSERT INTO `userimport` (';
            $firstkey = true;
            $firstuserkey = true;
            foreach($getKeys as $userkey) {
                if ($firstkey) {
                    $queryBuild .=$userkey;
                    $firstkey = false;
                }else{
                    $queryBuild .=','.$userkey;
                }
            }
            $queryBuild .= ') VALUES (';
            foreach($getKeys as $userkey) {
                if ($firstuserkey) {
                    $queryBuild .='"'.$user[$userkey].'"';
                    $firstuserkey = false;
                }else{
                    $queryBuild .=',"'.$user[$userkey].'"';
                }
            }
            $user = $this->customize($user);
            $queryBuild .= ') ON DUPLICATE KEY UPDATE `username` = "'.$user['username'].'", `email`="'.$user['email'].'"';
            if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                $this->msg('User Skipped: Invalid Email Address: '.$user['username']);
                $this->rejectedEmailUsers++;
                $this->msg('Failed Email Address: '.$user['email']);
            }else{
                $result = $conn->query($queryBuild);
                if ($result == true) {
                    $this->msg('User '.$user['username'].' has been created/updated');
                    $outcome->success++;
                }else{
                    $this->msg('User '.$user['username'].' has failed to create/update');
                    $outcome->failure++;
                }
            }
        }
        $conn->close();
        return $outcome;
    } // Close Process Data

    // Master function which sets off individual functions
    public function execute() {
        global $CFG;
        if ($CFG->debugging) {
            $this->log->open();
        }
        $this->msg('Beginning User Sync For: '.$CFG->sitename);
        $file = $this->findFile();
        $data = $this->csv_to_array($file);
        if ($data === FALSE) {
            $this->msg('Cannot read file & extract data');
        }else{
            $outcome = $this->processData($data);
            $this->msg('Total Success: '.$outcome->success);
            $this->msg('Total Failure: '.$outcome->failure);
            $this->msg('Rejected Emails: '.$this->rejectedEmailUsers);
        }
        if ($CFG->debugging) {
            $this->log->close();
        }
    } // Close execute function
    public function customize($user){
        return $user;
    }
} // Close Class
?>
