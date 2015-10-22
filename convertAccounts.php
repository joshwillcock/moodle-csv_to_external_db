<?php
// Created by Josh Willcock 2015
// Created for CL Consortium LTD
require_once('lib.php');
require_once('config.php');
$log = new debug_log;
$convert = new convert_users($log);
$convert->execute();
?>