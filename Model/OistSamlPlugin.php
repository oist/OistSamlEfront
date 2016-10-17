<?php

namespace Efront\Plugin\OistSaml\Model;
use Efront\Controller\BaseController;
use Efront\Controller\UrlhelperController;
use Efront\Model\AbstractPlugin;
use Efront\Model\Configuration;
use Efront\Model\User;
use Efront\Model\UserType;
use Efront\Plugin\OistSaml\Controller\OistSamlController;

class OistSamlPlugin extends AbstractPlugin {
	const VERSION = '1.0';
  protected $_sso_settings = array();
  
  public function installPlugin() {
	}

	public function uninstallPlugin() {
	}

	public function upgradePlugin() {
	}
  
  public function onLoadIconList($list_name, &$options) {
    if ($list_name == 'dashboard' && !User::getCurrentUser()->isSupervisor() && User::getCurrentUser()->user_types_ID == UserType::USER_TYPE_ADMINISTRATOR) {
      $options[] = array(
        'text' => $this->plugin->title,
        'group' => 4,
        'image' => $this->plugin_url . '/assets/images/plugin.svg',
        'class' => 'medium',
        'href' => UrlhelperController::url(array(
          'ctg' => $this->plugin->name
        )),
        'plugin' => true
      );
      return $options;
    } else {
      return null;
    }
  }
  
  public function onCtg($ctg) {
    if ($ctg == $this->plugin->name) {
      BaseController::getSmartyInstance()->assign("T_CTG", 'plugin')->assign("T_PLUGIN_FILE", $this->plugin_dir.'/View/OistSaml.tpl');
      $controller = new OistSamlController();
      $controller->plugin = $this->plugin;
      return $controller;
    }
  }
  
  public function onPageLoadStart() {
    // Only affect anonymous users.
    if (User::getCurrentUser()->id) {
      return;
    }
  
    // SAML needs to be enabled in the first place.
    $this->_sso_settings = getSamlConfigurationValues();
    if (!$this->_sso_settings[Configuration::CONFIGURATION_SAML_ENABLED]) {
      return;
    }
    
    // If the configuration is disabled, don't do anything.
    if (!Configuration::getValue("oist_saml_enable_sitewide")) {
      return;
    }
    
    // Set the return URL to the current one so we come back to the right place.
    $params = [
      'ReturnTo' => "//{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}"
    ];
    $saml = new OistSaml();
    $saml->authenticate($params);
  }
 
}
