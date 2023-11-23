function remote_launcher(file, name, width, height) {
	var sw     = screen.width;
	var sh     = screen.height;
	var ulx    = ((sw-width)/2), uly = ((sh-height)/2);
	var constr =
		'toolbar=no,location=no,directories=no,' +
		'status=no,menubar=no,resizable=no,' +
		'scrollbars=no,dependent=yes,' +
		'width='+ width + ',' +
		'height=' + height + ',' +
		'left=' + ulx + ',' +
		'top=' + uly;

	var myWindow = window.open(file,name,constr);
}