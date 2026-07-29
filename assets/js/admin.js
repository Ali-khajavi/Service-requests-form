(function($){
    'use strict';

    function srfAdminText(key, fallback) {
        return (window.srfAdmin && window.srfAdmin[key]) ? String(window.srfAdmin[key]) : String(fallback || '');
    }

    $(function(){

        var frame;

        $('#sr-service-gallery-button').on('click', function(e){
            e.preventDefault();

            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: srfAdminText('select_service_images', 'Select Service Images'),
                button: {
                    text: srfAdminText('use_these_images', 'Use these images')
                },
                multiple: true
            });

            frame.on('select', function(){
                var selection = frame.state().get('selection');
                var ids = [];
                var preview = $('#sr-service-gallery-preview');

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

                $('#sr-service-gallery-ids').val(ids.join(','));
            });

            frame.open();
        });

        document.querySelectorAll('.tpq-confirm-delete').forEach(function(button) {
            button.addEventListener('click', function(event) {
                if (!confirm(button.dataset.tpqMessage || srfAdminText('are_you_sure', 'Are you sure?'))) {
                    event.preventDefault();
                }
            });
        });

        document.querySelectorAll('[data-tpq-toggle]').forEach(function(field) {
            field.addEventListener('change', function(event) {
                var selector = event.target.dataset.tpqToggle;
                if (!selector) return;
                var target = document.querySelector(selector);
                if (!target) return;
                target.classList.toggle('tpq-is-hidden', !event.target.checked);
            });
        });

    });

})(jQuery);