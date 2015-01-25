<?php

include_once __DIR__ . '/../sdk/GSSDK.php';
if (defined('COMPILER_INCLUDE_PATH')) {
  include_once 'Gigya_Social_sdk_gigyaCMS.php';
} else {
  include_once __DIR__ . '/../sdk/gigyaCMS.php';
}


class Gigya_Social_Helper_Data extends Mage_Core_Helper_Abstract
{

    private $apiKey;
    private $apiSecret;
    private $apiDomain;
    private $userKey = null;
    private $userSecret = null;
    public  $utils;
    private $userMod;
    const CHARS_PASSWORD_LOWERS = 'abcdefghjkmnpqrstuvwxyz';
    const CHARS_PASSWORD_UPPERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const CHARS_PASSWORD_DIGITS = '23456789';
    const CHARS_PASSWORD_SPECIALS = '!$*-.=?@_';



    public function __construct() {
        $this->apiKey = Mage::getStoreConfig('gigya_global/gigya_global_conf/apikey');
        $this->apiSecret = Mage::getStoreConfig('gigya_global/gigya_global_conf/secretkey');
        $this->apiDomain = Mage::getStoreConfig('gigya_global/gigya_global_conf/dataCenter');
        $this->userKey = Mage::getStoreConfig('gigya_global/gigya_global_conf/userKey');
        $this->userSecret = Mage::getStoreConfig('gigya_global/gigya_global_conf/userSecret');
        $use_user_key = $this->userSecret = Mage::getStoreConfig('gigya_global/gigya_global_conf/useUserKey');
        $debug = Mage::getStoreConfig('gigya_global/gigya_global_conf/debug_log');
        $this->utils = new GigyaCMS($this->apiKey, $this->apiSecret, $this->apiDomain, $this->userSecret, $this->userKey, $use_user_key, $debug);
        $this->userMod = Mage::getStoreConfig('gigya_login/gigya_user_management/login_modes');
    }
    public function _getPassword($length = 8)
    {
        $chars = self::CHARS_PASSWORD_LOWERS
            . self::CHARS_PASSWORD_UPPERS
            . self::CHARS_PASSWORD_DIGITS
            . self::CHARS_PASSWORD_SPECIALS;
        $str = Mage::helper('core')->getRandomString($length, $chars);
        return 'Gigya_' . $str;
    }

    public function notifyRegistration($gigyaUid, $siteUid)
    {
        $params = array(
            'UID' => $gigyaUid,
            'siteUID' => $siteUid,
        );
        try {
            $res = $this->_gigya_api('notifyRegistration', $params);
        } catch (Exception $e) {
            $code = $e->getCode();
            $message = $e->getMessage();
            Mage::logException($e);
        }
    }

    public function notifyLogin($siteUid, $newUser = 'false', $userInfo = array())
    {
        $params = array(
            'siteUID' => $siteUid,
            'newUser' => $newUser,
        );
        if (!empty($userInfo)) {
            $params['userInfo'] = Mage::helper('core')->jsonEncode($userInfo);
        }
        try {
            $res = $this->_gigya_api('notifyLogin', $params);
            if (is_array($res) && $res["errorCode"] === 0) {
                setcookie($res["cookieName"], $res["cookieValue"], 0, $res["cookiePath"], $res["cookieDomain"]);
            } else {
                Mage::logException($res);
            }
        } catch (Exception $e) {
            $code = $e->getCode();
            $message = $e->getMessage();
            Mage::logException($e);
        }
    }

    /*
     * Logout user from Gigya
     * called by notify_logout customer observer
     * @param int $siteUid
     */
    public function notifyLogout($siteUid)
    {
        $params = array(
            'UID' => $siteUid,
        );
        try {
            $this->_gigya_api('logout', $params);
        } catch (Exception $e) {
            $code = $e->getCode();
            $message = $e->getMessage();
            Mage::logException($e);
        }
    }

    public function deleteAccount($gigyaUid)
    {
        $params = array(
            'UID' => $gigyaUid,
        );
        try {
            $res = $this->_gigya_api('deleteAccount', $params);
        } catch (Exception $e) {
            $code = $e->getCode();
            $message = $e->getMessage();
            Mage::logException($e);
        }
    }


    /**
     * Helper function that handles Gigya API calls.
     *
     * @param mixed $method
     *   The Gigya API method.
     * @param mixed $params
     *   The method parameters.
     *
     * @return array
     *   The Gigya response.
     */
    public function _gigya_api($method, $params)
    {
        $params['format'] = 'json';
        try {
            $response = $this->utils->call($method, $params);
            // If wrong data center resend to right one
        } catch (Exception $e) {
            $code = $e->getCode();
            $message = $e->getMessage();
            Mage::log($message);
            return $code;
        }
        return $response;
    }

  /**
   * Return the store config for a plugin.
   *
   * @param string $pluginName (plugin configuration path)
   * @param string $format
   * @param bool $feed
   *
   * @return array/json
   */
  public function getPluginConfig($pluginName, $format = 'json', $feed = FALSE)
    {
        $config = Mage::getStoreConfig($pluginName);
        //fix the magento yes/no as 1 or 0 so it would work in as true/false in javascript
        foreach ($config as $key => $value) {
            if ($value === '0' || $value === '1') {
                $config[$key] = ($value) ? true : false;
            }
        }
        // New comments can be overridden in advanced config
        if ($pluginName == 'gigya_comments/gigya_comments_conf' || $pluginName = 'gigya_r_and_r/gigya_r_and_r_conf') {
            $config['version'] = 2;
        }
        // Format advanced config
        if (!empty($config['advancedConfig'])) {
            $isJson = $this->_advancedConfFormat($config['advancedConfig']); // is advanced conf in json or key|val format
            if ($isJson) {
                $advConfig = json_decode($config['advancedConfig'], true);
            } else {
                $advConfig = $this->_confStringToArry($config['advancedConfig']);
            }
            // unify boolean values
            foreach ($advConfig as $key => $val) {
                $advConfig[$key] = $this->_string_to_bool($val);
            }
            $config = $advConfig + $config;
        }
        unset($config['advancedConfig']);
        //
        if ($feed === TRUE) {
            $config['privacy'] = Mage::getStoreConfig('gigya_activityfeed/gigya_activityfeed_conf/privacy');
        }
        if ($format === 'php') {
            return $config;
        }
        return Mage::helper('core')->jsonEncode($config);
    }

    /*
     * Check advanced config format
     * @param string $advancedConfig
     * @return bool $json
     */
    protected function _advancedConfFormat($advancedConfig) {
        if (substr($advancedConfig, 0, 1) === '{') {
            $json = true;
        } else {
            $json = false;  // advanced config is in deprecated key|val format
        }
        return $json;
    }

    public function getPluginContainerID($pluginName)
    {
        return Mage::getStoreConfig($pluginName . '/containerID');
    }

    public function isPluginEnabled($pluginName)
    {
        return (bool) Mage::getStoreConfig($pluginName . '/enable');
    }

    public function isShareBarEnabled($place)
    {
        return Mage::getStoreConfig('gigya_share/gigya_sharebar/enable_' . $place);
    }

    public function isShareActionEnabled($place)
    {
        return Mage::getStoreConfig('gigya_share/gigya_share_action/enable_' . $place);
    }

    public function isCountersEnabled()
    {
        return (bool) Mage::getStoreConfig('gigya_global/gigya_global_conf/counters');
    }

    public function isGmNotifyActionEnabled()
    {
        return (bool) Mage::getStoreConfig('gigya_gamification/gigya_gamification_conf/purchaseAction');
    }

    public function isFollowbarEnabled()
    {
        return (bool) Mage::getStoreConfig('gigya_followbar/gigya_followbar_conf/enable');
    }

    public function getExtensionVersion()
    {
        return (string)Mage::getConfig()->getNode()->modules->Gigya_Social->version;
    }

    public function getUserMod()
    {
        if (empty($this->userMod)) {
            $this->userMod = Mage::getStoreConfig('gigya_login/gigya_user_management/login_modes');
        }
        return $this->userMod;

    }

    /*
     * convert deprecated key|val advanced config format to ass array
     * @param string $str
     * @return array $lines
     */
    public function _confStringToArry($str)
    {
        $lines = array();
        $str = str_replace("\r\n", "\n", $str);
        $values = explode("\n", $str);
        //some clean up
        $values = array_map('trim', $values);
        $values = array_filter($values, 'strlen');
        foreach ($values as $value) {
            preg_match('/(.*)\|(.*)/', $value, $matches);
            $lines[$matches[1]] = $matches[2];
        }
        return $lines;
    }

    public function _string_to_bool($str)
    {
        if ($str === 'true' || $str === 'false') {
            return (bool)$str;
        }
        return $str;
    }

    public function call($method, $params)
    {
        return $this->utils->call($method, $params);
    }

    public function  getUtils() {
        return $this->utils;
    }
    public function getGigGlobalAdvancedConfig($advanced_config) {
      $array = json_decode($advanced_config);
      return $array;
    }

}
