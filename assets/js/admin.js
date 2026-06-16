/**
 * Followup admin — postmark interactions.
 *
 * Presentation only: keeps the postmark date stamp in sync with the delay
 * field, and re-presses the stamp when an email type is enabled. No behaviour
 * change; the form submits exactly as before with JS off.
 */
(function () {
	'use strict';

	function unit(card, days) {
		var data = card.getAttribute('data-fu-units');
		var pair = data ? data.split('|') : ['day', 'days'];
		return days === 1 ? pair[0] : pair[1];
	}

	function syncStamp(card) {
		var delay = card.querySelector('[data-fu-delay]');
		var stamp = card.querySelector('[data-fu-stamp]');
		if (!delay || !stamp) {
			return;
		}
		var days = parseInt(delay.value, 10);
		if (isNaN(days) || days < 0) {
			days = 0;
		}
		var num = stamp.querySelector('.followup-postmark__days');
		var u = stamp.querySelector('.followup-postmark__days-unit');
		if (num) {
			num.textContent = String(days);
		}
		if (u) {
			u.textContent = days === 0 ? (card.getAttribute('data-fu-soon') || 'soon') : unit(card, days);
		}
	}

	function press(card) {
		var stamp = card.querySelector('[data-fu-stamp]');
		if (!stamp) {
			return;
		}
		stamp.classList.remove('is-pressing');
		// Force reflow so the animation can restart.
		void stamp.offsetWidth;
		stamp.classList.add('is-pressing');
	}

	function init() {
		var cards = document.querySelectorAll('.followup-email');
		Array.prototype.forEach.call(cards, function (card) {
			var delay = card.querySelector('[data-fu-delay]');
			if (delay) {
				delay.addEventListener('input', function () {
					syncStamp(card);
				});
			}

			var toggle = card.querySelector('.followup-email__toggle');
			if (toggle) {
				toggle.addEventListener('change', function () {
					if (toggle.checked) {
						card.classList.add('is-enabled');
						press(card);
					} else {
						card.classList.remove('is-enabled');
					}
				});
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
