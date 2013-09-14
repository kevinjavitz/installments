var Installment = new Class.create({
    initialize: function () {

    },
    recalculate: function (href, params, isDefault) {
        new Ajax.Request(href, {
            method: 'post',
            /*action_content: ['cart_sidebar', 'top.links'],*/
            parameters: params,
            onComplete: function (transport) {
                var response = transport.responseJSON;
                if (response) {
                    var html = response.html;
                    if (html) {
                        _this.showModal(html);
                    }
                } else {
                    alert('ERROR!');
                }
                _this.hideLoader();
            }
        });
        return false;

    }
});
