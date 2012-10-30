#!/usr/bin/env php
<?php

// bootstrap the environment and run the processor
session_start();
require_once '../../civicrm.drupal7/civicrm.config.php';
require_once 'CRM/Core/Config.php';
$config = CRM_Core_Config::singleton();

CRM_Utils_System::authenticateScript(TRUE, 'root' );

