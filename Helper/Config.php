<?php

namespace Zoho\ZohoMail\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface as ScopeInterface;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\Cache\TypeListInterface;

class Config extends AbstractHelper
{

    protected $scopeConfig;
	protected $urlInterface;
	protected $configWriter;
	protected $helperBackend;
	protected $cacheTypeList;
	protected $cacheFrontendPool;
	protected $logger;
	protected $reinitableConfig;
	

    /**
     * @param Context $context
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\UrlInterface $urlInterface,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        \Magento\Backend\Helper\Data $helperBackend,
		\Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
		Pool $cacheFrontendPool,
		\Psr\Log\LoggerInterface $logger,
		\Magento\Framework\App\Config\ReinitableConfigInterface $reinitableConfig
    )
	{
		$this->scopeConfig = $scopeConfig;
		$this->urlInterface = $urlInterface;
		$this->configWriter = $configWriter;
		$this->helperBackend = $helperBackend;
		$this->cacheTypeList = $cacheTypeList;
		$this->cacheFrontendPool = $cacheFrontendPool;
		$this->logger = $logger;
		$this->reinitableConfig = $reinitableConfig;
    }

    public function getStoreConfig($config, $storeId = null)
    {
		return $this->scopeConfig->getValue('zohomail/mail/' . $config,ScopeInterface::SCOPE_STORES,$storeId);
    }
	public function setStoreConfig($config, $value,$storeId = null)
    {
		$this->configWriter->save('zohomail/mail/' . $config, $value,ScopeInterface::SCOPE_STORES,$storeId);
    }
	
	public function getTransmailEmailAddress($type,$storeId = null){
		return $this->scopeConfig->getValue('trans_email/'.$type.'/email',ScopeInterface::SCOPE_STORES,$storeId);
	}
	public function setZeptoEmailConfig($type, $value,$storeId)
    {
		$this->configWriter->save('zohomail/'.$type.'/email', $value,ScopeInterface::SCOPE_STORES,$storeId);
    }
	public function getZeptoEmailAddress($type,$storeId=null){
		
		return $this->scopeConfig->getValue('zohomail/'.$type.'/email',ScopeInterface::SCOPE_STORES,$storeId);
	}
	public function getBaseUrl()
    {
        return  $this->urlInterface->getBaseUrl();
    }
	public function flushCache($from = "")
    {
		$this->reinitableConfig->reinit();
    }
	public function getCallBackUrlForStore($storeId)
    {
        return  $this->urlInterface->setNoSecret(true)->getUrl('zohomailauth/authsettings/callback')."store/".$storeId;
    }
	public function deleteAccountConfig($storeId = null)
    {
		$this->configWriter->delete('zohomail/mail/account_id',ScopeInterface::SCOPE_STORES,$storeId);
		foreach (ZConstants::$email_types as $email_type) {
			$this->configWriter->delete('zohomail/'.$email_type['type'].'/email',ScopeInterface::SCOPE_STORES,$storeId);
		}
    }

}
