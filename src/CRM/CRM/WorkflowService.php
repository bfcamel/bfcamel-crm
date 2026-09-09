<?php
namespace BfCamel\CRM\CRM;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Compatibility bridge: WorkflowService historically lives one directory above
// while the CRM namespace autoloader expects CRM/WorkflowService.php.
require_once dirname( __DIR__ ) . '/WorkflowService.php';
