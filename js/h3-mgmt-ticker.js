( function ( $ ) { // closure

    $( document ).ready( function () {

        $( ".ticker-comment-button" ).on( 'click', $( this ), function () {
            var ticker_msg_id = $( this ).attr( 'data-ticker_msg_id' );

            $( "#ticker-send-comment_" + ticker_msg_id ).find( "#ticker_id" ).val( ticker_msg_id );

            if ( $( "#ticker-send-comment_" + ticker_msg_id ).hasClass( 'comment_close' ) ) {
                $( "#ticker-send-comment_" + ticker_msg_id )
                    .show()
                    .removeClass( 'comment_close' )
                    .addClass( 'comment_open' );
                $( this ).html( '- send a comment' );
            } else {
                $( "#ticker-send-comment_" + ticker_msg_id )
                    .hide()
                    .removeClass( 'comment_open' )
                    .addClass( 'comment_close' );
                $( this ).html( '+ send a comment' );
            }
        } );

        $( ".ticker-show-comments" ).on( 'click', $( this ), function () {
            var ticker_msg_id = $( this ).attr( 'data-ticker_msg_id' );

            if ( $( "#ticker-show-comments_" + ticker_msg_id ).hasClass( 'show-comments_close' ) ) {
                $( "#ticker-show-comments_" + ticker_msg_id )
                    .show()
                    .removeClass( 'show-comments_close' )
                    .addClass( 'show-comments_open' );
                var comment_html = $( this ).html();
                $( this ).html( comment_html.replace( "+", "-" ) );
            } else {
                $( "#ticker-show-comments_" + ticker_msg_id )
                    .hide()
                    .removeClass( 'show-comments_open' )
                    .addClass( 'show-comments_close' );
                var comment_html = $( this ).html();
                $( this ).html( comment_html.replace( "-", "+" ) );
            }
        } );

    } );

} )( jQuery ); // closure