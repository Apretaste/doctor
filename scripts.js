function showToast(text) {
  $(".toast").remove();
  M.toast({html: text});
}

function send() {
  var queryControl = $('#query');
  var query = queryControl.val();

  if (query.length < 2) {
    showToast('Escriba al menos 2 caracteres');
    queryControl.addClass('invalid');
    queryControl.css('color','red');
    return false;
  }

  apretaste.send({
    command: "DOCTOR",
    data: {
      query: query
    },
    redirect: true
  });
}