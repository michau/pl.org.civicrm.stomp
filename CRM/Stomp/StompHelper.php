<?php

use FuseSource\Stomp\Stomp;
use FuseSource\Stomp\Message\Map;
use FuseSource\Stomp\Exception\StompException;

require_once 'StompConfig.php';


/**
 * Provides simple facility for sending STOMP messages
 *
 * @author Michał Mach <michal@civicrm.org.pl>
 */
class CRM_Stomp_StompHelper {

  /**
   * We only need one instance of this object. So we use the singleton
   * pattern and cache the instance in this variable
   * @var object
   * @access private
   * @static
   */
  private static $_singleton = NULL;
  private $_connected = false;

  /**
   * STOMP server connection object
   * @var object
   * @access private
   */
  private $_stomp = NULL;

  /**
   * STOMP helper configuration
   * @var object
   * @access private
   */
  private $_config = NULL;  

  /**
   * Registry of queues needed
   * @var object
   * @access private
   */
  private $_queues = array();
  
  /**
   * Variables for measuring STOMP communication performance
   * @var timestamp
   * @access private
   */
  private $_helperLifetimeStart = null;
  private $_helperLifetimeEnd = null;

  /**
   * class constructor
   *
   */
  private function __construct() {
    $this->_config = new CRM_Stomp_StompConfig();
    $this->log("Creating helper object", 'DEBUG');
    $this->_helperLifetimeStart = microtime(true);    
    // FIXME: path solely for command line testing
    //$path = '../../packages/stomp-php/FuseSource/';
    $path = 'packages/stomp-php/FuseSource/';
    require_once $path . 'Stomp/ExceptionInterface.php';
    require_once $path . 'Stomp/Exception/StompException.php';
    require_once $path . 'Stomp/Stomp.php';
    require_once $path . 'Stomp/Frame.php';
    require_once $path . 'Stomp/Message.php';
    require_once $path . 'Stomp/Message/Bytes.php';
    require_once $path . 'Stomp/Message/Map.php';
  }

  public function connect() {    
    if (!$this->_connected) {
      $this->_stomp = new Stomp($this->_config->stompServerURL);
      try {
        $this->_stomp->connect();
        $this->_connected = true;
        $this->log("Initialised STOMP connection #" . $this->_stomp->getSessionId(), 'DEBUG');
      } catch (StompException $e) {
        CRM_Core_Error::fatal('Problem with STOMP connection initialisation! Caught exception: ' . $e->getMessage() . "\n");
      }
    }
  }

  /**
   * class destructor
   *
   */
  function __destruct() {
    if (isset($this->_stomp)) {
      $sessionId = $this->_stomp->getSessionId();
      $this->_stomp->disconnect();
      $this->log('Disconnected STOMP connection #' . $sessionId, 'DEBUG');
    }
    $this->_helperLifetimeEnd = microtime(true);
    $duration = $this->_helperLifetimeEnd - $this->_helperLifetimeStart;
    $this->log('Destroying: Helper lived for ' . $duration . ' seconds.', 'DEBUG');
  }

  /**
   * Static instance provider.
   *
   * Method providing static instance of Stomp provider
   */
  public static function singleton() {
    if (!isset(self::$_singleton)) {
      self::$_singleton = new CRM_Stomp_StompHelper();
    }
    return self::$_singleton;
  }

  private function _prepMessage($msg) {
    $header = array();
    $header['transformation'] = 'jms-map-json';
    $header['persistent'] = 'true';
    $mapMessage = new Map($msg, $header);
    return $mapMessage;
  }

  public function addQueue( $keyword, $mq_name ) {
    $this->_queues[$keyword] = '/queue/' . $mq_name;
  }
  
  public function getQueue( $keyword ) {
    if(!array_key_exists($keyword, $this->_queues)) {
      CRM_Core_Error::fatal('Trying to send to previously unregisterd queue.');
    } else {
      return $this->_queues[$keyword];
    }    
  }
  
  public function send($map, $queue) {

    // Start timer
    $time_start = microtime(true);
    
    $this->connect();
    $mapMessage = $this->_prepMessage($map);
    $this->_stomp->send($queue, $mapMessage);

    // End timer
    $time_end = microtime(true);
    $duration = $time_end - $time_start;

    $this->log('Sole send duration was ' . $duration . ' seconds.', 'DEBUG');
  }

  /**
   * Text file logging
   *
   */
  public function log($message = 'UNKNOWN', $level = 'DEBUG') {
    $text = strtr("@time - StompHelper - @level - @message\n", array(
      '@time' => date('Y-m-d H:i:s'),
      '@message' => $message,
      '@level' => $level
        ));
    file_put_contents($this->_config->logPath, $text, FILE_APPEND);
  }

}

// FIXME: Command line testing stuff - convenient for now
//require_once '/home/michau/Sandboxes/civicrm.drupal7/civicrm.config.php';
//$config = CRM_Core_Config::singleton();
//$testmsg = array( 'blah' => 'hello' );
//$stomp = CRM_Stomp_StompHelper::singleton();
//$stomp->send( $testmsg );
//var_dump($stomp);
?>
