<?php
namespace Efront\Plugin\OistSaml\Model;

use Efront\Controller\TemplateController;
use Efront\Model\BaseModel;
use Efront\Model\Cache;
use Efront\Model\Configuration;
use Efront\Model\Database;
use Efront\Model\Form;

class OistSamlForm extends BaseModel {
  const PLUGIN_NAME = 'OistSaml';
  
  public function form($url) {
    $form = new Form("oist_saml_form", "post", $url, "", null, true);
    try {
      $form->addElement("advcheckbox", "oist_saml_enable_sitewide", translate("Enable SAML sitewide"), translate("Enable SAML sitewide"), [
        0,
        1
      ]);
      $form->addElement("static", "", translate("Make sure SAML is enabled and configured in the <a href = '/system-config/tab/saml' class = 'link'>SAML settings.</a>"));
      $form->addElement('submit', 'submit', translate("Submit"), 'class = "btn btn-primary"');
      $form->setDefaults(Configuration::getValues());
      
      if ($form->isSubmitted() && $form->validate()) {
        try {
          $values = $form->exportValues();
          unset($values['submit']);
          foreach ($values as $key => $value) {
            $this->setValue($key, $value);
          }
          TemplateController::setSuccessMessage();
        } catch (\Exception $e) {
          handleNormalFlowExceptions($e);
        }
      }
    } catch (\Exception $e) {
      handleNormalFlowExceptions($e);
    }
    
    return $form;
    
  }

  /**
   * Override the set configuration value to avoid errors due our configuration
   * not being in the default set that Efront expects.
   *
   * @see Configuration::setValue()
   */
  protected static function setValue($name, $value) {
    
      Database::getInstance()->updateTableData("configuration", array('value' => $value), "name = '$name'");
      if (Database::getInstance()->getAffectedRows() == 0) {
        $result = Database::getInstance()->getTableData("configuration", "*", "name='{$name}'");
        if (empty($result)) {
          Database::getInstance()->insertTableData("configuration", array('name' => $name, 'value' => $value));
        }
      }
    
    
    Cache::getInstance()->deleteCache('configuration');
    
    return true;
  }
  
  
}
