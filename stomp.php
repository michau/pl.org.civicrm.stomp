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
 * sort desc two array by last element value
 */
function _stomp_cmp_custom_fields_array($a, $b)
{
  return strcmp(end($b), end($a));
}


/**
 * get contact custom values in cyklotron data format
 */
function _stomp_custom_value_get( $params ) {

  $values = array();
  $result = CRM_Core_BAO_CustomValueTable::getValues( $params );
  // skip other organization subtype custom values fields.
  if ($result['is_error'] == 0 ) {

    unset($result['is_error'], $result['entityID']);
    // Convert multi-value strings to arrays
    $sp = CRM_Core_DAO::VALUE_SEPARATOR;
    foreach ($result as $id => $value) {
      if (strpos($value, $sp) !== FALSE) {
        $value = explode($sp, trim($value, $sp));
      }

      $idArray = explode('_', $id);
      if ($idArray[0] != 'custom') {
        continue;
      }
      $fieldNumber = $idArray[1];
      $n = empty($idArray[2]) ? 0 : $idArray[2];
            
      if($n) {
        $values[$n]['custom_'.$fieldNumber] = $value;
      } else {
        $values['custom_'.$fieldNumber] = $value;
      }      
    }
    // sort array by last element value
    if($n) { usort($values,"_stomp_cmp_custom_fields_array"); }
  }
  return $values;
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
      $resultCustomData = array();

      foreach ($customGroups['values'] as $cgid => $group) {
        $customFields = civicrm_api("CustomField", "get", array('version' => 3, 'custom_group_id' => $cgid));
        $customFieldsParams = array(); 
        foreach ($customFields['values'] as $cfid => $values) {
           $customFieldsParams['custom_' . $cfid] = 1;
        }
        $customFieldsParams = array_merge($customFieldsParams, array('entityID' => $objectId) );
        $customFieldsValues = _stomp_custom_value_get( $customFieldsParams );
        if( !empty( $customFieldsValues ) ) {
          $resultCustomData['custom_group_'.$cgid] = $customFieldsValues;  
        }
      }
      
      $params = array( 'version' => 3, 
                       'id' => $objectId, );

      $paramsExtraData = array( 'api.website.get' => array(), 
                                'api.im.get' => array(), 
                                'api.phone.get' => array(), 
                                'api.address.get' => array(), );
                                                            
      $result = civicrm_api("Contact", "get", array_merge($paramsExtraData, $params ));
      if( empty($result['is_error']) && $result["values"][$result["id"]]) {
         $result = $result["values"][$result["id"]];
         foreach( $paramsExtraData as $key => $value ) {  
           if(empty($result[$key]['is_error']) && !empty($result[$key]['values']) ) {
             $result[$key] = $result[$key]['values'];
           } else {
             $result[$key] = array();
           }
         }
      } else {
        watchdog("stomp error", "contact get error:". print_r($result, true)."with params:". print_r($params, true));
        $result = civicrm_api("Contact", "getsingle", $params);
        $result = array_merge($result, $paramsExtraData);
      }
      $result = array_merge($result, $resultCustomData);
      // watchdog("stomp debug 1", print_r($result,true));

      $config->stomp->send($result, $queue);
      break;
    default:
      $config->stomp->log($logText . 'Nothing to do', 'DEBUG');
  }

  return;
}