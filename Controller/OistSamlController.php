<?php
namespace Efront\Plugin\OistSaml\Controller;

use Efront\Controller\TemplateController;
use Efront\Model\User;
use Efront\Controller\UrlhelperController;
use Efront\Model\UserType;

use Efront\Controller\BaseController;
use Efront\Model\Configuration;
use Efront\Plugin\OistSaml\Model\OistSamlForm;

class OistSamlController extends BaseController
{
	public $plugin;
	
	protected function _requestPermissionFor() {
	    if (Configuration::getValue(Configuration::CONFIGURATION_VERSION_HOSTED)) {
	        return array(UserType::USER_TYPE_PERMISSION_PLUGINS, 'none');
	    } else {
	        return array(UserType::USER_TYPE_PERMISSION_PLUGINS, UserType::USER_TYPE_ADMINISTRATOR);
	    }
	}
	
	public function index() {
		if (User::getCurrentUser()->isSupervisor() || User::getCurrentUser()->user_types_ID != UserType::USER_TYPE_ADMINISTRATOR) {
			UrlhelperController::redirect(array('ctg' => 'start'));
		}

		$smarty = self::getSmartyInstance();
		$this->_base_url = UrlhelperController::url(array('ctg' => $this->plugin->name));
		$smarty->assign("T_PLUGIN_TITLE", $this->plugin->title)->assign("T_PLUGIN_NAME", $this->plugin->name)->assign("T_BASE_URL", $this->_base_url);

		TemplateController::$purify = false;

		$this->_model = new OistSamlForm();
		$form = $this->_model->form($this->_base_url);

		$smarty->assign("T_SUCCESS", $form->success);
		$smarty->assign("T_PROCESSED", $form->processed);
		$smarty->assign("T_FORM", $form->toArray());
		if ($form->success) {
			TemplateController::setSuccessMessage();
		}
	}
	
}
