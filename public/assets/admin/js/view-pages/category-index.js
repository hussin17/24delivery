"use strict";
$(document).on('ready', function () {

    $('.js-select2-custom').each(function () {
        let select2 = $.HSCore.components.HSSelect2.init($(this));
    });
});

$('#reset_btn').click(function(){
    $('#exampleFormControlSelect1').val(null).trigger('change');
})
var forms = document.querySelectorAll('.priority-form');

forms.forEach(function(form) {
    var select = form.querySelector('.priority-select');

    select.addEventListener('change', function() {
        form.submit();
    });
});
