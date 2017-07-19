function demoRequest(form) {
	console.log(form.elements);

	var method = form.elements['i-method'].value;
	var endpoint = form.elements['i-endpoint'].value;
	var base = form.elements['i-base'].value;

	console.log("Method: " + method);
	console.log("Endpoint: " + endpoint);
	console.log("Base: " + base);

	

	return false;
}
