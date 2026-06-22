/**
 * Post metabox — vertical tab switching.
 */
;(function () {
	function activateTab(box, tab) {
		box.querySelectorAll('.wpbyvs-channel-tab-btn').forEach(function (b) {
			var on = b.dataset.tab === tab
			b.classList.toggle('wpbyvs-channel-tab-btn--active', on)
			b.setAttribute('aria-selected', String(on))
		})
		box.querySelectorAll('.wpbyvs-channel-tab-panel').forEach(function (p) {
			p.classList.toggle('wpbyvs-hidden', p.dataset.panel !== tab)
		})
	}

	document.querySelectorAll('.wpbyvs-metabox').forEach(function (box) {
		var first = box.querySelector('.wpbyvs-channel-tab-btn')
		if (first) activateTab(box, first.dataset.tab)
	})

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.wpbyvs-channel-tab-btn')
		if (!btn) return
		var box = btn.closest('.wpbyvs-metabox')
		if (!box) return
		activateTab(box, btn.dataset.tab)
	})
})()

/**
 * Metabox — copy a field value or shortcode to the clipboard, confirming with
 * a brief checkmark on the clicked button.
 */
;(function () {

	function copyToClipboard(text) {
		navigator.clipboard.writeText(text).catch(function () {
			var ta = document.createElement('textarea')
			ta.value = text
			ta.style.position = 'fixed'
			ta.style.opacity  = '0'
			document.body.appendChild(ta)
			ta.select()
			document.execCommand('copy')
			document.body.removeChild(ta)
		})
	}

	function flashCopied(btn) {
		btn.classList.add('wpbyvs-copied')
		setTimeout(function () {
			btn.classList.remove('wpbyvs-copied')
		}, 1000)
	}

	document.addEventListener('click', function (e) {
		var valBtn = e.target.closest('.wpbyvs-copy-val-btn')
		if (valBtn) {
			var wrap  = valBtn.closest('.wpbyvs-mb-field')
			var input = wrap ? wrap.querySelector('.wpbyvs-mb-input, .wpbyvs-mb-textarea') : null
			copyToClipboard(input ? input.value : '')
			flashCopied(valBtn)
			return
		}

		var scBtn = e.target.closest('.wpbyvs-copy-sc-btn')
		if (scBtn) {
			copyToClipboard(scBtn.dataset.shortcode || '')
			flashCopied(scBtn)
		}
	})
})()

;(function () {
	document.querySelector('.wpbyvs-developer-fields-toggle').addEventListener('click', function (e) {
		document.querySelector('.wpbyvs-developer-fields').classList.toggle('wpbyvs-hidden')
	})
})()
