<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Stomp
 *
 * @author michau
 */
class CRM_Stomp_Stomp {

   /**
   * We only need one instance of this object. So we use the singleton
   * pattern and cache the instance in this variable
   * @var object
   * @static
   */
  private static $_singleton = NULL;

   /**
   * class constructor
   *
   * @return CRM_Core_Smarty
   * @access private
   */ 
  function __construct() {
  }
  
   /**
   * Static instance provider.
   *
   * Method providing static instance of Stomp provider
   */
  static function &singleton() {
    if (!isset(self::$_singleton)) {
       self::$_singleton = new CRM_Stomp_Stomp();
    }
    return self::$_singleton;
  }   
}

?>
