$(document).ready(function(){
	 $('[data-toggle="popover"]').popover({
          //trigger: 'focus',
		  trigger: 'hover',
          html: true,
          content: function () {
				return '<img class="rounded" class="rounded" border="0" width="250" src="'+$(this).data('img') + '" />';
          },
          title: ''
    }) 
});