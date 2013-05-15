<?php

abstract class OrganizationStatus
{   
    public $status;     
}

class OrganizationStatusDeleted extends OrganizationStatus
{
    function __construct() {
        $this->status = "DELETED";    
    }
}

class OrganizationStatusModified extends OrganizationStatus
{
    function __construct(){
        $this->status = "MODIFIED"; 
    }   
}

?>