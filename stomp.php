<?php

require_once 'api/api.php';
require_once 'stomp.civix.php';
require_once 'CRM/Core/Config.php';

use FuseSource\Stomp\Stomp;
use FuseSource\Stomp\Message\Map;

require_once 'CRM/Stomp/StompHelper.php';

/**
 * Implementation of hook_civicrm_config
 */
function stomp_civicrm_config(&$config) {
  _stomp_civix_civicrm_config($config);
  // TODO: Investigate why helper is created 3 times during the request
  $config->stomp = CRM_Stomp_StompHelper::singleton();
  // Default queue for data change messages
  $config->stomp->addQueue('data', 'CIVICRM-DATA');
  //Default queue for field labels/schema information messages
  $config->stomp->addQueue('schema', 'CIVICRM-SCHEMA');
  //Default queue for category tree messages
  $config->stomp->addQueue('category', 'CIVICRM-CATEGORY');
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
function stomp_civicrm_pre($op, $objectName, $objectId, $objectRef) {
  
}

// On every change of custom fields rebuild the tree and send it out
function stomp_civicrm_postProcess($formName, &$form) {
  if( $formName == 'CRM_Custom_Form_Field') {
      $customGroups = civicrm_api("CustomGroup", "get", array('version' => '3', 'extends' => 'Organization'));
      $fields = array();

      foreach ($customGroups['values'] as $cgid => $group) {
        $customFields = civicrm_api("CustomField", "get", array('version' => 3, 'custom_group_id' => $cgid));
        foreach ($customFields['values'] as $cfid => $values) {
          $fields['custom_' . $cfid] = $values['label'];
        }
      }

      $config = CRM_Core_Config::singleton();
      $config->stomp->connect();
      $queue = $config->stomp->getQueue('schema');
      $config->stomp->send( array("schema" => $fields), $queue);
  }
}


/**
 * Implementation of hook_civicrm_post
 *
 * On each database operation check if it's necessary to send STOMP message
 * and send it if necessary. ;-)
 */
function stomp_civicrm_post($op, $objectName, $objectId, $objectRef) {
  
  $config = CRM_Core_Config::singleton();

  $logText = strtr("Firing off hook \"@op\" on \"@name #@id\": ", array(
    '@op' => $op,
    '@id' => $objectId,
    '@name' => $objectName
      ));

  switch ($objectName) {
    case 'Individual':
      $config->stomp->log($logText . 'Nothing to do', 'DEBUG');
      break;
    case 'Organization':
      $config->stomp->connect();
      $queue = $config->stomp->getQueue('data');
      $config->stomp->log($logText . 'Connected, will send message to ' .
          $queue, 'DEBUG');
      //TODO: Identifying custom fields here for now, but move it out to be done once
      $customGroups = civicrm_api("CustomGroup", "get", array('version' => '3', 'extends' => 'Organization'));
      $returnFields = array();

      foreach ($customGroups['values'] as $cgid => $group) {
        $customFields = civicrm_api("CustomField", "get", array('version' => 3, 'custom_group_id' => $cgid));
        foreach ($customFields['values'] as $cfid => $values) {
          $returnFields['return.custom_' . $cfid] = 1;
        }
      }

      $params = array('version' => '3', 'id' => $objectId);
      $result = civicrm_api("Contact", "getsingle", $params);

      $params = array_merge($returnFields, $params);
      $custom_result = civicrm_api("Contact", "getsingle", $params);

      $result = array_merge($custom_result, $result);
      //$config->stomp->log( CRM_Core_Error::debug( $params ) );

      $config->stomp->send($result, $queue);
      break;
    default:
      $config->stomp->log($logText . 'Nothing to do', 'DEBUG');
  }

  return;
}