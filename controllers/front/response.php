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

/**
 * Контроллер для обработки коллбэк-запросов от API сервиса МодульКассы.
 *
 * @author Daniel Gigel <daniel@gigel.ru>
 */
class DnModulKassaResponseModuleFrontController extends ModuleFrontController
{
    /**
     * @inheritDoc
     *
     * @author Daniel Gigel <daniel@gigel.ru>
     */
    public function initContent()
    {
        $this->disableColumns();

        $token = Tools::getValue('token');
        $doc_id = Tools::getValue('doc_id');

        if (!$token OR !$doc_id) {
            header('HTTP/1.0 400 Bad Request');
            die('<h1>400 Bad Request</h1>');
        }

        $token = trim($token);
        $doc_id = trim($doc_id);

        $validate = DnModulKassaClient::validateToken($token, $doc_id);

        if (!$validate) {
            header('HTTP/1.0 403 Forbidden');
            die('<h1>403 Forbidden</h1>');
        }

        $entry = DnModulKassaEntry::getEntryByDocId($doc_id);
        if (!$entry OR !is_object($entry)) {
            header('HTTP/1.0 404 Not Found');
            die('<h1>404 Not Found</h1>');
        }

        $entry->status = 'COMPLETED';
        $entry->save();

        header('Content-Type: application/json');
        die(Tools::jsonEncode(array()));
    }

    protected function disableColumns()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;
        $this->display_footer = false;
        $this->display_header = false;
    }
}
