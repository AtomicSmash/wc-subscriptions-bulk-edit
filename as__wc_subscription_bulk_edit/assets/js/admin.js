(function($) {


    // Init date picker on the payment date field
    $( "#subscription_next_payment_date" ).datepicker();

    // Create a copy of the WP inline edit post function
	var $wp_inline_edit = inlineEditPost.edit;

	// Then overwrite the function with custom code
	inlineEditPost.edit = function( id ) {

        // "call" the original WP edit function
		$wp_inline_edit.apply( this, arguments );

		// Get the post ID
		var $post_id = 0;
		if ( typeof( id ) == 'object' )
			$post_id = parseInt( this.getId( id ) );

		if ( $post_id > 0 ) {

			// Define the edit row
			var $edit_row = $( '#edit-' + $post_id );
			var $post_row = $( '#post-' + $post_id );

			// Get the data
            var $next_payment_date = $( '.column-next_payment_date', $post_row ).html();

			// Populate the data
            $( ':input[name="next_payment_date"]', $edit_row ).val( $next_payment_date );

		}
	};

    // The #bulk-edit button is the blue 'Update' button
	$( document ).on( 'click', '#bulk_edit', function() {

		// Define the bulk edit row
		var $bulk_row = $( '#bulk-edit' );

		// Get the selected post ids that are being edited
		var $post_ids = new Array();
		$bulk_row.find( '#bulk-titles' ).children().each( function() {
			$post_ids.push( $( this ).attr( 'id' ).replace( /^(ttle)/i, '' ) );
		});

		// Get the data
		var $next_payment_date = $bulk_row.find( '#subscription_next_payment_date' ).val();

		// Save the data
		$.ajax({
			url: ajaxurl, // this is a variable that WordPress has already defined
			type: 'POST',
			async: false,
			cache: false,
			data: {
				action: 'save_bulk_edit_shop_subscription', // this is the name of the WP AJAX function
				post_ids: $post_ids, // and these are the parameters being passed to the ajax function
                next_payment_date: $next_payment_date
			}
		});
	});

})(jQuery);