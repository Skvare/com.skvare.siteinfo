<?php

use CRM_Siteinfo_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Siteinfo_Form_Setting extends CRM_Core_Form {
  public function buildQuickForm() {

    // add form elements
    $this->add('text', 'siteinfo_secret', ts('Secret Key'), ['size' => 48],
      TRUE);

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    // use settings as defined in default domain
    $domainID = CRM_Core_Config::domainID();
    $settings = Civi::settings($domainID);
    $setDefaults = [];
    foreach ($this->getRenderableElementNames() as $elementName) {
      $setDefaults[$elementName] = $settings->get($elementName);
    }
    $this->setDefaults($setDefaults);
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();
    // use settings as defined in default domain
    $domainID = CRM_Core_Config::domainID();
    $settings = Civi::settings($domainID);

    foreach ($values as $k => $v) {
      if (strpos($k, 'siteinfo_') === 0) {
        $settings->set($k, $v);
      }
    }
    CRM_Core_Session::setStatus(E::ts('Setting updated successfully'));
    parent::postProcess();
  }


  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
