<?php

namespace Zoho\ZohoMail\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface as ScopeInterface;

class ConfigPlugin
{
	 protected $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }
    public function afterGetValue(ScopeConfigInterface $subject, $result, $path, $scope = null, $scopeCode = null)
    {
        
        $overridePath = array(
							'trans_email/ident_general/email' =>'zohomail/ident_general/email',
							'trans_email/ident_sales/email' =>'zohomail/ident_sales/email',
							'trans_email/ident_support/email' =>'zohomail/ident_support/email',
							'trans_email/ident_custom1/email' =>'zohomail/ident_custom1/email',
							'trans_email/ident_custom2/email' =>'zohomail/ident_custom2/email'
							);

        
        if (array_key_exists($path,$overridePath)) {
            $result = $this->scopeConfig->getValue($overridePath[$path], ScopeInterface::SCOPE_STORES, $scopeCode);
        }

        return $result;
    }
}
