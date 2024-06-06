"use strict";
$(document).on('ready', function () {
    // disable tranaction type for deliveryman
    // INITIALIZATION OF SELECT2
    // =======================================================
    $('.js-select2-custom').each(function () {
        var select2 = $.HSCore.components.HSSelect2.init($(this));
    });


    $('#type').on('change', function () {
        if ($('#type').val() == 'store') {
            $('#store').removeAttr("disabled");
            $('#deliveryman').val("").trigger("change");
            $('#deliveryman').attr("disabled", "true");
            $('#transaction_type').removeAttr("disabled");
console.log($('#transaction_type'), 'store');
        }
        else if ($('#type').val() == 'deliveryman') {
            $('#deliveryman').removeAttr("disabled");
            $('#store').val("").trigger("change");
            $('#store').attr("disabled", "true");
            $('#transaction_type').val("withdraw");
            $('#transaction_type').attr("disabled", "true");
console.log($('#transaction_type'), 'delivery');
        }
    });
});


$('#reset_btn').click(function () {
    $('#store').val(null).trigger('change');
    $('#deliveryman').val(null).trigger('change');
    $('#transaction_type').val(null).trigger('change');
})
