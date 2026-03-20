document.addEventListener('DOMContentLoaded', function () {
	const activeLink = document.querySelector('.amc-custom-dashboard__nav a.is-active');

	if (activeLink) {
		activeLink.setAttribute('aria-current', 'page');
	}
});
