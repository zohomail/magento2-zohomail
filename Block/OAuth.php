<?php
namespace Zoho\ZohoMail\Block;
use Magento\Backend\Model\UrlInterface;
use Zoho\ZohoMail\Helper\ZohoMailApi;

class OAuth extends \Magento\Framework\View\Element\Template
{
    protected $config;
    protected $adminUrl;
    protected $messageManager;
   

    public function __construct(
        UrlInterface $adminUrl,
        \Magento\Framework\View\Element\Template\Context $context,
        \Zoho\ZohoMail\Helper\Config $config,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        
        array $data = []
    ) {
        $this->config = $config;
        $this->adminUrl =$adminUrl;
        $this->messageManager = $messageManager;
        parent::__construct($context, $data);
    }


    public function getDomain()
    {
        return $this->config->getStoreConfig("domain",$this->getStoreId());
    }

    public function getCallBackUrlForStore()
    {
        return  $this->adminUrl->setNoSecret(true)->getUrl('zohomailauth/authsettings/callback')."store/".$this->getStoreId();
    }


    public function getClientId() 
    {
        $clientId = $this->config->getStoreConfig("client_id",$this->getStoreId());
        if(!empty($clientId )) {
            return base64_decode($clientId);
        }
        return '';

    }
    public function getClientSecret() 
    {
        $clientSecret = $this->config->getStoreConfig("client_secret",$this->getStoreId());
        if(!empty($clientSecret )) {
            return base64_decode($clientSecret);
        }
        return '';
    }
    public function isAccountConfigured() 
    {
        $clientSecret = $this->config->getStoreConfig("refresh_token",$this->getStoreId());
        if(!empty($clientSecret )) {
            return true;
        }
        return false;
    }
    public function isEmailConfigured() 
    {
        $accountId = $this->config->getStoreConfig("account_id",$this->getStoreId());
        if(!empty($accountId)) {
            return true;
        }
        return false;
    }
	public function getHostedDomain() {
		return $this->config->getStoreConfig("domain",$this->getStoreId());
	}


    public function getBaseUrl()
    {
        return $this->config->getBaseUrl();
    }

	public function getParams(){
		return $this->getRequest()->getParam('zepto_client_id');
	}
	
	public function getSupportEmail() {
		return $this->config->getTransmailEmailAddress("ident_support");
	}
	public function getTransmailEmailAddress($type,$storeId)
    {
        return $this->config->getZeptoEmailAddress($type,$storeId);
    }
	public function getStoreId() {
		return (int)$this->getRequest()->getParam('store');
	}
    public function getZohoMailAccounts() {
        $zmailApi = new ZohoMailApi($this->config,$this->getStoreId());
        return $zmailApi->getZohoMailAccountDetails();
    }
    public function getMessageManager() {
        return $this->messageManager;
    }
   

  

}
