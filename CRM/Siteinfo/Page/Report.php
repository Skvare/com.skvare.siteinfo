<?php
use CRM_Siteinfo_ExtensionUtil as E;

class CRM_Siteinfo_Page_Report extends CRM_Core_Page {

  public function run() {
    CRM_Utils_System::setTitle(E::ts('Status Report'));
    $output = CRM_Utils_Check::checkStatus();
    $outputArray = [];
    //CRM_Utils_JSON::output($outputArray);
    //CRM_Utils_System::civiExit();
    foreach ($output as $messageObje) {
      $msg = $messageObje->getMessage();
      if (!empty($msg)) {
        $msg = strip_tags($msg);
        $msg = htmlentities($msg);
        $msg = addslashes($msg);
      }
      $outputArray[$messageObje->getName()] = [
        'name' => $messageObje->getName(),
        'message' => $msg,
        'title' => $messageObje->getTitle(),
        'level' => $messageObje->getLevel(),
        'severity' => $messageObje->getSeverity(),
      ];
    }
    $outputArray['cmsType'] = [
      'name' => 'cmsType',
      'message' => '',
      'title' => CIVICRM_UF,
      'level' => 1,
      'severity' => 'info',
    ];
    $latestVer = CRM_Utils_System::version();
    $currentVer = CRM_Core_BAO_Domain::version(TRUE);
    $outputArray['civicrmCodeVersion'] = [
      'name' => 'civicrmCodeVersion',
      'message' => '',
      'title' => $latestVer,
      'level' => 1,
      'severity' => 'info',
    ];
    $outputArray['civicrmDbVersion'] = [
      'name' => 'civicrmDbVersion',
      'message' => '',
      'title' => $currentVer,
      'level' => 1,
      'severity' => 'info',
    ];
    if (version_compare($currentVer, $latestVer) > 0) {
      $outputArray['civicrmDbVersion']['level'] = 5;
      $outputArray['civicrmDbVersion']['severity'] = 'error';
      $outputArray['civicrmDbVersion']['message'] = 'DB Version ahead of code version.';
    }
    if (version_compare($currentVer, $latestVer) < 0) {
      $outputArray['civicrmDbVersion']['level'] = 4;
      $outputArray['civicrmDbVersion']['severity'] = 'warning';
      $outputArray['civicrmDbVersion']['message'] = 'Upgrade your Database.';
    }
    $outputArray['cmsVersion'] = [
      'name' => 'cmsVersion',
      'message' => '',
      'level' => 1,
      'severity' => 'info',
    ];
    if (CIVICRM_UF == 'Drupal') {
      $outputArray['cmsVersion']['title'] = VERSION;
    }
    elseif (CIVICRM_UF == 'Drupal8') {
      $outputArray['cmsVersion']['title'] = \Drupal::VERSION;
    }
    else {
      $outputArray['cmsVersion']['title'] = 'NA';
    }

    CRM_Utils_JSON::output($outputArray);
    CRM_Utils_System::civiExit();
  }

}
