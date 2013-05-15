<?php

abstract class OrganizationStatus
{   
    public $action;
    public $status;     
}

class OrganizationStatusSetDeleted extends OrganizationStatus
{
    function __construct() {
        $this->action = "SET";
        $this->status = "DELETED";    
    }
}

class OrganizationStatusUnsetDeleted extends OrganizationStatus
{
    function __construct() {
        $this->action = "UNSET";    
        $this->status = "DELETED";    
    }
}

class OrganizationStatusSetModified extends OrganizationStatus
{
    function __construct(){
        $this->action = "SET";    
        $this->status = "MODIFIED"; 
    }   
}

class OrganizationStatusUnsetModified extends OrganizationStatus
{
    function __construct(){
        $this->action = "UNSET";    
        $this->status = "MODIFIED"; 
    }   
}

?>