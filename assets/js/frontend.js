document.addEventListener('DOMContentLoaded', function () {
	const toggle = document.querySelector('.amc-nav-toggle');
	const nav = document.querySelector('.amc-nav');

	if (!toggle || !nav) {
		return;
	}

	toggle.addEventListener('click', function () {
		const open = nav.classList.toggle('is-open');
		toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
	});
});
