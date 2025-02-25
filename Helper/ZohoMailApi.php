<?php
namespace Zoho\ZohoMail\Helper;
use Zoho\ZohoMail\Helper\Config;
use Zoho\ZohoMail\Helper\ZConstants;
class ZohoMailApi {
	private $helper;
	private $storeId;
	
	private function getZohoMailUrl() {
		return "https://mail.".ZConstants::$domains[$this->helper->getStoreConfig('domain',$this->storeId)];
	}
	private function getAccountsUrl() {
		return "https://accounts.".ZConstants::$domains[$this->helper->getStoreConfig('domain',$this->storeId)];
	}
	public function __construct(Config $helper,$storeId) {
		$this->helper = $helper;
		$this->storeId = $storeId;
	}
	public function sendMail($mail_data)
	{
		$accountId = $this->helper->getStoreConfig('account_id',$this->storeId);
		return $this->sendZohoMail($mail_data,$accountId);
	}
	public function sendTestMail($mail_data,$accountId)
	{
		return $this->sendZohoMail($mail_data,$accountId);
	}
	public function sendZohoMail($mail_data,$accountId) {
		$responseObj = json_decode('{}');
		$urlToSend = $this->getZohoMailUrl().'/api/accounts/'.$accountId.'/messages';	
		$curl = curl_init();
		curl_setopt_array($curl, array(
				CURLOPT_URL => $urlToSend,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "POST",
				CURLOPT_POSTFIELDS => json_encode($mail_data),
				CURLOPT_HTTPHEADER => array(
				"accept: application/json",
				"authorization: Bearer ".$this->getAccessToken(),
				"cache-control: no-cache",
				"content-type: application/json",
				"User-Agent: MagentoPlugin"
			),
		));
		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		if($httpcode == '200' || $httpcode == '201') {
			$responseObj->result = "success";
			$responseObj->details = json_decode($response);
		}else{
			$responseObj->result ="error";
			$responseObj->details = json_decode($response);
		}
		return $responseObj;
	}
	public function getZohoMailAccounts() {
		$responseObj = json_decode('{}',true);
		$urlToSend = $this->getZohoMailUrl()."/api/accounts";	
		$curl = curl_init();
		$accessToken = $this->getAccessToken();
		$responseObj["access_token"] = $accessToken;
		if(empty($accessToken)) {
			$responseObj["result"] ="error";
			$responseObj["detail"]=json_decode('{"errorCode":"INVALID_OAUTHTOKEN"}');
		}
		curl_setopt_array($curl, array(
				CURLOPT_URL => $urlToSend,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "GET",
				CURLOPT_HTTPHEADER => array(
				"accept: application/json",
				"authorization: Bearer ".$this->getAccessToken(),
				"cache-control: no-cache",
				"content-type: application/json",
				"User-Agent: MagentoPlugin"
			),
		));
		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		if($httpcode == '200' || $httpcode == '201') {
			$responseObj["result"] ="success";
			$responseObj["detail"]=json_decode($response);
		}else{
			$responseObj["result"] ="error";
			$responseObj["detail"]=json_decode($response)->data;
		}
		
		return $responseObj;
	}

	private function getAccessToken() {
		if( !empty($this->helper->getStoreConfig('timestamp',$this->storeId)) && time() - $this->helper->getStoreConfig('timestamp',$this->storeId) > 3000) {
			$url = $this->getAccountsUrl()."/oauth/v2/token?refresh_token=".base64_decode($this->helper->getStoreConfig('refresh_token',$this->storeId))."&client_id=".base64_decode($this->helper->getStoreConfig('client_id',$this->storeId))."&client_secret=".base64_decode($this->helper->getStoreConfig('client_secret',$this->storeId))."&redirect_uri=".$this->helper->getCallBackUrlForStore($this->storeId)."&grant_type=refresh_token";
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
           
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			
			if($httpcode == '200'){
				$body = json_decode($response);
				if(empty($body->error)) {
					$this->helper->setStoreConfig('access_token',base64_encode($body->access_token), $this->storeId);
					$this->helper->setStoreConfig('timestamp',time(), $this->storeId);
					$this->helper->flushCache();
					return $body->access_token;
				}
				
			}
			return null;
			
		}
		else{
			return base64_decode($this->helper->getStoreConfig('access_token',$this->storeId));
		}
	}
	public function getZohoMailAccountDetails() {
        $response = $this->getZohoMailAccounts();
        $emailDetail = array();
        $emailArr = array();
        $accountId = '';
        if($response["result"] === "success") {
            $jsonbodyAccounts = $response["detail"];
            
            for($i=0;$i<count($jsonbodyAccounts->data);$i++)
			{
                $emailData = $jsonbodyAccounts->data[$i];
                if(!empty($emailData->sendMailDetails))
                {
                    $accountId = $jsonbodyAccounts->data[$i]->accountId;
                    for($j=0;$j<count($jsonbodyAccounts->data[$i]->sendMailDetails);$j++) {
                        array_push($emailArr,$jsonbodyAccounts->data[$i]->sendMailDetails[$j]->fromAddress);
                    }
                }
			}
          
        } else {
			$emailDetail['error'] = $response["detail"];
		}
        $emailDetail['account_id'] = $accountId;
        $emailDetail["sendmail_details"] = $emailArr;

        return $emailDetail;
    }
	
	public function uploadAttachment($attachments) {
		$accountId = $this->helper->getStoreConfig('account_id',$this->storeId);
		$boundary = uniqid();
		$eol = "\r\n"; 
		$body = '';
		foreach ($attachments as $file) {
			$body .= '--' . $boundary . $eol;
			$body .= 'Content-Disposition: form-data; name="attach"; filename="' . $file['name'] . '"' . $eol;
			$body .= 'Content-Type: ' . $file['mime_type'] . $eol . $eol;
			$body .= $file['content'] . $eol;
		}
		$body .= '--' . $boundary . '--' . $eol;
		$headers = [
			'Content-Type: multipart/form-data; boundary=' . $boundary,
			'Content-Length: ' . strlen($body),
			"authorization: Bearer ".$this->getAccessToken(),
			"User-Agent: MagentoPlugin"
		];
		$context = stream_context_create([
			'http' => [
				'method' => 'POST',
				'header' => implode("\r\n", $headers),
				'content' => $body
			]
		]);
		
		$response = file_get_contents($this->getZohoMailUrl().'/api/accounts/'.$accountId.'/messages/attachments?uploadType=multipart', false, $context);
		return $response;
	}

	
   
}
	