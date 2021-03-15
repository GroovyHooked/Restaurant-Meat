jQuery(document).ready(function ($) {
	jQuery( '.sap-infinite-table-add-row' ).on( 'click', function() {
		var max_row = findInfiniteTableMaxRow(); 

		var tfoot = jQuery( this ).closest( 'tfoot' );
		var tbody = jQuery( this ).closest( '.sap-infinite-table' ).find( 'tbody' );

		tfoot.find( '.sap-inifite-table-row-template' ).clone().appendTo( tbody );

		jQuery( '.sap-infinite-table tbody .sap-inifite-table-row-template' ).removeClass( 'sap-inifite-table-row-template' ).addClass( 'sap-inifinite-table-row' ).addClass( 'sap-new-infinite-row' );
		jQuery( '.sap-new-infinite-row' ).data('rowid', max_row + 1);
		jQuery( '.sap-new-infinite-row input, .sap-new-infinite-row select, .sap-new-infinite-row textarea' ).each(function() {
			jQuery(this).attr( 'name', jQuery(this).attr( 'name' ) + '_' + (max_row + 1));
		});

		jQuery( '.sap-new-infinite-row' ).find('.sap-infinite-table-id').val( max_row + 1 );
		jQuery( '.sap-new-infinite-row' ).find('.sap-infinite-table-id-html').html( max_row + 1 );
		
		jQuery( '.sap-new-infinite-row' ).removeClass( 'sap-new-infinite-row' ).removeClass( 'sap-hidden' );

		setInfiniteTableDeleteHandlers();
		setInfiniteTableUpdateHandlers();
	});

	setInfiniteTableDeleteHandlers();
	setInfiniteTableUpdateHandlers();

});

function findInfiniteTableMaxRow() {
	var max_row = 0;

	jQuery( '.sap-inifinite-table-row' ).each(function() {
		max_row = Math.max(jQuery(this).data( 'rowid' ), max_row);
	});

	return max_row;
}

function setInfiniteTableDeleteHandlers() {
	jQuery( '.sap-infinite-table-row-delete' ).off( 'click.delete_row' );
	jQuery( '.sap-infinite-table-row-delete' ).on( 'click.delete_row', function() {
		var table = jQuery( this ).closest( '.sap-infinite-table' );
		var row = jQuery( this );

		// give anything hooked into the delete action a chance to process before removing
		setTimeout( function() {
			row.parent().remove();

			infiniteTableSaveData( table );
		}, 200);
	});
}

function setInfiniteTableUpdateHandlers() {
	jQuery( '.sap-inifinite-table-row input, .sap-inifinite-table-row textarea' ).off( 'keyup.update_fields' );
	jQuery( '.sap-inifinite-table-row input, .sap-inifinite-table-row textarea' ).on( 'keyup.update_fields', function() {
		infiniteTableSaveData( jQuery( this ).closest( '.sap-infinite-table' ) );
	});

	jQuery( '.sap-inifinite-table-row input[type="number"]' ).off( 'click.update_fields' );
	jQuery( '.sap-inifinite-table-row input[type="number"]' ).on( 'click.update_fields', function() {
		infiniteTableSaveData( jQuery( this ).closest( '.sap-infinite-table' ) );
	});

	jQuery( '.sap-inifinite-table-row select' ).off( 'change.update_fields' );
	jQuery( '.sap-inifinite-table-row select' ).on( 'change.update_fields', function() {
		infiniteTableSaveData( jQuery( this ).closest( '.sap-infinite-table' ) );
	});
}

function infiniteTableSaveData( table ) {
	var fields = table.data( 'fieldids' ).split( ',' );
	var data = [];

	jQuery( table ).find( '.sap-inifinite-table-row' ).each(function() {
		var row_id = jQuery(this).data('rowid');
		var row_data = {};

		jQuery(fields).each(function(index, field) {
			if ( jQuery( 'input[name="' + field + '_' + row_id + '"]' ).length ) { row_data[field] = jQuery( 'input[name="' + field + '_' + row_id + '"]' ).val(); }
			else if ( jQuery( 'select[name="' + field + '_' + row_id + '"]' ).length ) { row_data[field] = jQuery( 'select[name="' + field + '_' + row_id + '"]' ).val(); }
			else if ( jQuery( 'textarea[name="' + field + '_' + row_id + '"]' ).length ) { row_data[field] = jQuery( 'textarea[name="' + field + '_' + row_id + '"]' ).val(); }
		});
 
		data.push(row_data);
	});
	
	jQuery( table ).find( 'input[type="hidden"]:not( .sap-infinite-table-id )' ).val(JSON.stringify(data));
}
