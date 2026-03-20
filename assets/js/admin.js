document.addEventListener('DOMContentLoaded', function () {
	const buttons = document.querySelectorAll('.amc-admin-button-row .button');

	buttons.forEach(function (button) {
		button.addEventListener('click', function () {
			button.blur();
		});
	});
});
