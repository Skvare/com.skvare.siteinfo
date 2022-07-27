<?php
require_once  __DIR__ . '/../../../vendor/autoload.php';
use CRM_Siteinfo_ExtensionUtil as E;
use \Firebase\JWT\JWT;


class CRM_Siteinfo_Page_Report extends CRM_Core_Page {

  public function run() {
    CRM_Utils_Request::retrieve('jwt', 'String', $this, FALSE);
    $isError = TRUE;
    $geSettings = self::getSettings();
    if (!empty($geSettings['siteinfo_secret']) && !empty($_REQUEST['jwt'])) {
      try {
        $token = JWT::decode($_REQUEST['jwt'], $geSettings['siteinfo_secret'], ['HS512']);
        $isError = FALSE;
      }
      catch (Exception $exception) {
        $isError = TRUE;
      }
      $now = new DateTimeImmutable();
      if ($token->nbf > $now->getTimestamp() || $token->exp < $now->getTimestamp()) {
        $isError = TRUE;
      }
    }
    $outputArray = [];
    CRM_Utils_System::setTitle(E::ts('Status Report'));

    if (!$isError) {
      // Get the System Status details.
      $output = CRM_Utils_Check::checkStatus();
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
      // get CMS type
      $outputArray['cmsType'] = [
        'name' => 'cmsType',
        'message' => '',
        'title' => CIVICRM_UF,
        'level' => 1,
        'severity' => 'info',
      ];
      // Get Code Version
      $latestVer = CRM_Utils_System::version();
      // Get DB CiviCRM Version
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
      // Get CMS Version
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
      $versionInfo = new CRM_Siteinfo_DrupalVersionCheck();
      [$isUpgradeRequire, $isSecurityRelease, $recommendedVersion] =
        $versionInfo->checkVersion(CIVICRM_UF, $outputArray['cmsVersion']['title']);
      if ($isUpgradeRequire) {
        $outputArray['cmsVersion']['severity'] = 'error';
        $outputArray['cmsVersion']['message'] = 'Upgrade Drupal to ' . $recommendedVersion;
        // @TODO for secure version release.
        if ($isSecurityRelease) {
          $outputArray['civicrmDbVersioncmsVersion']['severity'] = 'error';
        }
      }
    }
    CRM_Utils_JSON::output($outputArray);
    CRM_Utils_System::civiExit();
  }

  /**
   *
   * @return string[]
   */
  public static function getSettingsNames(): array {
    return [
      'siteinfo_secret',
    ];
  }

  /**
   * @return array
   */
  public static function getSettings() {
    foreach (self::getSettingsNames() as $name) {
      $settings[$name] = Civi::settings()->get($name);
    }

    return $settings ?? [];
  }
}
