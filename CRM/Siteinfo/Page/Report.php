<?php
require_once  __DIR__ . '/../../../vendor/autoload.php';
use CRM_Siteinfo_ExtensionUtil as E;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


class CRM_Siteinfo_Page_Report extends CRM_Core_Page {

  public function run() {
    CRM_Utils_Request::retrieve('jwt', 'String', $this, FALSE);
    $isError = TRUE;
    $getSettings = self::getSettings();
    if (!empty($getSettings['siteinfo_secret']) && !empty($_REQUEST['jwt'])) {
      try {
        $currentVer = CRM_Core_BAO_Domain::version(TRUE);
        if (version_compare($currentVer, '5.69.0') < 0) {
          $algs = ['HS512'];
          $token = JWT::decode($_REQUEST['jwt'], $getSettings['siteinfo_secret'], $algs);
        }
        else {
          $token = JWT::decode($_REQUEST['jwt'], new Key($getSettings['siteinfo_secret'], 'HS512'));
        }
        $isError = FALSE;
      }
      catch (Exception $exception) {
        $isError = TRUE;
      }
      $now = new DateTimeImmutable();
      if (!$isError && ($token->nbf > $now->getTimestamp() || $token->exp < $now->getTimestamp())) {
        $isError = TRUE;
      }
    }
    $projects = $outputArray = [];
    CRM_Utils_System::setTitle(E::ts('Status Report'));
    if (!$isError) {
      $drupalStatus = [];
      if (CIVICRM_UF == 'Drupal8') {
        $drupalStatus = CRM_Siteinfo_DrupalVersionCheck::drupal9Update();
      }
      elseif (CIVICRM_UF == 'Drupal') {
        $drupalStatus = CRM_Siteinfo_DrupalVersionCheck::drupal7Update();
      }
      $outputArray['cmsModules'] = [
        'name' => 'cmsModules',
        'message' => '',
        'level' => 1,
        'severity' => 'info',
        'title' => 'Modules',
      ];
      if (!empty($drupalStatus['update_contrib'])) {
        $outputArray['cmsModules']['severity'] =
          CRM_Siteinfo_DrupalVersionCheck::getSeverity($drupalStatus['update_contrib']['severity']);
        $outputArray['cmsModules']['message'] = strip_tags($drupalStatus['update_contrib']['value']);
      }
      foreach (['cron' => 'Drupal_Cron', 'update' => 'Drupal_Database_updates'] as $statusType => $statusTypeLabel) {
        if (!empty($drupalStatus[$statusType])) {
          $outputArray[$statusTypeLabel] = [
            'name' => $statusTypeLabel,
            'message' => strip_tags($drupalStatus[$statusType]['title']),
            'level' => 1,
            'severity' => CRM_Siteinfo_DrupalVersionCheck::getSeverity($drupalStatus[$statusType]['severity']),
            'title' => strip_tags($drupalStatus[$statusType]['value']),
          ];
        }
      }
      // Get the System Status details.
      $output = CRM_Utils_Check::checkStatus();
      foreach ($output as $messageObject) {
        $msg = $messageObject->getMessage();
        if (!empty($msg)) {
          $msg = strip_tags($msg);
          $msg = htmlentities($msg);
          $msg = addslashes($msg);
        }
        $outputArray[$messageObject->getName()] = [
          'name' => $messageObject->getName(),
          'message' => $msg,
          'title' => $messageObject->getTitle(),
          'level' => $messageObject->getLevel(),
          'severity' => $messageObject->getSeverity(),
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
      if (CIVICRM_UF == 'Drupal8') {
        $exactCiviCoreVersion = CRM_Siteinfo_DrupalVersionCheck::getExactVersion('civicrm/civicrm-core');
        $exactCiviModuleVersion = CRM_Siteinfo_DrupalVersionCheck::getExactVersion('civicrm/civicrm-drupal-8');
        if (!empty($exactCiviModuleVersion)) {
          $outputArray['civicrmCodeVersion']['message'] = 'CiviCRM Module Tag : ' . $exactCiviModuleVersion;
          $outputArray['civicrmCodeVersion']['civicrm_module_tag'] = $exactCiviModuleVersion;
        }
        if (!empty($exactCiviCoreVersion)) {
          $outputArray['civicrmCodeVersion']['title'] .= ' (Tag: ' . $exactCiviCoreVersion . ')';
          $outputArray['civicrmCodeVersion']['civicrm_core_tag'] = $exactCiviCoreVersion;
        }
      }
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
        $outputArray['cmsType']['title'] = 'Drupal-' . intval(VERSION);
      }
      elseif (CIVICRM_UF == 'Drupal8') {
        $outputArray['cmsVersion']['title'] = \Drupal::VERSION;
        $outputArray['cmsType']['title'] = 'Drupal-' . intval(\Drupal::VERSION);
      }
      elseif (CIVICRM_UF == 'WordPress') {
        global $wp_version;
        $outputArray['cmsVersion']['title'] = $wp_version;
        $outputArray['cmsType']['title'] = 'WordPress-' . intval($wp_version);

        $config = CRM_Core_Config::singleton();
        $cmsRoot = $config->userSystem->cmsRootPath();
        require_once $cmsRoot . '/wp-admin/includes/update.php';
        $updates = get_preferred_from_update_core();
        if ($updates->response == 'latest') {
          $outputArray['cmsVersion']['severity'] = 'info';
          $outputArray['cmsVersion']['level'] = 0;
          $outputArray['cmsVersion']['message'] = 'Your are on latest version';
        }
        else {
          $outputArray['cmsVersion']['severity'] = 'warning';
          $outputArray['cmsVersion']['level'] = 1;
          $outputArray['cmsVersion']['message'] = 'Upgrade to ' . $updates->version;
        }
      }
      else {
        $outputArray['cmsVersion']['title'] = 'NA';
      }
      if (in_array(CIVICRM_UF, ['Drupal', 'Drupal8'])) {
        $versionInfo = new CRM_Siteinfo_DrupalVersionCheck();
        $projectStatus = $versionInfo->checkVersion('drupal', 'Drupal', CIVICRM_UF, $outputArray['cmsVersion']['title']);
        $outputArray['cmsVersion']['message'] = $projectStatus['message'];
        $outputArray['cmsVersion']['severity'] = $projectStatus['severity'];
      }
      /*
      if ($projectStatus['isUpgradeRequire']) {
        $outputArray['cmsVersion']['severity'] = 'warning';
        if ($projectStatus['isSecurityRelease']) {
          $outputArray['cmsVersion']['severity'] = 'error';
        }
      }
      */
      $configDbCiviCRM = parse_url(CIVICRM_DSN);
      $outputArray['dbServer'] = [
        'name' => 'dbServer',
        'message' => '',
        'title' => $configDbCiviCRM['host'],
        'level' => 1,
        'severity' => 'info',
      ];

      $outputArray['linuxServer'] = [
        'name' => 'linuxServer',
        'message' => '',
        'title' => gethostname(),
        'level' => 1,
        'severity' => 'info',
      ];

      foreach (['dbCMS' => CIVICRM_UF_DSN, 'dbCiviCRM' => CIVICRM_DSN] as $dbKey => $db_connection) {
        $date = self::getDbDate($db_connection);
        if (!empty($date)) {
          $outputArray[$dbKey] = [
            'name' => $dbKey,
            'message' => $date,
            'title' => date('Y-m-d', strtotime($date)),
            'level' => 1,
            'severity' => 'info',
          ];
        }
      }
    }
    CRM_Utils_JSON::output($outputArray);
    CRM_Utils_System::civiExit();
  }

  /**
   * Function to get Datbase Create Date Time.
   *
   * @param $db_connection
   * @return string|null
   * @throws CRM_Core_Exception
   */
  public static function getDbDate($db_connection) {
    try {
      $dbConfig = parse_url($db_connection);
      $dbName = trim($dbConfig['path'], '/');
      $sqlParams[1] = [$dbName, 'String'];
      $sql = "SELECT MIN(create_time) AS Creation_Time FROM information_schema.tables WHERE table_schema = %1 Group by table_schema";

      return CRM_Core_DAO::singleValueQuery($sql, $sqlParams);
    }
    catch (\Exception $e) {
      return '';
    }
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
