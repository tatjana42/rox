function monkeyPatchAutocomplete() {
    jQuery.ui.autocomplete.prototype._renderItem = function (ul, item) {

        var keywords = jQuery.trim(this.term).split(' ').join('|');
        var output = item.label.replace(new RegExp("(" + keywords + ")", "gi"), '<span class="ui-menu-item-highlight">$1</span>');

        return jQuery("<li>")
            .append(jQuery("<a>").html(output))
            .appendTo(ul);
    };
}

jQuery(function () {
    monkeyPatchAutocomplete();
});

jQuery.widget( "custom.catcomplete", jQuery.ui.autocomplete, {
    _renderMenu: function( ul, items ) {
        var that = this,
            currentCategory = "";
        jQuery.each( items, function( index, item ) {
            if ( item.category != currentCategory ) {
                ul.append( "<li class='ui-autocomplete-category'>" + item.category + "</li>" );
                currentCategory = item.category;
            }
            that._renderItemData( ul, item );
        });
    },
});

jQuery(function() {
    jQuery( "#search-location" ).catcomplete({
  source: function( request, response ) {
    jQuery.ajax({
      url: "/search/locations/places",
      dataType: "jsonp",
      data: {
        name: request.term
      },
      success: function( data ) {
        if (data.result != "success") {
        	data.locations = [{ name: "No matches found or search service not running", category: "Information" }];
        }
          response( 
          jQuery.map( data.locations, function( item ) {
            return {
              label: (item.name ? item.name : "")+ (item.admin1 ? (item.name ? ", " : "") + item.admin1 : "") + (item.country ? ", " + item.country : "")  + (item.cnt ? " (" + item.cnt +")" : ""),
              labelnocount: (item.name ? item.name : "")+ (item.admin1 ? (item.name ? ", " : "") + item.admin1 : "") + (item.country ? ", " + item.country : ""),
              value: item.geonameid,
              category: item.category
            };
          }));
    }
  });
  },
  change: function( event, ui ) {
    if (ui.item == null) {
      jQuery( "#search-geoname-id" ).val( 0 );
    } else {
      jQuery( "#search-geoname-id" ).val(ui.item.value);
    }
  },
  search: function( event, ui ) {
	  jQuery( '#search-loading').css( 'visibility', 'visible');
  },
  response: function( event, ui ) {
	  jQuery( '#search-loading').css( 'visibility', 'hidden');
  },
  select: function( event, ui ) {
    jQuery( "#search-geoname-id" ).val( ui.item.value );
    jQuery( this ).val( ui.item.labelnocount );
    
    return false;
  },
  minLength: 1,
  delay: 200,
    });
});