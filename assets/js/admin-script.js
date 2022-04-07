jQuery(document).ready(function($) {

    let meta_image_frame;
    let counter = $('#images-container > p').length + 1;

    $('body').on('click', '.meta-image-button:not(.remove-button)', function(e){
        e.preventDefault();

        let target = $(this).prev('[name*=meta-image]');

        meta_image_frame = wp.media.frames.file_frame = wp.media({
            title: 'Image Gallery Selection Window',
            button: {text: 'Add to Gallery'},
            library: { type: 'image'},
            multiple: false
        });

        meta_image_frame.on('select', function(){
            let media_attachment = meta_image_frame.state().get('selection').first().toJSON();
            let url = '';
            $(target).val(media_attachment.url);
        });

        meta_image_frame.open();
    });

    $('body').on('click', '.meta-image-button.remove-button', function(e){
        e.preventDefault();

        let target = $(this).parent().find('[name*=meta-image]');
        $(target).val('');
    });


    $('#add-input').on('click', function(event){
        add_input()
    });

    function add_input(){
        var input = `<p><label for='meta-image' class=''>Add Image</label>`
            +`<input type='text' name='meta-image-${counter}' id='meta-image-${counter}' value='' />`
            +`<input type='button' class='meta-image-button button' value='Upload Image' />`
            +`<input type='button' class='meta-image-button button remove-button' value='Remove Image' /></p>`;

        $('#images-container').append(input);
    }

});
