'use strict';

/* light/dark mode toggle persistence, https://www.smashingmagazine.com/2024/03/setting-persisting-color-scheme-preferences-css-javascript/ */
const defaultMode = window.matchMedia?.("(prefers-color-scheme: dark)").matches ? 'dark' : 'light';
const modeToggle = document.querySelector('[name="toggle-color-scheme"]');
let manualMode = localStorage.getItem('manual-light-dark');

if (manualMode) {
	modeToggle.checked = (manualMode == 'dark') ? true : false;
	document.documentElement.setAttribute('data-mode', manualMode);
} else {
	if (defaultMode == 'dark') {
		modeToggle.checked = true;
		document.documentElement.style.setProperty('--color-scheme', 'dark');
		document.documentElement.setAttribute('data-mode', 'dark');
	} else {
		modeToggle.checked = false;
		document.documentElement.style.setProperty('--color-scheme', 'light');
		document.documentElement.setAttribute('data-mode', 'light');
	}
}

modeToggle?.addEventListener('change', function (event) {
	manualMode = event.target.checked ? 'dark' : 'light';
	localStorage.setItem('manual-light-dark', manualMode);
	document.documentElement.style.setProperty('--color-scheme', manualMode);
	document.documentElement.setAttribute('data-mode', manualMode);

	// console.log('manually set mode to', manualMode);
});

// logo animation
document.querySelectorAll('.logo-link svg #fenster rect')?.forEach(function (fenster, i) {

	console.log(fenster, i);
	fenster.style.backgroundColor = 'transparent';
	const randomDelay = Math.floor(Math.random() * 1500) + 200;
	// fenster.style.animationDelay = (i*20) + 'ms';
	fenster.style.animationDelay = randomDelay + 'ms';
});

