function docsResize() {
	clientWidth  = parseInt($('#main').css('width'))-25;
	clientHeight = parseInt($('body').css('height'))-200;

	$().ready(function() {
		$('#container').css('width', clientWidth+"px");
		$('#container').css('height', clientHeight+"px");
		$('#data_tbl').css('width', clientWidth+"px");
		$('#data_tbl').css('height', clientHeight+"px");
		$('.mceIframeContainer').css('width', clientWidth+"px");
		$('.mceIframeContainer').css('height', (clientHeight-100)+"px");
	});
}
