$(document).ready(function () {
    $('#dnmodulkassa_createdoc').on('click', function (e) {
        e.preventDefault();
        stopAjaxQuery();

        var errors = [];

        var form_data = {
            id_order: id_order,
            doc_type: $('input[name="DNMODULKASSA_DOCTYPE"]:checked').val(),
            payment_type: $('input[name="DNMODULKASSA_PAYMENTTYPE"]:checked').val(),
            print_receipt: $('input[name="DNMODULKASSA_PRINTRECEIPT"]:checked').val(),
            contact: $('input[name="DNMODULKASSA_CONTACT"]').val()
        };

        for (var key in form_data) {
            if (form_data[key].length == 0) {
                errors.push(key);
            }
        }

        if (errors.length > 0) {
            jAlert('В форме заполнены не все поля.');
        } else {
            var $this_button = $(this).button('loading');
            $.ajax({
                type: 'POST',
                dataType: 'json',
                url: 'ajax-tab.php',
                cache: false,
                data: {
                    ajax: true,
                    controller: 'AdminDnModulKassa',
                    action: 'createDoc',
                    token: tokenDnModulKassa,
                    form_data: form_data
                },
            })
                .done(function (response) {
                    if (response.success === true) {
                        console.log(response);
                        $this_button.button('reset');
                        $this_button.removeClass('btn-primary').addClass('btn-success');
                        $this_button.text('Отправить повторно');
                        $('#dnmodulkassa_modal_open').text('Отправить повторно');
                        $('#dnmodulkassa_docs').removeClass('hidden');
                        $('#dnmodulkassa_docs_table > tbody:last').append(
                            '<tr>' +
                            '<td>' + response.entry.doc_id + '</td>' +
                            '<td>' + (response.entry.doc_type == 'SALE' ? 'Продажа' : 'Возврат') + '</td>' +
                            '<td>' + (response.entry.payment_type == 'CASH' ? 'Наличными' : 'Картой') + '</td>' +
                            '<td>' + response.entry.checkout_datetime + '</td>' +
                            '<td>' + response.entry.status + '</td>' +
                            '<td>' + response.entry.date_upd + '</td>' +
                            '</tr>');
                    } else {
                        $this_button.button('reset');
                    }
                })
                .fail(function (XMLHttpRequest, textStatus, errorThrown) {
                    jAlert("Error.\n\ntextStatus: '" + textStatus + "'\nerrorThrown: '" + errorThrown + "'\nresponseText:\n" + XMLHttpRequest.responseText);
                    $this_button.button('reset');
                });
        }
    });
});