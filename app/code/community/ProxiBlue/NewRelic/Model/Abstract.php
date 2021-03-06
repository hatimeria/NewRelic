<?php

/**
 *    This file is part of ProxiBlue NewRelic Module  available via GitHub https://github.com/ProxiBlue/NewRelic
 *
 *    ProxiBlue NewRelic Module is free software: you can redistribute it and/or modify
 *    it under the terms of the GNU General Public License as published by
 *    the Free Software Foundation, either version 3 of the License, or
 *    (at your option) any later version.
 *
 *    ProxiBlue NewRelic Module is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU General Public License for more details.
 *
 *    You should have received a copy of the GNU General Public License
 *    along with ProxiBlue NewRelic Module.
 *    If not, see <http://www.gnu.org/licenses/>.
 * */
?>
<?php

/**
 * Base functions to communicate with new relic
 *
 * @category   ProxiBlue
 * @package    ProxiBlue_NewRelic
 * @author     Lucas van Staden (support@proxiblue.com.au)
 * */
class ProxiBlue_NewRelic_Model_Abstract
{

    protected $_eventType = 'Unknown';
    protected $_headers;
    protected $_account_Id;
    protected $_api_key;
    protected $_data_key;
    protected $_enabled = true;
    protected $_userAgentString = 'ProxiBlue NewRelic for Magento';
    protected $_internalLink;

    public function __construct()
    {
        $this->_internalLink = Mage::getStoreConfig('newrelic/api/internal_link') ?: 'http://internal.hatimeria.com/';
        $this->_userAgentString .= '/' . $this->getExtensionVersion();
        $this->_userAgentString .= ' (https://github.com/hatimeria/NewRelic)';
        try {
            $this->setDefaults();
        } catch (Mage_Core_Model_Store_Exception $e) {
            // cannot determine default store id (??)
            // try and load by default store id
            $this->setDefaults(0);
        } catch (Exception $e) {
            $this->_enabled = false;
            Mage::logException($e);
        }
        if (empty($this->_account_Id) || empty($this->_api_key) || empty($this->_data_key)) {
            $this->_enabled = false;
        }
    }

    /**
     * Try and load the newrelic configuration for store
     * @param type $store
     */
    private function setDefaults($store = null)
    {
        $this->_account_Id = Mage::getStoreConfig('newrelic/api/account_id', $store);
        $this->_api_key = Mage::getStoreConfig('newrelic/api/api_key', $store);
        $this->_data_key = Mage::getStoreConfig('newrelic/api/data_access_key', $store);
    }

    /**
     * If configuration is not set, this will result in false
     * @return type
     */
    public function getEnabled()
    {
        return $this->_enabled;
    }

    /**
     * Record index events via curl
     * @param string $message
     * @param Varien_Event $event
     * @return \ProxiBlue_NewRelic_Model_Observer
     */
    public function recordEvent($message, $event = null)
    {
        if (!Mage::getStoreConfig('newrelic/api/internal_key')) {
            Mage::log('Cannot record newrelic event, missing internal proxy secret key');
            return;
        }

        $type = $this->_eventType . ": " . $message;
        $user = $this->getCurrentUser($event);
        $data = "deployment[revision]={$type}&";
        $data .= "deployment[description]={$type}&";
        $data .= "deployment[changelog]={$type}&";
        $data .= "deployment[user]={$user}";
        $data .= '&key=' . Mage::getStoreConfig('newrelic/api/internal_key');
        try {
            $response = $this->talkToNewRelic($data);
            if (Mage::app()->getStore()->isAdmin() && !empty($response) && $response['success']) {
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__("Event recorded in NewRelic : " . $type)
                );
            }
            elseif(!empty($response) && !$response['success']) {
                Mage::log(['url' => $this->getInternalDeploymentLink(), 'request' => $data,'response' => $response], null, 'newrelic.log');
            }
        } catch (Exception $e) {
            throw ProxiBlue_NewRelic_Exception($e);
        }
        return $this;
    }

    /**
     * Get the current user, be it admin or frontend
     *
     * @param Varien_Event $event
     * @return string
     */
    public function getCurrentUser(Varien_Event $event = null)
    {
        if (Mage::app()->getStore()->isAdmin()) {
            if (is_object(Mage::getSingleton('admin/session')->getUser())) {
                return Mage::getSingleton('admin/session')->getUser()->getEmail();
            } else {
                return $this->getDeployerScript($event);
            }
        } else {
            if (Mage::getSingleton('customer/session')->isLoggedIn()) {
                return Mage::getSingleton('customer/session')->getCustomer()->getId() . ' / ' . Mage::getSingleton(
                        'customer/session'
                    )->getCustomer()->getEmail();
            } else {
                return 'Guest User - not Logged in';
            }
        }
    }

    /**
     * Talk to NewRelic API
     *
     * @param array $data
     * @return boolean|array
     */
    public function talkToNewRelic($data)
    {
        $http = new Varien_Http_Adapter_Curl();
        $http->setConfig(['timeout' => 2]);
        $http->write(Zend_Http_Client::POST, $this->getInternalDeploymentLink(), '1.1', ['Content-Type: application/x-www-form-urlencoded'], $data);
        $response = $http->read();
        $response = Zend_Http_Response::extractBody($response);
        try {
            $response = Zend_Json_Decoder::decode($response);
        }
        catch (Exception $e) {
            $response = false;
        }

        return $response;
    }

    public function getExtensionVersion()
    {
        return (string)Mage::getConfig()->getNode()->modules->ProxiBlue_NewRelic->version;
    }

    /**
     * @param Varien_Event|null $event
     * @return mixed|string
     */
    protected function getDeployerScript(Varien_Event $event = null)
    {
        if (Mage::registry('current_cron_job')) {
            return Mage::registry('current_cron_job');
        }
        $eventName = $event ? 'Event: ' . $event->getName() : null;

        return 'Shell Script: ' . $_SERVER["SCRIPT_FILENAME"]. '; ' . $eventName;
    }

    /**
     * @return string
     */
    protected function getInternalDeploymentLink()
    {
        return $this->_internalLink . 'api/newrelic/deployment';
    }

}
