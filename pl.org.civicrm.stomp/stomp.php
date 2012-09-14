<?php

require_once 'stomp.civix.php';
use FuseSource\Stomp\Stomp;
use FuseSource\Stomp\Message\Map;

require_once 'CRM/Stomp/Stomp.php';

/**
 * Implementation of hook_civicrm_config
 */
function stomp_civicrm_config(&$config) {
  _stomp_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function stomp_civicrm_xmlMenu(&$files) {
  _stomp_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function stomp_civicrm_install() {
  return _stomp_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function stomp_civicrm_uninstall() {
  return _stomp_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function stomp_civicrm_enable() {
  return _stomp_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function stomp_civicrm_disable() {
  return _stomp_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function stomp_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _stomp_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function stomp_civicrm_managed(&$entities) {
  return _stomp_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_pre
 *
 * On each database operation to be started check if the queue is available and stop
 * the operation if it's not.
 */
function stomp_civicrm_pre( $op, $objectName, $objectId, $objectRef ) {
}


/**
 * Implementation of hook_civicrm_post
 *
 * On each database operation check if it's necessary to send STOMP message
 * and send it if necessary. ;-)
 */
function stomp_civicrm_post( $op, $objectName, $objectId, $objectRef ) {

    $stomp = CRM_Stomp_StompHelper::singleton(); 
    
    switch( $objectName ) {
        case 'Individual':
            break;
        default:
            $stomp->log('Nothing to do', $op, $objectName, $objectId);
    }
        
    if ( $objectName == 'Individual' && $op == 'edit' ) {           
        $stomp->send( $map );
    }
    
    return;
}