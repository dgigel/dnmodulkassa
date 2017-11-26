<div class="col-md-6" id="dnmodulkassa_col">
    <div class="panel">
        <div class="panel-heading">
            <img src="../modules/dnmodulkassa/logo.gif"> <b>Модуль</b>Касса
            <span class="panel-heading-action">
                <a class="list-toolbar-btn" href="{$module_settings_link}" title="Настроить">
                    <i class="process-icon-configure"></i>
                </a>
            </span>
        </div>
        {if !$configured}
            <p class="alert alert-info">Необходима первоначальная настройка модуля!<br>Перейдите в <a target="_blank" href="{$module_settings_link}">настроки</a>.</p>
        {else}
            <div class="row">
                <button id="dnmodulkassa_modal_open" type="button" data-toggle="modal" data-target="#dnmodulkassaModal" class="btn btn-lg btn-default col-md-12">Отправить {if count($entries) > 0}повторно{else}в МодульКассу{/if}</button>
            </div>
            <div class="row {if count($entries) == 0}hidden{/if}" id="dnmodulkassa_docs">
                <div class="col-md-12">
                    <hr>
                    <h4>Документы:</h4>
                    <button type="button" class="btn btn-default pull-right" id="dnmodulkassa_refresh" data-loading-text="<i class='icon-refresh icon-spin icon-fw'></i>"><i class='icon-refresh icon-fw'></i></button>
                    <table class="table" id="dnmodulkassa_docs_table">
                        <thead>
                            <tr>
                                <th>Номер</th>
                                <th>Тип</th>
                                <th>Оплата</th>
                                <th>Дата документа</th>
                                <th>Статус</th>
                                <th>Обновлено</th>
                            </tr>
                        </thead>
                        <tbody>
                        {if count($entries) > 0}
                            {foreach $entries as $entry}
                                <tr>
                                    <td>{$entry['doc_id']}<br>{$tokee}</td>
                                    <td>{if $entry['doc_type'] == 'SALE'}Продажа{else}Возврат{/if}</td>
                                    <td>{if $entry['payment_type'] == 'CASH'}Наличными{else}Картой{/if}</td>
                                    <td>{$entry['checkout_datetime']}</td>
                                    <td>{$entry['status']}</td>
                                    <td>{$entry['date_upd']}</td>
                                </tr>
                            {/foreach}
                        {/if}

                        </tbody>
                    </table>
                </div>
            </div>
        {/if}
    </div>
</div>
<div id="dnmodulkassaModal" class="modal bs-example-modal-lg" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Оправить документ в <img src="../modules/dnmodulkassa/logo.gif"> <b>Модуль</b>Кассу</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-horizontal">
                            <div class="form-group">
                                <label class="control-label col-lg-3">Тип: </label>
                                <div class="col-lg-9">
                                    <p class="radio">
                                        <label for="DNMODULKASSA_DOCTYPE_SALE"><input type="radio" name="DNMODULKASSA_DOCTYPE" id="DNMODULKASSA_DOCTYPE_SALE" value="SALE" checked="checked">Продажа</label>
                                    </p>
                                    <p class="radio">
                                        <label for="DNMODULKASSA_DOCTYPE_RETURN"><input type="radio" name="DNMODULKASSA_DOCTYPE" id="DNMODULKASSA_DOCTYPE_RETURN" value="RETURN">Возврат</label>
                                    </p>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-lg-3">Оплата: </label>
                                <div class="col-lg-9">
                                    <p class="radio">
                                        <label for="DNMODULKASSA_PAYMENTTYPE_CASH"><input type="radio" name="DNMODULKASSA_PAYMENTTYPE" id="DNMODULKASSA_PAYMENTTYPE_CASH" value="CASH" checked="checked">Наличными</label>
                                    </p>
                                    <p class="radio">
                                        <label for="DNMODULKASSA_PAYMENTTYPE_CARD"><input type="radio" name="DNMODULKASSA_PAYMENTTYPE" id="DNMODULKASSA_PAYMENTTYPE_CARD" value="CARD">Картой</label>
                                    </p>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-lg-3">Печатать чек: </label>
                                <div class="col-lg-9">
                                    <span class="switch prestashop-switch fixed-width-lg">
                                        <input type="radio" name="DNMODULKASSA_PRINTRECEIPT" id="DNMODULKASSA_PRINTRECEIPT_1" value="1" checked="checked">
                                        <label for="DNMODULKASSA_PRINTRECEIPT_1" class="radioCheck">Да</label>
                                        <input type="radio" name="DNMODULKASSA_PRINTRECEIPT" id="DNMODULKASSA_PRINTRECEIPT_0" value="0">
                                        <label for="DNMODULKASSA_PRINTRECEIPT_0" class="radioCheck">Нет</label>
                                        <a class="slide-button btn"></a>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-lg-3">Почта/Телефон: </label>
                                <div class="col-lg-9">
                                    <input type="text" class="text form-control" value="{$contact}" name="DNMODULKASSA_CONTACT">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-lg btn-default" data-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-lg btn-{if count($entries) > 0}success{else}primary{/if} btn-docsend" id="dnmodulkassa_createdoc" data-loading-text="<i class='icon-refresh icon-spin icon-fw'></i> Отправляем">Отправить {if count($entries) > 0}повторно{/if}</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->