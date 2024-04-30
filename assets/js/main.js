( function( wp ) {
    var isPublished = false;
    
    wp.data.subscribe( function() {
        var editor = wp.data.select( 'core/editor' );
        
        // Check if post is published
        if ( editor.isSavingPost() && ! editor.isAutosavingPost() && ! editor.isPreviewingPost() && ! isPublished ) {

            wp.data.dispatch( 'core/notices' ).createNotice(
                'success', // Can be 'success', 'info', 'warning', or 'error'
                'Post Published Successfully!',
                {
                    isDismissible: true,
                }
            );

            isPublished = true;
        }
    } );
} )( window.wp );
