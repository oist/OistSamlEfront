<?php
namespace Efront\Plugin\OistSaml\Model;

use Efront\Controller\MainController;
use Efront\Controller\UrlhelperController;
use Efront\Controller\TemplateController;
use Efront\Exception\EfrontException;
use Efront\Model\Branch;
use Efront\Model\Configuration;
use Efront\Model\User;
use Exception;


/**
 * This is a clone of the Saml initial library with support to redirect to the
 * initial url request.
 *
 * @see Saml::authenticate()
 */
class OistSaml
{
  const PLUGIN_NAME = 'OistSaml';
  const SSO_TYPE_NONE = '0';
  const SSO_TYPE_RMUNIFY = '1';
  const SSO_TYPE_SAML = '2';
  const SSO_TYPE_LDAP = '3';
  
  protected $_domain = 'efront-sp';
  protected $_sso_settings = array();
  
  public function __construct() {
    require_once(G_ROOTPATH.'libraries/external/simplesamlphp/lib/_autoload.php');
    
    $this->_sso_settings = getSamlConfigurationValues();
    $session = \SimpleSAML_Session::getInstance();
    $this->_sso_settings['domain'] = $this->_domain;
    $session->setData("Array", "sso", $this->_sso_settings);
  }
  
  public function getDomain() {
    return $this-> _domain;
  }
  
  public function authenticate($params) {
    try{
      $as = new \SimpleSAML_Auth_Simple($this->_domain);
      \SimpleSAML_Configuration::getInstance();
      
      $as->requireAuth($params);
      
      if($as->isAuthenticated()){
        
        $attributes = $as->getAttributes();
        
        if (!array_key_exists($this->_sso_settings[Configuration::CONFIGURATION_SAML_EMAIL], $attributes)){
          TemplateController::setMessage(translate("A valid email is needed for account related communication").". ".translate("Check that the %s attribute (%s) defined in your configuration is correct",translate("Email"),$this->_sso_settings[Configuration::CONFIGURATION_SAML_EMAIL]), 'error');
          $this->ssoLogout();
        }elseif(!array_key_exists($this->_sso_settings[Configuration::CONFIGURATION_SAML_FIRST_NAME], $attributes)){
          TemplateController::setMessage(translate("'%s' is required",translate("First name")).". ".translate("Check that the %s attribute (%s) defined in your configuration is correct",translate("First name"),$this->_sso_settings[Configuration::CONFIGURATION_SAML_FIRST_NAME]), 'error');
          $this->ssoLogout();
        }elseif(!array_key_exists($this->_sso_settings[Configuration::CONFIGURATION_SAML_LAST_NAME], $attributes)){
          TemplateController::setMessage(translate("'%s' is required",translate("Last name")).". ".translate("Check that the %s attribute (%s) defined in your configuration is correct",translate("Last name"),$this->_sso_settings[Configuration::CONFIGURATION_SAML_LAST_NAME]), 'error');
          $this->ssoLogout();
        }
        else{
          
          if (trim($attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_EMAIL]][0]) == ''){
            $attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_EMAIL]][0] = " ";
            TemplateController::setMessage(translate("A valid email is needed for account related communication"), 'error');
          }
          
          if(trim($attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_FIRST_NAME]][0]) == '' && trim($attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_LAST_NAME]][0]) == ''){
            $attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_FIRST_NAME]][0] = ' ';
            $attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_LAST_NAME]][0] = ' ';
          }
          else{
            if(trim($attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_FIRST_NAME]][0]) == ''){
              $attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_FIRST_NAME]][0] = $attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_LAST_NAME]][0];
            }
            
            if(trim($attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_LAST_NAME]][0]) == ''){
              $attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_LAST_NAME]][0] = $attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_FIRST_NAME]][0];
            }
          }
          
          $session = \SimpleSAML_Session::getInstance();
          $session->registerLogoutHandler('EfrontConnect', 'logoutHandler');
          
          $this->_login($attributes, $params);
        }
      }
    } catch (\SimpleSAML_Error_Error $e) {
      $this->_samlErrorHandler($e);
    } catch (\Exception $e) {
      handleNormalFlowExceptions($e);
    }
    
    return $this;
  }
  
  /**
   * Process the variables sent by the Idp and perform the login with SAML
   * @param $sso array the value defined in domain's Configuration table
   * @param $values array sent by IdP
   */
  protected function _login($attributes, $params){
    if(!empty($attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_TARGETED_ID]])){	// user comes authenticated in index page
      
      $userId = User::loginToId($attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_TARGETED_ID]][0]);
      
      if (is_null($userId)){	// User doesn't exist. Create user
        if (0&&reachedPlanLimit()){	//@todo
          TemplateController::setMessage(translate("You have reached the maximum active users allowed by the selected plan."), 'warning');
        }
        else{
          $fields = array(
            'login' => $attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_TARGETED_ID]][0],
            'password' => sha1($attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_TARGETED_ID]][0]),
            'name' => $attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_FIRST_NAME]][0],
            'surname' => $attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_LAST_NAME]][0],
            'email' => $attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_EMAIL]][0]);
          
          $user = User::createNewUser($fields);
          $user->login($user->password, true);
          UrlhelperController::redirect('index.php');
        }
      }
      else{	// User exists
        $fields = array(
          'name' => $attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_FIRST_NAME]][0],
          'surname' => $attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_LAST_NAME]][0],
          'email' => $attributes[$this->_sso_settings[Configuration::CONFIGURATION_SAML_EMAIL]][0],
        );
        
        
        $user = new User($userId);
        
        //Added this to avoid login user from central idp when in case he belongs to a branch and exists in both idps
        if (!MainController::$current_branch && !Configuration::getValue(Configuration::CONFIGURATION_BRANCH_SIGNIN_DEFAULT_URL) && $user->branches_ID) {
          $branch = new Branch($user->branches_ID);
          if ($branch->url) {
            TemplateController::setMessage(translate("Please sign in from your branch's URL"));
            UrlhelperController::redirect(array('ctg' => 'start', 'op' => 'login'));
          }
        }
        
        //this line is not needed in case user exists. To prevent issues with same user existing in two idps
        //if (MainController::$current_branch) {
        //$fields['branches_ID'] = MainController::$current_branch->id;
        //}
        
        $user->setFields($fields)->save();	//update whatever changed
        
        $user->login($user->password,true);
        
        if (isset($params['ReturnTo'])) {
          UrlhelperController::redirect($params['ReturnTo']);
        }
        UrlhelperController::redirect(array('ctg' => 'start'));
      }
    }
    /*
     else{//User is not authenticates, set SAML session to be ready for authentication

    $session = \SimpleSAML_Session::getInstance();
    $sso['domain']=$this->_domain;
    $session->setData("Array", "sso", $this->_sso_settings);
    }
    */
  }
  
  /**
   *  SSO logout and destruction of the SAML session
   */
  public function ssoLogout(){
    if ($this->_sso_settings[Configuration::CONFIGURATION_SAML_INTEGRATION_TYPE] == self::SSO_TYPE_SAML && trim($this->_sso_settings[Configuration::CONFIGURATION_SAML_SIGN_OUT]) == ''){
      $session = \SimpleSAML_Session::getInstance();
      $session->doLogout($this->_domain);
    } elseif ($this->_sso_settings[Configuration::CONFIGURATION_SAML_INTEGRATION_TYPE] == self::SSO_TYPE_SAML || $this->_sso_settings[Configuration::CONFIGURATION_SAML_INTEGRATION_TYPE] == self::SSO_TYPE_LDAP) {
      $as = new \SimpleSAML_Auth_Simple($this->_domain);
      $as->logout('/index.php');
    }
    return $this;
  }
  
  /**
   * Error handler for SAML-related errors
   * @param string $errorcode
   * @param string $errormsg
   * @throws Exception
   */
  protected function _samlErrorHandler(\SimpleSAML_Error_Error $e){
    switch ($e->getCode){
      case 'WRONGUSERPASS':
        throw new EfrontException(translate("Your username or password is incorrect. Please try again, making sure that CAPS LOCK key is off"));
        break;
      case 'CREATEREQUEST':
        throw new EfrontException(translate("An error occurred while trying to create the authentication request"));
        break;
      case 'LOGOUTREQUEST':
        throw new EfrontException(translate("An error occurred while trying to process the Logout Request"));
        break;
      case 'METADATA':
        throw new EfrontException(translate("Your SSO metadata is misconfigured."));
        break;
      case 'PROCESSASSERTION':
        throw new EfrontException(translate("We did not accept the response sent from the Identity Provider"));
        break;
      case 'LOGOUTINFOLOST':
        throw new EfrontException(translate("Logout information lost"));
        break;
      case 'RESPONSESTATUSNOSUCCESS':
        throw new EfrontException(translate("The Identity Provider responded with an error"));
        break;
      case 'NOTVALIDCERT':
        throw new EfrontException(translate("You did not present a valid certificate"));
        break;
      case 'NOCERT':
        throw new EfrontException(translate("Authentication failed: your browser did not send any certificate"));
        break;
      case 'UNKNOWNCERT':
        throw new EfrontException(translate("Authentication failed: the certificate your browser sent is unknown"));
        break;
      case 'USERABORTED':
        throw new EfrontException(translate("The authentication was aborted by the user"));
        break;
      case 'NOSTATE':
        throw new EfrontException(translate("State information lost, no possible way to restart the request."));
        break;
      default:
        throw new EfrontException($e->getMessage());
        break;
    }
  }
}
