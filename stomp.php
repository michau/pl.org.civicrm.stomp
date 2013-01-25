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
 *  Install options
 */
function _stomp_option_config_install() {


  civicrm_initialize();   
  require_once 'CRM/Core/BAO/OptionGroup.php';
  require_once 'CRM/Core/BAO/OptionValue.php';
       
  $params = array( "title" => "pl.org.civicrm.stomp.schema.labels",
                   "name" => "pl.org.civicrm.stomp.schema.labels",
                   "is_active" => 1 );
  $ids =  array();
  $optionGroup = CRM_Core_BAO_OptionGroup::add($params, $ids);
  
  if($optionGroup) {
  
   $fields = array( );
   $result = civicrm_api( "Contact", "getfields", array("version" => 3, "action" => "get" ));
   $fields = array_merge($fields, $result['values']);   
   $result = civicrm_api( "Address", "getfields", array("version" => 3, "action" => "get" ));   
   $fields = array_merge($fields, $result['values']);   
   $result = civicrm_api( "Phone", "getfields", array("version" => 3,   "action" => "get" ));   
   $fields = array_merge($fields, $result['values']);   
   $result = civicrm_api( "Website", "getfields", array("version" => 3, "action" => "get" ));   
   $fields = array_merge($fields, $result['values']);   
   $result = civicrm_api( "Im", "getfields", array("version" => 3, "action" => "get" ));
   $fields = array_merge($fields, $result['values']);
   $fields = array_merge($fields, array( "api.website.get" => array(), 
                                         "api.im.get" => array(), 
                                         "api.phone.get" => array(), 
                                         "api.address.get" => array(), 
                                         "api.relationship.get" => array(),));     
   foreach( $fields as $key => $value ) {
      
      $info = explode('_', $key);      
      if($info[0] != "custom") {
         $params = array( "option_group_id" => $optionGroup->id, 
                          "label" => !empty($value["title"]) ? $value["title"] : "$key",
                          "value" => "$key",
                          "is_active" => 1, );
         $option = CRM_Core_BAO_OptionValue::add($params, $ids);     
      }
   } 
  }

}

/**
 *  uninstall options
 */
function _stomp_option_config_uninstall() {

  civicrm_initialize();      
  require_once 'CRM/Core/BAO/OptionGroup.php';
  require_once 'CRM/Core/BAO/OptionValue.php';

  $defaults = array();
  $params = array( "name" => "pl.org.civicrm.stomp.schema.labels" );
  $option_group = CRM_Core_BAO_OptionGroup::retrieve( $params, $defaults);

  if($option_group) {
    $options = CRM_Core_BAO_OptionValue::getOptionValuesArray($option_group->id);
    foreach( $options as $i => $option) {
        CRM_Core_BAO_OptionValue::del($option["id"]);
    }
  }
  CRM_Core_BAO_OptionGroup::del( $option_group->id);  

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
  _stomp_option_config_install();  
  return _stomp_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function stomp_civicrm_uninstall() {
  _stomp_option_config_uninstall();
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
  
  if( $formName == 'CRM_Custom_Form_Field' || $formName == 'CRM_Admin_Form_OptionValue') {
    stomp_post_schema();  
  }
}

/**
 * Send schema message
 */ 
function stomp_post_schema() {

      $fields        = array();
      $defaults      = array();
      $option_fields = array();      
      $params        = array( "name" => "pl.org.civicrm.stomp.schema.labels" );
      $option_group  = CRM_Core_BAO_OptionGroup::retrieve( $params, $defaults);
      if($option_group) {
          $option_fields = CRM_Core_BAO_OptionValue::getOptionValuesAssocArray($option_group->id);
      }
      $organizationFeilds = civicrm_api( "contact", "getfields", array("version" => 3, "contact_type"=> "Organization", "action" => "get" ));
      foreach( $organizationFeilds['values'] as $key => $values ) {
        if(!empty($values['extends']) && $values['extends'] == "Organization") {
          watchdog("field", print_r($values['label'],true));
          $fields[$key]['label'] = CRM_Utils_Array::value($key, $option_fields, $values['label']);
          $fields[$key]['type'] = $values["data_type"];          
          $fields[$key]['htmlType'] = $values["html_type"];          
        }
        else if(empty($values['extends'])) {
          $fields[$key]['label'] = CRM_Utils_Array::value($key, $option_fields, $key);
          $fields[$key]['type'] = CRM_Utils_Type::typeToString($values["type"]);          
          $fields[$key]['htmlType'] = "String";
        }
      }
       
      $extendsFeildTypes = array( "address", "phone", "website", "im", "relationship" );
      foreach( $extendsFeildTypes as $i => $extendsFeildType ) {
       $extendsFeilds = civicrm_api( "$extendsFeildType", "getfields", array("version" => 3, "action" => "get" ));
       foreach( $extendsFeilds['values'] as $key => $values ) { 
         if(!empty($values['extends'])) {       
           $fields[$key]['label'] = CRM_Utils_Array::value($key, $option_fields, $values['label']);
           $fields[$key]['type'] = $values['data_type'];          
           $fields[$key]['htmlType'] = $values['html_type'];          
         } else {        
           $fields[$key]['label'] = CRM_Utils_Array::value($key, $option_fields, $key);
           $fields[$key]['type'] = CRM_Utils_Type::typeToString($values["type"]);          
           $fields[$key]['htmlType'] = "String";   
         }
       }
       $fields["api.$extendsFeildType.get"]['label'] = CRM_Utils_Array::value("api.$extendsFeildType.get", $option_fields, "$extendsFeildType");
       $fields["api.$extendsFeildType.get"]['type'] = 'group';
       $fields["api.$extendsFeildType.get"]['htmlType'] = 'group';
       $fields["api.$extendsFeildType.get"]['fields'] = array_keys($extendsFeilds['values']);   
      }

      $relationshipTypes = civicrm_api("RelationshipType", "get", array('version' => 3));
      if($relationshipTypes['count']) {
         $relationships = array();
         foreach($relationshipTypes['values'] as $i => $values ) {
           $id = $values['id'];
           $relationships['relationship_type_'.$id.'_label_a_b']['label'] = $values['label_a_b'];
           $relationships['relationship_type_'.$id.'_label_a_b']['type'] = 'organization';            
           $relationships['relationship_type_'.$id.'_label_a_b']['htmlType'] = 'link';              
           $relationships['relationship_type_'.$id.'_label_b_a']['label'] = $values['label_b_a'];   
           $relationships['relationship_type_'.$id.'_label_b_a']['type'] = 'organization';            
           $relationships['relationship_type_'.$id.'_label_b_a']['htmlType'] = 'link';                            
         }
         $fields = array_merge($fields, $relationships);   
      }

      $customGroups = civicrm_api("CustomGroup", "get", array('version' => 3));      
      foreach ($customGroups['values'] as $cgid => $group) {
        if( $group['extends'] == 'Organization' ) {
          $customFields = civicrm_api("CustomField", "get", array('version' => 3, 'custom_group_id' => $cgid));
          $customFields_ids = array();
          foreach ($customFields['values'] as $cfid => $values) {
            $fields['custom_'.$cfid]['label'] = $values['label'];
            $fields['custom_'.$cfid]['type'] = $values["data_type"];          
            $fields['custom_'.$cfid]['htmlType'] = $values["html_type"];            
            $customFields_ids[] = 'custom_' . $cfid;
          }        
          $fields['custom_group_' . $cgid]['label'] = $group['title'];          
          $fields['custom_group_' . $cgid]['type'] = 'group';          
          $fields['custom_group_' . $cgid]['htmlType'] = 'group';                                    
          $fields['custom_group_' . $cgid]['fields'] = $customFields_ids;          
        } 
      }
     
      $config = CRM_Core_Config::singleton();
      $config->stomp->connect();
      $queue = $config->stomp->getQueue('schema');
      $config->stomp->send( array("schema" => $fields), $queue);

}

/**
 * get terms object
 */
function _stomp_get_terms( $custom_field_id, $value = array(), $taxonomy_fields_ids = array()) {

  if(in_array($custom_field_id, $taxonomy_fields_ids)) {

    if(is_array($value)) {
        if(!empty($value) && $term = taxonomy_term_load($value[0])){
          return array( "vid" => $term->vid, "tid" => $value );
        }

    } elseif($term = taxonomy_term_load($value)){
          return array( "vid" => $term->vid, "tid" => array($value));    
    }
    return "";    
  }
  return $value;
}


/**
 * sort descending array by last element value
 */
function _stomp_cmp_custom_fields_array($a, $b)
{
  return strcmp(end($b), end($a));
}


/**
 * get contact custom values array formatted as cyklotron data
 */
function _stomp_custom_value_get( $params ) {

  $values = array();    
  $result = CRM_Core_BAO_CustomValueTable::getValues( $params );
  
  // skip other organization subtype custom values fields.
  if ($result['is_error'] == 0 ) {

    unset($result['is_error'], $result['entityID']);
    
    // Convert multi-value strings to arrays
    $sp = CRM_Core_DAO::VALUE_SEPARATOR;
    
    // get taxonomy bazyngo_categorization module fields ids    
    $taxonomy_fields_ids = array ( "0" => variable_get('bazyngo_categorization_customfield1_id', 0),
                                   "1" => variable_get('bazyngo_categorization_customfield1_id', 0));
    
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
      $value = _stomp_get_terms($fieldNumber, $value, $taxonomy_fields_ids);
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
 * get contact relationship array formatted as cyklotron data
 */
function _stomp_relationship_get( $contact_id, $relationships ) {

   $result = array();
   // sort array by last element value   
   usort($relationships,"_stomp_cmp_custom_fields_array");   
   foreach( $relationships as $i => $relationship ) { 
     if( $relationship['is_active'] ) {
      $rtid = $relationship['relationship_type_id'];
      if( $relationship['contact_id_a'] == $contact_id ) {
        $external_id = civicrm_api("Contact", "getvalue", array("version" => '3', 
                                                                "contact_id" => $relationship['contact_id_b'], 
                                                                "return" => 'external_identifier'));     
                                                                                                                                 
        $result['relationship_type_'.$rtid.'_label_a_b'][] = $external_id;
      } else {
        $external_id = civicrm_api("Contact", "getvalue", array("version" => '3', 
                                                                "contact_id" => $relationship['contact_id_a'], 
                                                                "return" => 'external_identifier'));
                                                                           
        $result['relationship_type_'.$rtid.'_label_b_a'][] = $external_id;      
      }
     }
   }
   return $result;
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
      $countries = CRM_Core_PseudoConstant::country();
      $locationTypes = CRM_Core_PseudoConstant::locationType();
      $imProviders = CRM_Core_PseudoConstant::IMProvider();
      $websiteTypes = CRM_Core_PseudoConstant::websiteType();
      $phoneTypes = CRM_Core_PseudoConstant::phoneType(); 
           
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
                       'id' => $objectId );

      $paramsExtraData = array( 'api.website.get' => array(), 
                                'api.im.get' => array(), 
                                'api.phone.get' => array(), 
                                'api.address.get' => array(),
                                'api.relationship.get' => array(),
                               );                              
                                                                                            
      $result = civicrm_api("Contact", "get", array_merge($paramsExtraData, $params ));
      if( empty($result['is_error']) && $result["values"][$result["id"]]) {
         $result = $result["values"][$result["id"]];
         foreach( $paramsExtraData as $key => $value ) {  
           if(empty($result[$key]['is_error']) && !empty($result[$key]['values']) ) {
             $result[$key] = $result[$key]['values'];
             $keyPart = explode('.', $key);
             if( $keyPart[1] && $keyPart[1] == 'address') {
               $entityType = ucfirst($keyPart[1]);
               foreach( $result[$key] as $i => $entity ) {
                 $result[$key][$i]["location_type_id"] = CRM_Utils_Array::value($entity["location_type_id"], $locationTypes);
                 if(!empty($entity["country_id"])) {
                   $result[$key][$i]["country_id"] = CRM_Utils_Array::value($entity["country_id"], $countries);                                 
                 }
                 $customParams = array( "entityID" => $entity['id'], 
                                        "entityType" => $entityType, );  
                 $custom = _stomp_custom_value_get($customParams); 
                 $result[$key][$i] += $custom;
               }
             }
             elseif( $keyPart[1] && $keyPart[1] == 'website') {
               foreach( $result[$key] as $i => $entity ) {
                $result[$key][$i]["website_type_id"] = CRM_Utils_Array::value($entity["website_type_id"], $websiteTypes);               
               }
             }
             elseif( $keyPart[1] && $keyPart[1] == 'im') {
              foreach( $result[$key] as $i => $entity ) {
               $result[$key][$i]["location_type_id"] = CRM_Utils_Array::value($entity["location_type_id"], $locationTypes);                                     
               $result[$key][$i]["provider_id"] = CRM_Utils_Array::value($entity["provider_id"], $imProviders);               
              }
             }             
             elseif( $keyPart[1] && $keyPart[1] == 'phone') {
              foreach( $result[$key] as $i => $entity ) {
               $result[$key][$i]["location_type_id"] = CRM_Utils_Array::value($entity["location_type_id"], $locationTypes);
               $result[$key][$i]["phone_type_id"] = CRM_Utils_Array::value($entity["phone_type_id"], $phoneTypes);       
              }  
             }
             elseif( $keyPart[1] && $keyPart[1] == 'relationship' ) {
               $result2 = civicrm_api("RelationshipType", "get", array("version" => 3 ) );
               watchdog("bazyngo_ui debug", print_r($result2,true));
               $result[$key] = _stomp_relationship_get( $objectId, $result[$key] );
             }              
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
      
      $config->stomp->send($result, $queue);      
      break;
    default:
      $config->stomp->log($logText . 'Nothing to do', 'DEBUG');
  }

  return;
}