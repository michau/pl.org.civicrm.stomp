<?php

/**
 * Provides configuration for StompHelper
 *
 * @author Michał Mach <michal@civicrm.org.pl>
 */
class CRM_Stomp_StompConfig {

  /**
   * Path to store STOMP communication logs
   * @var string
   * @access private
   */
  public $logPath = '/tmp/stomp.log';

  /**
   * The address of STOMP server
   * @var string
   * @access private
   */
  public $stompServerURL = 'tcp://localhost:61613';  

  /**
   * The username of STOMP server
   * @var string
   * @access private
   */
  public $stompUser = null;

  /**
   * The password of STOMP server
   * @var string
   * @access private
   */  
  public $stompPassword = null;
}