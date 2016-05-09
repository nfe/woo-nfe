if ( typeof jq == 'undefined' ) {
    var jq = jQuery;
}

jq( function() {
    var AddRow      = jq('#add-row');
    var RemoveRow   = jq('.remove-row');

    AddRow.on('click', function() {
        var row = jq( '.empty-row.screen-reader-text' ).clone(true);
        row.removeClass('empty-row screen-reader-text');
        row.insertBefore('#nfe-woo-fieldset-one tbody>tr:last');

        return false;
    });

    RemoveRow.on('click', function() {
        jq(this).parents('tr').remove();

        return false;
    });
}); 
