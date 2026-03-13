/**
 * Language Picker JS (SLY)
 * handles dropdown click open/close.
 * Position is now handled via CSS flexbox/order in langpicker.css.php
 */
(function() {
	var clickBound = false;

	function bindClick() {
		var dropdown = document.getElementById('topmenu-lang-dropdown');
		var toggle = document.getElementById('lang-toggle');
		if (!dropdown || !toggle || clickBound) return;
		clickBound = true;
		toggle.addEventListener('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			dropdown.classList.toggle('open');
		});
		document.addEventListener('click', function(e) {
			if (!dropdown.contains(e.target)) dropdown.classList.remove('open');
		});
	}

	function init() {
		bindClick();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
	window.addEventListener('load', function() {
		bindClick();
	});
})();
