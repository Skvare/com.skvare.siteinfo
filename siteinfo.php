<?php

require_once 'siteinfo.civix.php';
// phpcs:disable
use CRM_Siteinfo_ExtensionUtil as E;
// phpcs:enable

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function siteinfo_civicrm_config(&$config) {
  _siteinfo_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function siteinfo_civicrm_install() {
  _siteinfo_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function siteinfo_civicrm_enable() {
  _siteinfo_civix_civicrm_enable();
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
 */
//function siteinfo_civicrm_preProcess($formName, &$form) {
//
//}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
//function siteinfo_civicrm_navigationMenu(&$menu) {
//  _siteinfo_civix_insert_navigation_menu($menu, 'Mailings', [
//    'label' => E::ts('New subliminal message'),
//    'name' => 'mailing_subliminal_message',
//    'url' => 'civicrm/mailing/subliminal',
//    'permission' => 'access CiviMail',
//    'operator' => 'OR',
//    'separator' => 0,
//  ]);
//  _siteinfo_civix_navigationMenu($menu);
//}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
function siteinfo_civicrm_navigationMenu(&$menu) {
  _siteinfo_civix_insert_navigation_menu($menu, 'Administer/System Settings', [
    'label' => E::ts('Site Info Setting'),
    'name' => 'siteinfo_setting',
    'url' => CRM_Utils_System::url('civicrm/siteinfo/setting', 'reset=1', TRUE),
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'separator' => 0,
  ]);
  _siteinfo_civix_navigationMenu($menu);
}
/**
 * Implements hook_civicrm_idsException().
 */
function siteinfo_civicrm_idsException( &$skip ) {
  $skip[] = 'civicrm/siteinfo/status';
}
