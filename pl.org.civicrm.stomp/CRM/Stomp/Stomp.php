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
     * @return CRM_Core_Smarty
     * @access private
     */
    function __construct() {
        // FIXME: path solely for command line testing
        $path = '../../packages/stomp-php/FuseSource/';
        require_once $path . 'Stomp/ExceptionInterface.php';
        require_once $path . 'Stomp/Exception/StompException.php';
        require_once $path . 'Stomp/Stomp.php';
        require_once $path . 'Stomp/Frame.php';
        require_once $path . 'Stomp/Message.php';
        require_once $path . 'Stomp/Message/Bytes.php';
        require_once $path . 'Stomp/Message/Map.php';

        $this->_stomp = new Stomp($this->_stompServerURL);
        try {
            $this->_stomp->connect();
        } catch (StompException $e) {
            CRM_Core_Error::fatal('Problem on STOMP connection initialisation! Caught exception: ' . $e->getMessage() . "\n");
        }
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

    /**
     * Text file logging
     *
     */
    public function log($message = 'UNKNOWN', $op = 'UNKNOWN', $objectName = 'UNKNOWN', $objectId = 'UNKNOWN') {
        $text = strtr("@time - Performed \"@op\" on \"@name #@id\" with message: @msg\n", array(
            '@op' => $op,
            '@time' => date('Y-m-d H:i:s'),
            '@id' => $objectId,
            '@name' => $objectName,
            '@msg' => $message
                ));
        file_put_contents($this->_logPath, $text, FILE_APPEND);
    }

}

// FIXME: Command line testing stuff - convenient for now

require_once '/home/michau/Sandboxes/civicrm.drupal7/civicrm.config.php';
$config = CRM_Core_Config::singleton();

$stomp = CRM_Stomp_StompHelper::singleton();
var_dump($stomp);
?>
