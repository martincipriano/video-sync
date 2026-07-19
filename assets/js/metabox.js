/**
 * Post metabox — vertical tab switching.
 */
;(function () {
	function activateTab(box, tab) {
		box.querySelectorAll('.buoyvs-channel-tab-btn').forEach(function (b) {
			var on = b.dataset.tab === tab
			b.classList.toggle('buoyvs-channel-tab-btn--active', on)
			b.setAttribute('aria-selected', String(on))
		})
		box.querySelectorAll('.buoyvs-channel-tab-panel').forEach(function (p) {
			p.classList.toggle('buoyvs-hidden', p.dataset.panel !== tab)
		})
	}

	document.querySelectorAll('.buoyvs-metabox').forEach(function (box) {
		var first = box.querySelector('.buoyvs-channel-tab-btn')
		if (first) activateTab(box, first.dataset.tab)
	})

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.buoyvs-channel-tab-btn')
		if (!btn) return
		var box = btn.closest('.buoyvs-metabox')
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
		btn.classList.add('buoyvs-copied')
		setTimeout(function () {
			btn.classList.remove('buoyvs-copied')
		}, 1000)
	}

	document.addEventListener('click', function (e) {
		var valBtn = e.target.closest('.buoyvs-copy-val-btn')
		if (valBtn) {
			var wrap  = valBtn.closest('.buoyvs-mb-field')
			var input = wrap ? wrap.querySelector('.buoyvs-mb-input, .buoyvs-mb-textarea') : null
			copyToClipboard(input ? input.value : '')
			flashCopied(valBtn)
			return
		}

		var scBtn = e.target.closest('.buoyvs-copy-sc-btn')
		if (scBtn) {
			copyToClipboard(scBtn.dataset.shortcode || '')
			flashCopied(scBtn)
		}
	})
})()

;(function () {
	document.querySelector('.buoyvs-developer-fields-toggle').addEventListener('click', function (e) {
		document.querySelector('.buoyvs-developer-fields').classList.toggle('buoyvs-hidden')
	})
})()
