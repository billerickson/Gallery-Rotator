jQuery(function($) {
	var settings, $checkbox, $template, $code, galleries = {};

	// Don't do anything unless there is is data
	if( typeof(be_gallery_rotator) == 'undefined' ) {
		return;
	}

	// Parse the JSON data of the plugin
	settings = $.parseJSON(be_gallery_rotator);

	// Fetch the checkbox
	$checkbox = $(settings.setting_code);

	// This will hold the tag with the template for the gallery
	$template = $('#tmpl-gallery-settings');

	// Add the "Display as Rotator" checkbox as HTML code
	$code = $( '<div />' ).append($template.html());

	$('input[data-setting="_orderbyRandom"]', $code).each(function(){
		$(this).closest('.setting').after($checkbox);
	});

	// Set the new HTML where needed
	$template.html( $code.html() );

	galleries = {};

	// Overriding the "attachments" method allows retrieving the rotator status
	wp.media.gallery.attachments = function( shortcode ) {
		var shortcodeString = shortcode.string(),
			result = galleries[ shortcodeString ],
			attrs, args, query, others;

		delete galleries[ shortcodeString ];

		if ( result )
			return result;

		// Fill the default shortcode attributes.
		attrs = _.defaults( shortcode.attrs.named, wp.media.gallery.defaults );
		args  = _.pick( attrs, 'orderby', 'order' );

		args.type    = 'image';
		args.perPage = -1;

		// Mark the `orderby` override attribute.
		if ( 'rand' === attrs.orderby )
			attrs._orderbyRandom = true;

		// Map the `orderby` attribute to the corresponding model property.
		if ( ! attrs.orderby || /^menu_order(?: ID)?$/i.test( attrs.orderby ) )
			args.orderby = 'menuOrder';

		// Map the `ids` param to the correct query args.
		if ( attrs.ids ) {
			args.post__in = attrs.ids.split(',');
			args.orderby  = 'post__in';
		} else if ( attrs.include ) {
			args.post__in = attrs.include.split(',');
		}

		if ( attrs.exclude )
			args.post__not_in = attrs.exclude.split(',');

		if ( ! args.post__in )
			args.uploadedTo = attrs.id;

		args[settings.setting_key] = 'true' === attrs[settings.setting_key];

		// Collect the attributes that were not included in `args`.
		others = _.omit( attrs, 'id', 'ids', 'include', 'exclude', 'orderby', 'order' );

		query = wp.media.query( args );
		query.gallery = new Backbone.Model( others );
		return query;
	}

	// Overriding this method helps us remove the "rotator" attribute if not used
	wp.media.gallery.shortcode = function( attachments ) {
		var props = attachments.props.toJSON(),
			attrs = _.pick( props, 'orderby', 'order' ),
			shortcode, clone;

		if ( attachments.gallery )
			_.extend( attrs, attachments.gallery.toJSON() );

		// Convert all gallery shortcodes to use the `ids` property.
		// Ignore `post__in` and `post__not_in`; the attachments in
		// the collection will already reflect those properties.
		attrs.ids = attachments.pluck('id');

		// Copy the `uploadedTo` post ID.
		if ( props.uploadedTo )
			attrs.id = props.uploadedTo;

		// Check if the gallery is randomly ordered.
		if ( attrs._orderbyRandom )
			attrs.orderby = 'rand';
		delete attrs._orderbyRandom;

		// Check if the rotator is checked
		if ( !attrs[settings.setting_key] )
			delete attrs[settings.setting_key];

		// If the `ids` attribute is set and `orderby` attribute
		// is the default value, clear it for cleaner output.
		if ( attrs.ids && 'post__in' === attrs.orderby )
			delete attrs.orderby;

		// Remove default attributes from the shortcode.
		_.each( wp.media.gallery.defaults, function( value, key ) {
			if ( value === attrs[ key ] )
				delete attrs[ key ];
		});

		shortcode = new wp.shortcode({
			tag:    'gallery',
			attrs:  attrs,
			type:   'single'
		});

		// Use a cloned version of the gallery.
		clone = new wp.media.model.Attachments( attachments.models, {
			props: props
		});
		clone.gallery = attachments.gallery;
		galleries[ shortcode.string() ] = clone;

		return shortcode;
	}
});