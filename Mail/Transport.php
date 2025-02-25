<?php

namespace Zoho\ZohoMail\Mail;


use InvalidArgumentException;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Mail\MessageInterface;
use Magento\Framework\Mail\Transport as MagentoTransport;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Phrase;
use Magento\Store\Model\StoreManagerInterface as StoreManagerInterface;

use Psr\Log\LoggerInterface;
use Zend_Mail;
use Magento\Framework\Encryption\EncryptorInterface as EncryptorInterface;
use Zoho\ZohoMail\Helper\Config as OauthConfig;
use Zoho\ZohoMail\Helper\ZeptoAuth as ZeptoAuth;
use Zoho\ZohoMail\Helper\ZohoMailApi as ZohoMailApi;
use Zoho\ZohoMail\Model\AdminNotification as AdminNotification;
use Zoho\ZohoMail\Helper\ZConstants as ZConstants;
class Transport extends MagentoTransport implements TransportInterface
{


    protected $config;
	
	protected $message;
    protected $logger;
	protected $oauthconfig;
	protected $adminNotification;
	protected $parameters;
	protected $storeManager;
	protected $storeId;

    public function __construct(MessageInterface $message,StoreManagerInterface $storeManager, $parameters = null, LoggerInterface $logger = null,OauthConfig $oauthConfig=null)
    {
		$this->message = $message;
		$this->storeManager = $storeManager;
		$this->logger = $logger ?: ObjectManager::getInstance()->get(LoggerInterface::class);
		$this->oauthconfig = $logger ?: ObjectManager::getInstance()->get(OauthConfig::class);
		$this->parameters = $parameters;
		if($storeManager->getStore()){
			$this->storeId =$storeManager->getStore()->getId();
		}
        parent::__construct($message, $parameters);
		
    }

    /**
     * Send a mail using this transport
     *
     * @return void
     */
    public function sendMessage()
    {
		if(!isset($this->storeId)){
			parent::sendMessage();
			return;
		}
		$domain = $this->oauthconfig->getStoreConfig('domain',$this->storeId);
		$account_id = $this->oauthconfig->getStoreConfig('account_id',$this->storeId);
		
		$allowed_emails = json_decode($this->oauthconfig->getStoreConfig('allowed_emails',$this->storeId),true);
		
		if(!isset($account_id)){
			
			parent::sendMessage();
			return;
		}
		$zmailApi = new ZohoMailApi($this->oauthconfig,$this->storeId);
		$body = $this->message->getBody();
        $parsedBody = [
            'text' => '',
			'html' => '',
            'attachments' => []
        ];
		$parsedBody['ishtml'] = true;
		foreach ($body->getParts() as $part) {
			if ($part->getType() == 'text/plain') {
				$parsedBody['text'] .= $part->getRawContent();
				$parsedBody['ishtml'] = false;
			}
			else if ($part->getType() == 'text/html') {
				$parsedBody['html'] .= $part->getRawContent();
            } else {
				$parsedBody['attachments'][] = [
							'content' => $part->getRawContent(),
                            'name' => $part->getFilename(),
                            'mime_type' => $part->getType()
                        ];
            }
                
        }
        
		
		
		$message = $this->message;
		
		
		$to = $cc = $bcc = $reply_to = array();
		$headers = $message->getHeaders();
		
		$fromAddress =$headers['From'];
		$fromNameAndAddress = $this::splitNameAndAddress($fromAddress);

		$mail_data = array();
		if ($headers['From'] !== null) {
			if (!in_array(strtolower($fromNameAndAddress['address']), $allowed_emails)){
				$fromAddress = $this->oauthconfig->getTransmailEmailAddress(ZConstants::ident_general,$this->storeId);
			}
			$mail_data['fromAddress'] =  $fromAddress;
			
        }
		
		if ($headers['To'] !== null) {
			$mail_data['toAddress'] = $headers['To'];
        }
		if (array_key_exists('Cc',$headers)) {
			$mail_data['ccAddress'] = $headers['Cc'];
        }
		if (array_key_exists('Bcc',$headers)) {
			$mail_data['ccAddress'] = $headers['Bcc'];
			
        }
		
		if (array_key_exists('Subject',$headers)) {
			$subject = $headers['Subject'];
			$mail_data['subject'] = $subject;
        }
		if($parsedBody['ishtml']){
			$mail_data['mailFormat'] = 'html';
			$mail_data['content'] = $parsedBody["html"];
		} else {
			$mail_data['mailFormat'] = 'plaintext';
			$mail_data['content'] = $parsedBody["text"];
		}
		if($parsedBody['attachments']){
			$attachmentRes = $zmailApi->uploadAttachment($parsedBody['attachments']);
			$mail_data['attachments'] = json_decode($attachmentRes)->data;
		}

		
		$respObj = $zmailApi->sendMail($mail_data);
		
    }
	
	private function splitNameAndAddress($emailAddress) {
		$result = [
			'name' => '',
			'address' => ''
			];
		$pos = strpos($emailAddress, '<');
		if($pos !== false) {
			$result['address'] =  substr($emailAddress, $pos+1, strlen($emailAddress)-$pos-2);
			$result['name'] = substr($emailAddress,0,$pos-1);
		} else {
			$result['address'] = $emailAddress;
		}
		
		return $result;
	}
	private function getEmailDetails($emailAddressDetails) {
		if(!is_array($emailAddressDetails)){
			$emailAddressDetails = explode( ',', $emailAddressDetails );
		}
		$result = array();
		
		
		foreach($emailAddressDetails as $emailAddress){
			$emailDetail = array();
			$emailDetail["email_address"] = $this::splitNameAndAddress($emailAddress);
			array_push($result,$emailDetail);
		}
		
		return $result;
	}

}
