/**
 * Post metabox — vertical tab switching.
 */
;(function () {
	function activateTab(box, tab) {
		box.querySelectorAll('.ys-channel-tab-btn').forEach(function (b) {
			var on = b.dataset.tab === tab
			b.classList.toggle('ys-channel-tab-btn--active', on)
			b.setAttribute('aria-selected', String(on))
		})
		box.querySelectorAll('.ys-channel-tab-panel').forEach(function (p) {
			p.classList.toggle('ys-hidden', p.dataset.panel !== tab)
		})
	}

	document.querySelectorAll('.yousync-metabox').forEach(function (box) {
		var first = box.querySelector('.ys-channel-tab-btn')
		if (first) activateTab(box, first.dataset.tab)
	})

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.ys-channel-tab-btn')
		if (!btn) return
		var box = btn.closest('.yousync-metabox')
		if (!box) return
		activateTab(box, btn.dataset.tab)
	})
})()

/**
 * Metabox — copy to clipboard and toast notification.
 */
;(function () {

	var toastEl    = null
	var toastTimer = null

	function showToast() {
		if (!toastEl) {
			toastEl = document.createElement('div')
			toastEl.id = 'ys-copy-toast'
			toastEl.textContent = '✓  Copied!'
			document.body.appendChild(toastEl)
		}
		toastEl.classList.add('ys-copy-toast--visible')
		clearTimeout(toastTimer)
		toastTimer = setTimeout(function () {
			toastEl.classList.remove('ys-copy-toast--visible')
		}, 1500)
	}

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
		showToast()
	}

	document.addEventListener('click', function (e) {
		var field = e.target.closest('.ys-mb-input, .ys-mb-textarea')
		if (field && !field.disabled) {
			copyToClipboard(field.value)
		}
	})
})()

;(function () {
	document.querySelector('.ys-developer-fields-toggle').addEventListener('click', function (e) {
		document.querySelector('.ys-developer-fields').classList.toggle('ys-hidden')
	})
})()
