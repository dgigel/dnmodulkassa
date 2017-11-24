<?php
/**
 * This file is part of DnModulKassa module for PrestaShop.
 * @author Daniel Gigel <daniel@gigel.ru>
 */

if (!defined('_PS_VERSION_'))
    exit;

class DnModulKassaHandler
{
    public static function log($log_entry, $log_file = null)
    {
        if ($log_file == null) {
            $log_file = _PS_MODULE_DIR_ . 'dnmodulkassa/logs/dnmodulkassa.log';

            if (!is_dir(_PS_MODULE_DIR_ . 'dnmodulkassa/logs/')) {
                mkdir(_PS_MODULE_DIR_ . 'dnmodulkassa/logs/', 0775, true);
            }
        }
        file_put_contents($log_file, "\n" . date('Y-m-d H:i:sP') . ' : ' . $log_entry, FILE_APPEND);
    }

    public static function isAssociated()
    {
        return Configuration::get('DNMODULKASSA_ASSOCIATE_USER') !== '';
    }

    public static function createAssociation($retailpoint_id, $login, $password, $test_mode)
    {
        $fn_base_url = static::getFnBaseUrlByMode($test_mode);
        $response = static::sendHttpRequest('/v1/associate/' . $retailpoint_id, 'POST', array('username' => $login, 'password' => $password), $fn_base_url);
        if ($response !== false) {
            $associated_login = $response['userName'];
            $associated_password = $response['password'];
            $operating_mode = $response['operating_mode'];
            $retail_point_info = '';
            if ($response['name']) {
                $retail_point_info .= $response['name'];
            }
            if ($response['address']) {
                $retail_point_info .= ' ' . $response['address'];
            }

            Configuration::updateValue('DNMODULKASSA_ASSOCIATE_USER', $associated_login);
            Configuration::updateValue('DNMODULKASSA_ASSOCIATE_PASSWORD', $associated_password);
            Configuration::updateValue('DNMODULKASSA_RETAIL_POINT_INFO', $retail_point_info);

            return array(
                'success' => TRUE,
                'data' => array(
                    'associated_login' => $associated_login,
                    'associated_password' => $associated_password
                )
            );
        } else {
            return array(
                'success' => FALSE,
                'error' => error_get_last()['message']
            );
        }
    }

    public static function removeCurrentAssociation()
    {
        $association_data = static::getAssociationData();
        if ($association_data !== FALSE) {
            Configuration::updateValue('DNMODULKASSA_ASSOCIATE_USER', '');
            Configuration::updateValue('DNMODULKASSA_ASSOCIATE_PASSWORD', '');
            Configuration::updateValue('DNMODULKASSA_RETAIL_POINT_INFO', '');
        } else {
            static::log('ERROR: Модуль не настроен. Невозможно удалить связку.');
        }

    }

    public static function getFnStatus($login, $password, $test_mode)
    {
        $fn_base_url = static::getFnBaseUrlByMode($test_mode);
        $response = static::sendHttpRequest('/v1/status', 'GET', array('username' => $login, 'password' => $password), $fn_base_url);

        if ($response !== false) {
            return array(
                'success' => TRUE,
                'data' => array(
                    'status' => $response['status'],
                    'dateTime' => $response['dateTime']
                )
            );
        } else {
            return array(
                'success' => FALSE,
                'error' => error_get_last()['message']
            );
        }
    }

    public static function getAssociationData()
    {
        $associated_login = Configuration::get('DNMODULKASSA_ASSOCIATE_USER');
        $associated_password = Configuration::get('DNMODULKASSA_ASSOCIATE_PASSWORD');
        if ($associated_login == '') {
            return false;
        } else {
            return array(
                'username' => $associated_login,
                'password' => $associated_password
            );
        }
    }

    private static function sendHttpRequest($url, $method, $auth_data, $fn_base_url, $data = '')
    {
        $encoded_auth = base64_encode($auth_data['username'] . ':' . $auth_data['password']);
        static::log('sendHttpRequest(' . $url . ', ' . $method . ', ' . $encoded_auth);
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . $encoded_auth
        );
        if ($method == 'POST' && $data != '') {
            $headers['Content-Length'] = mb_strlen($data, '8bit');
        }
        $headers_string = '';
        foreach ($headers as $key => $value) {
            $headers_string .= $key . ': ' . $value . "\r\n";
        }
        $options = array(
            'http' => array(
                'header' => $headers_string,
                'method' => $method
            ),
            'https' => array(
                'header' => $headers_string,
                'method' => $method
            )
        );
        if ($method == 'POST' && $data != '') {
            $options['http']['content'] = $data;
        }
        $context = stream_context_create($options);
        static::log("Request: " . $method . ' ' . $fn_base_url . $url . "\n$headers_string\n" . $data);
        $response = file_get_contents($fn_base_url . $url, false, $context);
        if ($response === false) {
            static::log("Error:" . var_export(error_get_last(), true));
            return false;
        }
        static::log("\nResponse:\n" . var_export($response, true));
        return json_decode($response, true);
    }

    private static function getFnBaseUrl()
    {
        $test_mode = Configuration::get('DNMODULKASSA_TEST_MODE');
        return static::getFnBaseUrlByMode($test_mode);
    }

    private static function getFnBaseUrlByMode($test_mode)
    {
        if ((int)$test_mode == 1) {
            return 'https://demo-fn.avanpos.com/fn';
        } else {
            return 'https://service.modulpos.ru/api/fn';
        }
    }
}