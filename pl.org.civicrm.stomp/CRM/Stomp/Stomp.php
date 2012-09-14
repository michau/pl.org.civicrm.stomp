<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

use FuseSource\Stomp\Stomp;
use FuseSource\Stomp\Message\Map;
use FuseSource\Stomp\Exception\StompException;

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

    /**
     * STOMP server connection object
     * @var object
     * @access private
     */
    private $_stomp = NULL;

    /**
     * Path to store STOMP communication logs
     * @var string
     * @access private
     */
    private $_logPath = '/tmp/stomp.log';

    /**
     * The address of STOMP server
     * @var string
     * @access private
     */
    private $_stompServerURL = 'tcp://localhost:61613';

    /**
     * class constructor
     *
     */
    function __construct() {
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

        $this->log( "Initialising" );
        $this->_stomp = new Stomp($this->_stompServerURL);
        try {
            $this->_stomp->connect();
        } catch (StompException $e) {
            CRM_Core_Error::fatal('Problem with STOMP connection initialisation! Caught exception: ' . $e->getMessage() . "\n");
        }
    }

     /**
     * class destructor
     *
     */
    function __destruct() {
    }


    /**
     * Static instance provider.
     *
     * Method providing static instance of Stomp provider
     */
    static function &singleton() {
        if (!isset(self::$_singleton)) {
            self::$_singleton = new CRM_Stomp_StompHelper();
        }
        return self::$_singleton;
    }

    private function prepMessage( $msg ) {
        $header = array();
        $header['transformation'] = 'jms-map-json';
        $mapMessage = new Map($map, $header);
        return $mapMessage;
    }
    
    public function send( $map ) {
        
        $time_start = microtime();

        $mapMessage = $this->prepMessage( $map );
        $this->_stomp->send("/queue/civicrm", $mapMessage );
        
        $time_end = microtime();
        $d = $time_end - $time_start;
        list($usec, $sec) = explode(" ", $d);
        $duration = ((float)$usec + (float)$sec);
        
        $this->log( 'Sent message in ' . $duration . '! ', $op, $objectName, $objectId, $duration );
        
    }
    
   
    function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
    
    /**
     * Text file logging
     *
     */
    public function log($message = 'UNKNOWN', $op = 'UNKNOWN', $objectName = 'UNKNOWN', $objectId = 'UNKNOWN', $duration = 'UNKNOWN') {
        $text = strtr("@time - Performed \"@op\" on \"@name #@id\" with message: @msg\n", array(
            '@op' => $op,
            '@time' => date('Y-m-d H:i:s'),
            '@id' => $objectId,
            '@name' => $objectName,
            '@msg' => $message
                ));
                
        if( $duration !== 'UNKNOWN' ) {
            $text = $text . " " . "(Duration: " . $duration . " )";
        }

        file_put_contents($this->_logPath, $text, FILE_APPEND);
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
