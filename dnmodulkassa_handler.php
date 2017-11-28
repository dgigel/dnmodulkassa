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
        if ((int)ConfigurationCore::get('DNMODULKASSA_LOGS_MODE') == 1) {
            if ($log_file == null) {
                $log_file = _PS_MODULE_DIR_ . 'dnmodulkassa/logs/dnmodulkassa.log';

                if (!is_dir(_PS_MODULE_DIR_ . 'dnmodulkassa/logs/')) {
                    mkdir(_PS_MODULE_DIR_ . 'dnmodulkassa/logs/', 0775, true);
                }
            }
            file_put_contents($log_file, "\n" . date('Y-m-d H:i:sP') . ' : ' . $log_entry, FILE_APPEND);
        }
    }

    public static function isAssociated()
    {
        return Configuration::get('DNMODULKASSA_ASSOCIATE_USER') !== '';
    }

    public static function createAssociation($retailpoint_id, $login, $password)
    {
        $fn_base_url = static::getFnBaseUrl();
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

    public static function getStatus($login, $password)
    {
        $fn_base_url = static::getFnBaseUrl();
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
        if (!$associated_login OR !$associated_password) {
            return false;
        } else {
            return array(
                'username' => $associated_login,
                'password' => $associated_password
            );
        }
    }

    public static function createDoc($order, $doc_type, $payment_type, $print_receipt, $contact)
    {
        if (!is_object($order))
            $order = new Order((int)$order);

        $dateTime = new DateTime('NOW');
        $doc = array(
            'id' => $order->id . '-' . uniqid(),
            'checkoutDateTime' => $dateTime->format(DATE_RFC3339),
            'docNum' => (string)$order->id,
            'docType' => $doc_type,
            'printReceipt' => (bool)$print_receipt,
            'email' => $contact,
            'moneyPositions' => array(array(
                'paymentType' => $payment_type,
                'sum' => floatval(number_format($order->total_paid, 2, '.', ''))
            ))
        );

        $doc['responseURL'] = static::getResponseUrl(array('doc_id' => $doc['id'], 'token' => static::createToken($doc['id'])));

        $products = $order->getProductsDetail();

        if (count($products) == 0)
            return false;

        $vatTag = Configuration::get('DNMODULKASSA_VAT_TAG');
        $inventPositions = array();
        foreach ($products as $product) {
            $product_name = Product::getProductName((int)$product['id_product']);
            $inventPositions[] = static::createInventPosition(
                (strlen(trim($product['product_reference'])) > 0 ? $product_name . ' ' . $product['product_reference'] : $product_name),
                $product['unit_price_tax_incl'],
                $product['product_quantity'],
                $vatTag);
        }

        if ($order->total_discounts > 0) {
            if ($inventPositions[0]['quantity'] > 1) {
                $inventPositions[0]['quantity'] -= 1;
                array_unshift($inventPositions, static::createInventPosition(
                    $inventPositions[0]['name'],
                    $inventPositions[0]['price'],
                    1,
                    $vatTag
                ));
            }

            $discount_percent = $order->total_discounts / $order->total_products;
            $total_discounts = 0;
            foreach ($inventPositions as &$position) {
                $position['discSum'] = floatval(number_format($position['price'] * $discount_percent, 2, '.', ''));
                $total_discounts += $position['discSum'] * $position['quantity'];
            }

            if ($order->total_discounts != $total_discounts) {
                $diff = floatval(number_format($order->total_discounts - $total_discounts, 2, '.', ''));
                $inventPositions[0]['discSum'] = floatval(bcadd($inventPositions[0]['discSum'], $diff, 2));
            }
        }

        if ($order->total_shipping > 0)
            $inventPositions[] = static::createInventPosition('ДОСТАВКА', $order->total_shipping, 1, $vatTag);

        $doc['inventPositions'] = $inventPositions;

        return $doc;
    }

    private static function createInventPosition($name, $price, $quantity, $vatTag, $discSum = 0)
    {
        return array(
            'name' => trim($name),
            'price' => floatval(number_format($price, 2, '.', '')),
            'quantity' => (int)$quantity,
            'vatTag' => (int)$vatTag,
            'discSum' => floatval($discSum)
        );
    }

    public static function sendDoc($doc)
    {
        if (!is_array($doc))
            return false;

        $doc_json = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $association_data = static::getAssociationData();
        if (!$association_data)
            return false;

        $response = static::sendHttpRequest('/v1/doc', 'POST', $association_data, static::getFnBaseUrl(), $doc_json);

        if ($response === FALSE)
            static::log('sendDoc error:' . var_export(error_get_last(), TRUE));

        return $response;
    }

    public static function getDocStatus($doc_id)
    {
        $fn_base_url = static::getFnBaseUrl();
        $association_data = static::getAssociationData();
        if (trim($association_data['username']) == '' OR trim($association_data['password']) == '')
            return false;

        $doc_id = trim($doc_id);

        $response = static::sendHttpRequest('/v1/doc/' . $doc_id . '/status', 'GET', $association_data, $fn_base_url);

        if ($response !== false) {
            return array(
                'success' => TRUE,
                'data' => array(
                    'status' => $response['status'],
                    'fnState' => $response['fnState'],
                    'fiscalInfo' => $response['fiscalInfo']
                )
            );
        } else {
            return array(
                'success' => FALSE,
                'error' => error_get_last()['message']
            );
        }
    }

    private static function createToken($document_number)
    {
        return md5(Configuration::get('DNMODULKASSA_SECRET') . '$' . $document_number);
    }

    public static function validateToken($token, $document_number)
    {
        return trim($token) == static::createToken($document_number);
    }

    private static function sendHttpRequest($url, $method, $auth_data, $fn_base_url, $data = '')
    {
        $encoded_auth = base64_encode($auth_data['username'] . ':' . $auth_data['password']);
        static::log('sendHttpRequest(' . $url . ', ' . $method . ', ' . $encoded_auth);
        $headers = array(
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . $encoded_auth
        );
        if ($method == 'POST' && $data != '') {
            $headers['Content-Length'] = mb_strlen($data, '8bit');
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $fn_base_url . $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        if ($method == 'POST')
            curl_setopt($curl, CURLOPT_POST, 1);
        if ($method == 'POST' && $data != '')
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        static::log("Request: " . $method . ' ' . $fn_base_url . $url . "\nHeaders: " . var_export($headers, true) . "\n" . $data);
        $response = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false) {
            static::log("Error:" . var_export(error_get_last(), true));
            return false;
        }

        if ($code >= 400) {
            static::log("\nError. Response: " . var_export($response, true));
            return false;
        }

        static::log("\nResponse: " . var_export($response, true));
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

    public static function getResponseUrl($params = false)
    {
        if (!is_array($params))
            $params = array();

        $link = new Link();
        return $link->getModuleLink('dnmodulkassa', 'response', $params, (int)Configuration::get('PS_SSL_ENABLED'));
    }
}
