<?php

namespace Omnipay\Novalnet\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Novalnet\Helpers\RedirectEncode;
use SimpleXMLElement;

/**
 * @method RedirectCompletePurchaseResponse send()
 */
class RedirectCompletePurchaseRequest extends RedirectPurchaseRequest
{
    public $endpoint = 'https://payport.novalnet.de/nn_infoport.xml';

    /**
     * {@inheritdoc}
     */
    public function getData()
    {
        $this->validate(
            'vendorId',
            'vendorAuthcode',
            'productId'
        );

        $data = array(
            'vendor_id' => $this->getVendorId(),
            'vendor_authcode' => $this->getVendorAuthcode(),
            'test_mode' => $this->getTestMode(),
            'request_type' => 'TRANSACTION_STATUS',
            'product_id' => $this->getProductId(),
            'tid' => $this->getTransactionReference(),
        );

        return $data;
    }

    public function getTransactionReference()
    {
        return $this->httpRequest->get('tid');
    }

    /**
     * {@inheritdoc}
     */
    public function sendData($data)
    {
        $postData = $this->httpRequest->request->all();

        if (isset($postData['payment_error'])) {
            throw new InvalidResponseException(
                $postData['payment_error'] . ': ' .
                $postData['status_text'] . '(' . $postData['status'] .')'
            );
        }

        // For encoded parameters, check the hash
        if ($this->shouldEncode()) {
            $validHash = RedirectEncode::checkHash((array) $postData, $this->getPaymentKey());
            if (!$validHash) {
                throw new InvalidResponseException('Invalid hash');
            }

            return new RedirectCompletePurchaseResponse($this, (object) $postData);
        }

        // build xml
        $xml = new SimpleXMLElement('<nnxml></nnxml>');
        $subElement = $xml->addChild('info_request');
        $this->arrayToXml($data, $subElement);

        // send request
        $httpResponse = $this->httpClient->post($this->endpoint, null, $xml->asXML())->send();

        // return response
        return $this->response = new RedirectCompletePurchaseResponse($this, $httpResponse->xml());
    }

    public function getStatusText()
    {
        return $this->httpRequest->get('status_text');
    }

    public function getStatusCode()
    {
        return $this->httpRequest->get('status');
    }

    private function arrayToXml($array, &$xml_user_info)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (!is_numeric($key)) {
                    $subnode = $xml_user_info->addChild("$key");
                    $this->arrayToXml($value, $subnode);
                } else {
                    $subnode = $xml_user_info->addChild("item$key");
                    $this->arrayToXml($value, $subnode);
                }
            } else {
                $xml_user_info->addChild("$key", htmlspecialchars("$value"));
            }
        }
    }
}
