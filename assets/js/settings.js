/**
 * Settings screen — tabs and the Export/Import tab's import button.
 */
(function () {
	'use strict'

	document.addEventListener('DOMContentLoaded', function () {
		initTabs()
		initExport()
		initImport()
	})

	/**
	 * Switch tabs in place (the tabs are ?tab= links, so this is an enhancement).
	 * Arrow keys, Home and End move between tabs, per the ARIA tabs pattern.
	 */
	function initTabs() {
		const tabs   = Array.from(document.querySelectorAll('.buoyvs-tab-button'))
		const footer = document.querySelector('.buoyvs-settings-footer')

		if (!tabs.length) return

		function activate(tab, focus) {
			tabs.forEach(function (t) {
				const selected = t === tab
				t.classList.toggle('active', selected)
				t.setAttribute('aria-selected', selected ? 'true' : 'false')
				t.tabIndex = selected ? 0 : -1

				const panel = document.getElementById(t.getAttribute('aria-controls'))
				if (panel) panel.hidden = !selected
			})

			// "Save Changes" belongs to the settings form, not Export/Import.
			if (footer) footer.hidden = tab.dataset.tab === 'export-import'

			const url = new URL(window.location.href)
			url.searchParams.set('tab', tab.dataset.tab)
			url.searchParams.delete('settings-updated')
			window.history.replaceState(null, '', url)

			if (focus) tab.focus()
		}

		tabs.forEach(function (tab, i) {
			tab.addEventListener('click', function (e) {
				e.preventDefault()
				activate(tab, false)
			})

			tab.addEventListener('keydown', function (e) {
				let next = null
				if (e.key === 'ArrowRight') next = tabs[(i + 1) % tabs.length]
				if (e.key === 'ArrowLeft') next = tabs[(i - 1 + tabs.length) % tabs.length]
				if (e.key === 'Home') next = tabs[0]
				if (e.key === 'End') next = tabs[tabs.length - 1]
				if (!next) return
				e.preventDefault()
				activate(next, true)
			})
		})
	}

	/**
	 * Disable the export button while no channel is ticked.
	 */
	function initExport() {
		const form = document.querySelector('.buoyvs-export-form')
		if (!form) return

		const boxes  = Array.from(form.querySelectorAll('input[name="channels[]"]'))
		const submit = form.querySelector('.buoyvs-export-submit')
		if (!boxes.length || !submit) return

		function update() {
			submit.disabled = !boxes.some(function (box) { return box.checked })
		}

		boxes.forEach(function (box) { box.addEventListener('change', update) })
		update()
	}

	/**
	 * The file input is hidden once JS runs: the "Import Sync Rules" button
	 * opens the file picker, and choosing a file submits (after a confirm
	 * when the import replaces the current setup).
	 * Without JS the plain file input + submit button still work.
	 */
	function initImport() {
		const form = document.querySelector('.buoyvs-import-form')
		if (!form) return

		const input   = form.querySelector('input[type="file"]')
		const trigger = form.querySelector('.buoyvs-import-trigger')
		if (!input || !trigger) return

		form.classList.add('is-enhanced')

		trigger.addEventListener('click', function (e) {
			e.preventDefault()
			input.click()
		})

		input.addEventListener('change', function () {
			if (!input.files.length) return

			// Only a full replace is destructive enough to ask first.
			const mode = form.querySelector('input[name="buoyvs_import_mode"]:checked')
			const ok   = (!mode || mode.value === 'replace') ? window.confirm(form.dataset.confirm) : true
			if (ok) {
				form.submit()
			} else {
				input.value = ''
			}
		})
	}
})()
