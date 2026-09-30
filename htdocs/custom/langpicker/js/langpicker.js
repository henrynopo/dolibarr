
/**
 * Language Picker JS
 */

$(document).ready(function(){
	// Reposition language picker in top header:
	// move it between version (.aversion) and user menu (.login_block_user)
	(function moveLangPickerWithRetry() {
		var tries = 0;
		var maxTries = 25; // ~5s at 200ms

		function attempt() {
			tries++;

			var $dropdown = $(".language-dropdown").first();
			if (!$dropdown.length) {
				if (tries < maxTries) setTimeout(attempt, 200);
				return;
			}

			// Avoid moving twice
			if ($dropdown.data("langpicker-moved")) return;

			var $loginBlock = $dropdown.closest("div.login_block");
			if (!$loginBlock.length) {
				if (tries < maxTries) setTimeout(attempt, 200);
				return;
			}

			var $userBlock = $loginBlock.find("div.login_block_user").first();
			if (!$userBlock.length) {
				if (tries < maxTries) setTimeout(attempt, 200);
				return;
			}

			var $versionSpan = $loginBlock.find("span.aversion").first();
			if ($versionSpan.length) {
				var $versionWrap = $versionSpan.closest(".login_block_elem");
				if ($versionWrap.length) {
					$dropdown.insertAfter($versionWrap);
				} else {
					$dropdown.insertBefore($userBlock);
				}
			} else {
				$dropdown.insertBefore($userBlock);
			}

			// Mark so CSS can switch from fixed/absolute positioning
			$dropdown.addClass("langpicker-moved-into-topright");
			$dropdown.data("langpicker-moved", 1);
		}

		attempt();
	})();

	$("#lang-toggle").click(function(e){
		e.preventDefault();
		e.stopPropagation();
		$(".language-dropdown").toggleClass("open");
	});

	$(document).click(function() {
		$(".language-dropdown").removeClass("open");
	});
});
