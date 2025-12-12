(function($){
    'use strict';

    $(function(){

        var frame;

        $('#sr-service-gallery-button').on('click', function(e){
            e.preventDefault();

            // If the media frame already exists, reopen it.
            if ( frame ) {
                frame.open();
                return;
            }

            // Create the media frame.
            frame = wp.media({
                title: 'Select Service Images',
                button: {
                    text: 'Use these images'
                },
                multiple: true
            });

            // When images are selected, run this callback.
            frame.on('select', function(){
                var selection = frame.state().get('selection');
                var ids       = [];
                var preview   = $('#sr-service-gallery-preview');

                preview.empty();

                selection.each(function(attachment){
                    attachment = attachment.toJSON();
                    ids.push(attachment.id);

                    if (attachment.sizes && attachment.sizes.thumbnail) {
                        preview.append(
                            '<div class="sr-service-gallery-item">' +
                                '<img src="' + attachment.sizes.thumbnail.url + '" style="width:80px;height:80px;object-fit:cover;border:1px solid #ddd;" />' +
                            '</div>'
                        );
                    } else if (attachment.url) {
                        preview.append(
                            '<div class="sr-service-gallery-item">' +
                                '<img src="' + attachment.url + '" style="width:80px;height:80px;object-fit:cover;border:1px solid #ddd;" />' +
                            '</div>'
                        );
                    }
                });

                // Save IDs to hidden field
                $('#sr-service-gallery-ids').val(ids.join(','));
            });

            // Finally, open the modal
            frame.open();
        });

    });

})(jQuery);
