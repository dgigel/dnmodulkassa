<?php
/**
 * МодульКасса: модуль для PrestaShop.
 *
 * @author    Daniel Gigel <daniel@gigel.ru>
 * @author    Maksim T. <zapalm@yandex.com>
 * @copyright 2017 Daniel Gigel
 * @link      https://prestashop.modulez.ru/ru/third-party-data-integration/55-prestashop-and-modulkassa-integration.html Домашняя страница модуля
 * @license   https://ru.bmstu.wiki/MIT_License Лицензия MIT
 */

if (false === defined('_PS_VERSION_')) {
    exit;
}

$vendorAutoloader = __DIR__ . '/vendor/autoload.php';
if (false === file_exists($vendorAutoloader)) {
    $vendorAutoloader = __DIR__ . '/../../vendor/autoload.php';
}
/** @noinspection PhpIncludeInspection */
require_once $vendorAutoloader;
