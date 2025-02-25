<?php

namespace Zoho\ZohoMail\Controller\Adminhtml\AuthSettings;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Exception\LocalizedException;
use Zoho\ZohoMail\Helper\Config;
use Zoho\ZohoMail\Helper\ZConstants;
use Zoho\ZohoMail\Helper\ZohoMailApi as ZohoMailApi;
class Callback extends Action
{
    protected $httpRequest;
    protected $helper;

    protected $resultPageFactory;
    public function __construct(
        Action\Context $context,
        HttpRequest $httpRequest,
        Config $helper,
    ) {
        parent::__construct($context);
        $this->httpRequest = $httpRequest;
        $this->helper = $helper;
    }

    /**
     * Override the method to bypass the secret key requirement for this action.
     *
     * @return bool
     */
    protected function _validateSecretKey()
    {
        return true; // Disable the secret key requirement for this action
    }

    public function execute()
    {
        try {
            // Step 1: Get the authorization code from the OAuth provider's redirect
            $authCode = $this->httpRequest->getParam('code');
            $encoded_state = $this->httpRequest->getParam('state');
            if (!$authCode) {
                throw new LocalizedException(__('Authorization code missing.'));
            }
            $state=explode("::",base64_decode($encoded_state));
            $clientId = $state[0];
            $clientSecret = $state[1];
            $domain = $state[2];
            $storeId = $state[3];
            $form_key = $state[4];
            $completeRedirectUrl = $this->helper->getCallBackUrlForStore($storeId);
            $url = "https://accounts.".ZConstants::$domains[$domain]."/oauth/v2/token?code=".$authCode."&client_id=".$clientId."&client_secret=".$clientSecret."&redirect_uri=".$completeRedirectUrl."&grant_type=authorization_code&state=".$encoded_state;
            $curl = curl_init();
            curl_setopt_array($curl, array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "POST"
		    ));
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            $respObj = json_decode("{}",true);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if($httpcode == '200') {
                $body = json_decode($response,true);
                if(array_key_exists('error',$body)){
                    $respObj["result"] = "error";
                    $respObj["message"] = json_decode($response);
                } else {
                    $zmailApi = new ZohoMailApi($this->helper,$storeId);
                    $this->helper->setStoreConfig('domain',$domain,$storeId);
                    $this->helper->setStoreConfig('client_id',base64_encode($clientId),$storeId);
                    $this->helper->setStoreConfig('client_secret',base64_encode($clientSecret),$storeId);
                    $this->helper->setStoreConfig('refresh_token',base64_encode($body['refresh_token']),$storeId);
                    $this->helper->setStoreConfig('access_token',base64_encode($body['access_token']),$storeId);
                    $this->helper->setStoreConfig('timestamp',time(),$storeId);
                    $respObj["result"] = "success";
                    $this->helper->flushCache();
                    $emailDetail = $zmailApi->getZohoMailAccountDetails($storeId); 
                    $sendMailList = $emailDetail["sendmail_details"];
                    $accountId = $emailDetail["account_id"];
                    $mailAccountID = $this->helper->getStoreConfig('account_id',$storeId);
                    $isMailConfigured = true;
                    if(isset($mailAccountID)) {
                        if($accountId == $mailAccountID) {
                            foreach (ZConstants::$email_types as $email_type) {
                                $fromAddress = $this->helper->getZeptoEmailAddress($email_type['id'],$storeId);
                                if(!in_array($fromAddress,$sendMailList)){
                                    $isMailConfigured = false;
                                }
                            }
                            
                        } else {
                            $isMailConfigured = false;
                        }
                        if(!$isMailConfigured) {
                            $this->helper->deleteAccountConfig($storeId);
                            $this->helper->flushCache();
                        }
                    } 
                    

                }
                
                
            }else{
                $respObj["result"] = "error";
                $respObj["message"] = json_decode($response);
            }
            $this->getResponse()->setBody("<script>window.opener.postMessage(".json_encode($respObj).");window.close();</script>");
            

            return;
        } catch (\Exception $e) {
            // Log any unexpected errors for debugging
            $this->getResponse()->setBody("Error occured".$e);
            return;
            //return $this->resultRedirectFactory->create()->setPath('admin/dashboard/index');
        }
    }

    // Additional methods (getAccessToken, storeAccessToken, etc.) here...
}
