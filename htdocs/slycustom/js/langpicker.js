/**
 * Language Picker JS (SLY)
 */
(function() {
	var clickBound = false;

	function moveLangBeforeUser() {
		var langBlock = document.querySelector('.login_block_lang');
		var userBlock = document.querySelector('.login_block_user');
		if (!langBlock || !userBlock || !userBlock.parentNode) return;
		if (userBlock.previousElementSibling === langBlock) return;
		userBlock.parentNode.insertBefore(langBlock, userBlock);
	}

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
		moveLangBeforeUser();
		bindClick();
	}

	/* 切换语言后整页重定向，用户块可能晚于 DOMContentLoaded 插入，故多处执行插入 */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
	window.addEventListener('load', function() {
		moveLangBeforeUser();
		bindClick();
	});
	setTimeout(moveLangBeforeUser, 300);
	setTimeout(moveLangBeforeUser, 600);
	requestAnimationFrame(function() {
		setTimeout(moveLangBeforeUser, 100);
	});
})();
