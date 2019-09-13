// submit search on enter
$(document).ready(function() {
	$('#query').keypress(function (e) {
		if (e.which == 13) {
			send();
			return false;
		}
	});
});

function send() {
	var query = $('#query').val();

	if (query.length < 2) {
		M.toast({html: 'Escriba al menos 2 caracteres'});
		return false;
	}

	apretaste.send({
		command: "DOCTOR ARTICULO",
		data: {'query': query},
		redirect: true
	});
}