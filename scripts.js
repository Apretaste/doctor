// globals
var share;

function init(term, result) {
	share = {
		text: term + ": " + removeTags(result).substr(0,50) + '...',
		icon: 'user-nurse',
		send: function() {
			apretaste.send({
				command:'PIZARRA PUBLICAR',
				redirect: false,
				callback: {
					name: 'toast',
					data: 'Se ha compartido en Pizarra el resultado de Doctor'
				},
				data: {
					text: $('#message').val(),
					image: '',
					link: {
						command: btoa(JSON.stringify({
							command: 'DOCTOR ARTICULO',
							data: {
								query: term
							}
						})),
						icon: share.icon,
						text: share.text
					}
				}});
		}
	}
}

// functions
function toast(message){
	M.toast({html: message});
}

function removeTags(str) {
	if ((str===null) || (str===''))
		return '';
	else
		str = str.toString();

	// Regular expression to identify HTML tags in
	// the input string. Replacing the identified
	// HTML tag with a null string.
	return str.replace( /(<([^>]+)>)/ig, '');
}

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

// POLYFILL
function _typeof(obj) {
	if (typeof Symbol === "function" && typeof Symbol.iterator === "symbol") {
		_typeof = function _typeof(obj) {
			return typeof obj;
		};
	} else {
		_typeof = function _typeof(obj) {
			return obj && typeof Symbol === "function" && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj;
		};
	}
	return _typeof(obj);
}

if (!Object.keys) {
	Object.keys = function () {
		'use strict';

		var hasOwnProperty = Object.prototype.hasOwnProperty,
			hasDontEnumBug = !{
				toString: null
			}.propertyIsEnumerable('toString'),
			dontEnums = ['toString', 'toLocaleString', 'valueOf', 'hasOwnProperty', 'isPrototypeOf', 'propertyIsEnumerable', 'constructor'],
			dontEnumsLength = dontEnums.length;

		return function (obj) {
			if (_typeof(obj) !== 'object' && (typeof obj !== 'function' || obj === null)) {
				throw new TypeError('Object.keys called on non-object');
			}

			var result = [], prop, i;

			for (prop in obj) {
				if (hasOwnProperty.call(obj, prop)) {
					result.push(prop);
				}
			}

			if (hasDontEnumBug) {
				for (i = 0; i < dontEnumsLength; i++) {
					if (hasOwnProperty.call(obj, dontEnums[i])) {
						result.push(dontEnums[i]);
					}
				}
			}

			return result;
		};
	}();
}

// events
$(function () {
	$('#query').keypress(function (e) {
		if (e.which === 13) {
			send();
			return false;
		}
	});

	$('.modal').modal();

});


