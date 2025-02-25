<?php 
namespace Zoho\ZohoMail\Controller\Adminhtml\AuthSettings; 
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\App\Action\HttpGetActionInterface as HttpGetActionInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfigInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\Area;
use Magento\Store\Model\StoreManagerInterface;
use Zoho\ZohoMail\Helper\Config;
use Zoho\ZohoMail\Helper\ZConstants;
use Zoho\ZohoMail\Helper\ZohoMailApi as ZohoMailApi;
use Zoho\ZohoMail\Model\AdminNotification as AdminNotification;
class Index extends \Magento\Backend\App\Action implements HttpGetActionInterface, HttpPostActionInterface {
protected $resultPageFactory = false;
protected $helper;
protected $transportBuilder;
protected $storeManager;
protected $adminNotification;
	public function __construct(
		\Magento\Backend\App\Action\Context $context,
		\Magento\Framework\View\Result\PageFactory $resultPageFactory,
		Config $helper,
		TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
		AdminNotification $adminNotification
	)
	{
		parent::__construct($context);
		$this->helper = $helper;
		$this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
		$this->resultPageFactory = $resultPageFactory;
		$this->adminNotification = $adminNotification;
	}
	public function execute()
	{
		$storeId = (int)$this->getRequest()->getParam('store');
		 
		$resultPage = $this->resultPageFactory->create();
		$params = $this->getRequest()->getParams(); //get params
		$postData = $this->getRequest()->isPost();
		if($postData){
			$respObj = json_decode("{}",true);
			
			if($params['options'] == 'saveEmailSettings'){
				$failedEmails = array();
				$zmailApi = new ZohoMailApi($this->helper,$storeId);
				$emailDetail = $zmailApi->getZohoMailAccountDetails($storeId); 
				$sendMailList = $emailDetail["sendmail_details"];
				$accountId = $emailDetail["account_id"];
				
				if(empty($accountId)){
					$respObj["result"] = "failure";
					$respObj["error_message"] = "Zoho Mail account not exist";
				}
				else{
				foreach (ZConstants::$email_types as $email_type) {
					//checking email is available in send mail details
					if(in_array($params[$email_type['param_name']],$sendMailList)) {
						$mailRes = $this->sendMail($params[$email_type['param_name']],$email_type['type'],$zmailApi,$accountId);
						if(!empty($mailRes) && $mailRes->result !== "success"){
							$respObj["result"] = "failure";
							$failedDetails = ['type'=>$email_type['param_name'],'error'=>$mailRes->details];
							array_push($failedEmails,$failedDetails);
						}
						
					}
					else {
						$error_data = array(
							'data' => array('moreInfo' => 'email not available')
						);
						
						
						$failedDetails = ['type'=>$email_type['param_name'],'error'=> $error_data];
						array_push($failedEmails,$failedDetails);
					}
					
				}
				
				if(count($failedEmails) == 0 && !isset($respObj['error_message'])){
					$respObj["result"] = "success";
					
					$this->helper->setStoreConfig('account_id',$accountId,$storeId);
					$allowedEmails = array();
					foreach (ZConstants::$email_types as $email_type) {
						$this->helper->setZeptoEmailConfig($email_type['id'],$params[$email_type['param_name']],$storeId);
						array_push($allowedEmails,strtolower($params[$email_type['param_name']]));
					}
					$this->helper->setStoreConfig('allowed_emails',json_encode($allowedEmails),$storeId);
					$this->helper->setStoreConfig('failed_mails',json_encode([]),$storeId);
				}
				else{
					$respObj["result"] = "failure";
					$respObj["email_error"] = $failedEmails;
				}
				}
				
				
				$this->getResponse()->setBody(json_encode($respObj));
				$this->helper->flushCache();
				return;
			} else if($params['options'] == 'authorize'){
				$respObj["result"] = "success";
				$clientId = $params["clientId"];
				$clientSecret = $params["clientSecret"];
				$domain =$params["domain"];
				$state=base64_encode($clientId."::".$clientSecret."::".$domain."::".$storeId."::".$params['form_key']);
				$respObj["authorize_url"] = "https://accounts.".ZConstants::$domains[$params["domain"]]."/oauth/v2/auth?client_id=".$clientId."&response_type=code&access_type=offline&prompt=consent&redirect_uri=".$this->helper->getCallBackUrlForStore($storeId)."&state=".$state."&scope=".ZConstants::mail_scopes;
				
				$this->helper->setStoreConfig('form_key',$params['form_key'],$storeId);
				$this->getResponse()->setBody(json_encode($respObj));
				return;
			} else if($params['options'] == 'testOauthSettings'){
				$failedEmails = array();
				$zmailApi = new ZohoMailApi($this->helper,$storeId);
				$emailDetail = $zmailApi->getZohoMailAccountDetails(); 
				$sendMailList = $emailDetail["sendmail_details"];
				$accountId = $emailDetail["account_id"];
				$respObj["result"] = "success";
				if(empty($accountId)){
					$respObj["result"] = "failure";
					$respObj["error_message"] = "Zoho Mail account not exist";
				}
				else {
					foreach (ZConstants::$email_types as $email_type) {
						$fromAddress = $this->helper->getZeptoEmailAddress($email_type['id'],$storeId);
						$mailRes = $this->sendMail($fromAddress,$email_type['type'],$zmailApi,$accountId);
						
						if(!empty($mailRes) && $mailRes->result !== "success"){
							$respObj["result"] = "failure";
							$failedDetails = ['type'=>$email_type['param_name'],'error'=>$mailRes->details];
							array_push($failedEmails,$failedDetails);
						}
					}
					if(count($failedEmails)>0){
						$respObj["email_error"] = $failedEmails;
					}
				}
				$this->getResponse()->setBody(json_encode($respObj));
				return;
			}

		}else{
			$ids = $this->storeManager->getStores();
			if(array_key_exists($storeId,$ids)){
				$resultPage->getConfig()->getTitle()->prepend((__('ZohoMail Settings')));
			}else{
				$params = $this->getRequest()->getParams();
				$params['store'] = array_key_first($ids);
				if(isset($ids) && count($ids)>0) {
					$resultRedirect = $this->resultRedirectFactory->create();
					$resultRedirect->setPath('zohomailauth/authsettings/', $params);
					return $resultRedirect;
				}
				
			}
			
		}

		return $resultPage;
	}
	
    public function sendMail($fromAddress,$type,$zeptoMailApi,$accountId)
    {
		$mail_data = array();
		$from = json_decode('{}');
		$from->address = $fromAddress;
		$emailDetail = array();
		$toArray = array();
		
		$mail_data['fromAddress'] = $fromAddress;
		$mail_data['toAddress'] = $fromAddress;

		$mail_data['subject'] = "This is a test email for the '". $type ."'category.";
		$mail_data['content'] = "This is a test email for the <b><i> ". $type ."</i></b> category.";
		
		
		return $zeptoMailApi->sendTestMail($mail_data,$accountId);
        
    }
	 protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(static::ADMIN_RESOURCE);
    }


	
}