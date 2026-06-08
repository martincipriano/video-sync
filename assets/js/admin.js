const channelsContainer = document.getElementById('ys-channels')
const singleSyncRules  = document.getElementById('ys-rules')
const delegationRoot   = channelsContainer || singleSyncRules

if (delegationRoot) {

/**
 * Update the rule label span from the selected action. Free runs every rule
 * once, so the schedule suffix is fixed.
 */
function updateRuleLabel(rule) {
	const label = rule.querySelector('.ys-rule-heading')
	if (!label) return

	const actionSelect   = rule.querySelector('.ys-action')
	const selectedAction = actionSelect?.selectedOptions[0]
	const hasAction = selectedAction && selectedAction.value

	if (!hasAction) {
		label.textContent = 'Please select an action.'
		rule.classList.add('ys-rule--no-action')
		return
	}

	rule.classList.remove('ys-rule--no-action')

	const actionText = selectedAction.textContent.trim()
	label.textContent = actionText + ' immediately after enabling and saving.'
}

// Init labels on load
delegationRoot.querySelectorAll('.ys-rule').forEach(updateRuleLabel)

/**
 * Get a localStorage key for a sync rule's accordion state.
 */
function getRuleStorageKey(rule) {
	const channel = rule.closest('.ys-channel')
	const chIdx   = channel ? channel.dataset.channelIndex : 'single'
	return `yousync_accordion_ch${chIdx}_rule${rule.dataset.ruleIndex}`
}

/**
 * Toggle accordion expand/collapse on a sync rule.
 */
function toggleRuleAccordion(rule, header) {
	const isExpanded = header.getAttribute('aria-expanded') === 'true'
	header.setAttribute('aria-expanded', isExpanded ? 'false' : 'true')
	rule.classList.toggle('ys-collapsed', isExpanded)
	try { localStorage.setItem(getRuleStorageKey(rule), isExpanded ? 'collapsed' : 'expanded') } catch (e) {}
}

// Restore accordion state from localStorage on page load, then reveal containers
delegationRoot.querySelectorAll('.ys-rule').forEach(function(rule) {
	try {
		if (localStorage.getItem(getRuleStorageKey(rule)) === 'collapsed') {
			rule.classList.add('ys-collapsed')
			const header = rule.querySelector('.ys-rule-header')
			if (header) header.setAttribute('aria-expanded', 'false')
		}
	} catch (e) {}
})
delegationRoot.querySelectorAll('.ys-rules--init').forEach(function(el) {
	el.classList.remove('ys-rules--init')
})

// Click handler
delegationRoot.addEventListener('click', function(e) {
	const header = e.target.closest('.ys-rule-header')
	if (header && !e.target.closest('button, label')) {
		const rule = header.closest('.ys-rule')
		if (rule) toggleRuleAccordion(rule, header)
	}
})

// Keyboard handler
delegationRoot.addEventListener('keydown', function(e) {
	if (e.key !== 'Enter' && e.key !== ' ') return
	if (!e.target.classList.contains('ys-rule-header')) return
	e.preventDefault()
	const rule = e.target.closest('.ys-rule')
	if (rule) toggleRuleAccordion(rule, e.target)
})

/**
 * Reindex all sync rules within a container to ensure sequential numbering (0, 1, 2...)
 */
function reindexRules(container) {
	const rules = container.querySelectorAll('.ys-rule')
	rules.forEach((rule, newIndex) => {
		const oldIndex = rule.getAttribute('data-rule-index')

		// Update data attribute
		rule.setAttribute('data-rule-index', newIndex)

		// Update all name attributes — match the rule-level index in the name
		rule.querySelectorAll('[name]').forEach(element => {
			const name = element.getAttribute('name')
			// For channels page: channels[X][sync_rules][OLD] → channels[X][sync_rules][NEW]
			// For single page: sync_rules[OLD] → sync_rules[NEW]
			element.setAttribute('name', name.replace(
				new RegExp(`\\[sync_rules\\]\\[${oldIndex}\\]|^sync_rules\\[${oldIndex}\\]`),
				(match) => match.replace(`[${oldIndex}]`, `[${newIndex}]`)
			))
		})

		// Update all id attributes
		rule.querySelectorAll('[id]').forEach(element => {
			const id = element.getAttribute('id')
			if (id.includes(oldIndex)) {
				let newId = id.replace(new RegExp(`-${oldIndex}$`), `-${newIndex}`)
				newId = newId.replace(`sync-rules-${oldIndex}-`, `sync-rules-${newIndex}-`)
				element.setAttribute('id', newId)
			}
		})

		// Update all for attributes
		rule.querySelectorAll('[for]').forEach(element => {
			const forAttr = element.getAttribute('for')
			if (forAttr.includes(oldIndex)) {
				let newFor = forAttr.replace(new RegExp(`-${oldIndex}$`), `-${newIndex}`)
				newFor = newFor.replace(`sync-rules-${oldIndex}-`, `sync-rules-${newIndex}-`)
				element.setAttribute('for', newFor)
			}
		})

	})
}

/**
 * Toggle the disabled-rule notice when a rule is enabled/disabled.
 */
delegationRoot.addEventListener('change', function(e) {
	if (e.target.classList.contains('ys-rule-toggle')) {
		const notice = e.target.closest('.ys-rule')?.querySelector('.ys-rule-disabled-notice')
		if (notice) notice.classList.toggle('ys-hidden', e.target.checked)
	}
})

/**
 * Update dynamic labels that depend on the selected action/resource.
 */
function updateRuleDynamicLabels(rule) {
	const action   = rule.querySelector('.ys-action')?.value ?? ''
	const resource = rule.querySelector('.ys-action')?.selectedOptions[0]?.dataset.resource ?? ''

	const maxItemsLabel = rule.querySelector('.ys-max-items-label')
	if (maxItemsLabel) {
		const textNode = maxItemsLabel.firstChild
		const newText  = resource === 'video' ? 'Videos per run'
			: resource === 'playlist' ? 'Playlists per run'
			: 'Items per run'
		if (textNode && textNode.nodeType === Node.TEXT_NODE) {
			textNode.textContent = newText + ' '
		}
	}

	const postTypeLabel = rule.querySelector('.ys-post-type-label')
	if (postTypeLabel) {
		const textNode = postTypeLabel.firstChild
		const newText  = action === 'playlists_sync_new'
			? 'Save synced playlists as post type'
			: action === 'channel_sync_new'
				? 'Save synced channel as post type'
				: 'Save synced videos as post type'
		if (textNode && textNode.nodeType === Node.TEXT_NODE) {
			textNode.textContent = newText + ' '
		}
	}
}

// Init dynamic labels on load
delegationRoot.querySelectorAll('.ys-rule').forEach(updateRuleDynamicLabels)

/**
 * Apply correct visibility of post-type and taxonomy wrappers based on the selected action.
 */
function applyRuleActionVisibility(rule) {
	const action    = rule.querySelector('.ys-action')?.value ?? ''
	const isSyncNew = action === 'videos_sync_new' || action === 'playlists_sync_new' || action === 'channel_sync_new'
	const postTypeWrapper = rule.querySelector('.ys-post-type-wrapper')
	const postTypeSelect  = rule.querySelector('[name*="[destination_post_type]"]')
	if (postTypeWrapper) {
		postTypeWrapper.classList.toggle('ys-hidden', !isSyncNew)
	}
	if (postTypeSelect) {
		postTypeSelect.disabled = !isSyncNew
	}
	rule.querySelector('.ys-items-per-run-wrapper')?.classList.toggle('ys-hidden', !!action && action.startsWith('channel_'))
}

// Initialize post-type visibility on load for all existing rules
delegationRoot.querySelectorAll('.ys-rule').forEach(applyRuleActionVisibility)

/**
 * Refresh action-dependent UI when a rule's action changes.
 */
delegationRoot.addEventListener('change', function(e) {

	if (e.target.classList.contains('ys-action')) {
		const syncRule = e.target.closest('.ys-rule')
		applyRuleActionVisibility(syncRule)
		updateRuleDynamicLabels(syncRule)
		updateRuleLabel(syncRule)
	}

	if (e.target.classList.contains('ys-wizard-action-select')) {
		const wizard = e.target.closest('.ys-wizard')
		if (!wizard) return
		const resource = e.target.selectedOptions[0]?.dataset.resource ?? ''
		const label = wizard.querySelector('.ys-max-items-label')
		if (label) {
			const newText = resource === 'video'    ? 'Videos per run'
				: resource === 'playlist' ? 'Playlists per run'
				: 'Items per run'
			const tn = label.firstChild
			if (tn && tn.nodeType === Node.TEXT_NODE) tn.textContent = newText
			else label.textContent = newText
		}
		// Clear error state when user makes a selection.
		e.target.closest('.ys-form-group')?.classList.remove('ys-form-group--error')
		// Show/hide post type wrapper in step 3 based on action.
		const action         = e.target.value
		const isSyncNew       = action === 'videos_sync_new' || action === 'playlists_sync_new' || action === 'channel_sync_new'
		const isChannelAction = action.startsWith('channel_')
		wizard.querySelector('.ys-wizard-post-type-wrapper')?.classList.toggle('ys-hidden', !isSyncNew)
		wizard.querySelector('.ys-items-per-run-wrapper')?.classList.toggle('ys-hidden', isChannelAction)
		wizard.dataset.isChannelAction = isChannelAction ? '1' : ''
		updateWizardProgressIndicators(wizard)
	}
})

/**
 * Handle adding new sync rules.
 *
 * On the Channels page, clicking "Add sync rule" shows the wizard instead of
 * inserting a blank accordion card inline. On any other page, the old
 * behaviour (insert from template) is preserved.
 */
delegationRoot.addEventListener('click', function(e) {
	const btn = e.target.closest('.ys-add-rule')
	if (!btn) return
	e.preventDefault()

	const channelGroup = btn.closest('.ys-channel')

	// Channels page: show wizard.
	if (channelGroup && youSync.isChannelsPage) {
		const wizard = channelGroup.querySelector('.ys-wizard')
		if (wizard) {
			wizard.classList.remove('ys-hidden')
			wizardReset(wizard)
			wizard.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
		}
		return
	}

	// Fallback: insert inline (single-channel page or no wizard present).
	const targetContainer = channelGroup
		? channelGroup.querySelector('.ys-rules')
		: singleSyncRules

	if (!targetContainer) return

	const rules = [...targetContainer.querySelectorAll('.ys-rule')]
	const newIndex = Math.max(-1, ...rules.map(r => +r.dataset.ruleIndex)) + 1
	let template = youSync.syncRule.rule
		.replaceAll('{{INDEX}}', newIndex)
		.replaceAll('{{NUMBER}}', newIndex + 1)

	if (channelGroup) {
		template = template.replaceAll('{{CHANNEL_INDEX}}', channelGroup.dataset.channelIndex)
	}

	targetContainer.insertAdjacentHTML('beforeend', template)
	applyRuleActionVisibility(targetContainer.lastElementChild)
	updateRuleDynamicLabels(targetContainer.lastElementChild)
	updateRuleLabel(targetContainer.lastElementChild)
	targetContainer.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'start' })
})

/**
 * Handle removal of sync rules
 */
delegationRoot.addEventListener('click', function(e) {
	if (e.target.classList.contains('ys-remove-rule')) {
		if (!window.confirm('Delete this automation? This action cannot be undone.')) return

		const rule = e.target.closest('.ys-rule')
		const rulesContainer = rule.closest('.ys-rules')
		const totalRules = rulesContainer.querySelectorAll('.ys-rule').length

		// If this is the last rule, clear the entire container
		if (totalRules === 1) {
			rulesContainer.innerHTML = ''
		} else {
			rule.remove()
			// Reindex remaining rules to ensure sequential numbering
			reindexRules(rulesContainer)
		}
	}
})

/**
 * Calculate and display estimated YouTube API quota cost for a sync rule.
 */
function updateQuotaEstimate(rule) {
	const el = rule.querySelector('.ys-quota-estimate')
	if (!el) return

	const action     = rule.querySelector('.ys-action')?.value || ''
	const videoCount = parseInt(rule.closest('.ys-rules')?.dataset.videoCount) || 0

	if (action === 'videos_sync_new') {
		const batches = Math.ceil(Math.max(1, videoCount) / 50)
		const perRun  = 1 + batches * 2
		el.textContent = `Approx. ${perRun} unit${perRun !== 1 ? 's' : ''} per run`
		el.classList.remove('ys-hidden')
	} else if (action === 'playlists_sync_new') {
		el.textContent = 'Approx. 1 unit per 50 playlists'
		el.classList.remove('ys-hidden')
	} else {
		el.textContent = ''
		el.classList.add('ys-hidden')
	}
}

delegationRoot.querySelectorAll('.ys-rule').forEach(updateQuotaEstimate)


delegationRoot.addEventListener('change', function(e) {
	if (e.target.classList.contains('ys-action')) {
		updateQuotaEstimate(e.target.closest('.ys-rule'))
	}
})

// ============================================================
// Sync Rule Wizard (flat scope inside delegationRoot for function access)
// ============================================================
const TOTAL_STEPS    = 3
const SLIDE_DURATION = 220 // ms

/**
 * Update the progress bar to show only steps the user will actually visit,
 * renumbering them sequentially (1, 2, 3…) so there are no gaps.
 */
function updateWizardProgressIndicators(wizard) {
	let displayNum = 1
	wizard.querySelectorAll('.ys-wizard-step-indicator').forEach(indicator => {
		indicator.classList.remove('ys-hidden')
		const prevLine = indicator.previousElementSibling
		if (prevLine?.classList.contains('ys-wizard-progress-line')) {
			prevLine.classList.remove('ys-hidden')
		}
		indicator.textContent = displayNum++
	})
}

/**
 * Reset the wizard to step 1.
 */
function wizardReset(wizard) {
	wizard.querySelector('.ys-items-per-run-wrapper')?.classList.remove('ys-hidden')
	updateWizardProgressIndicators(wizard)
	wizardShowStep(wizard, 1)
	// Clear error messages.
	wizard.querySelectorAll('.ys-wizard-error').forEach(el => el.classList.add('ys-hidden'))
	wizard.querySelector('.ys-wizard-finish')?.removeAttribute('disabled')
	// Restore default post type from channel settings.
	const defaultPostType = wizard.dataset.defaultPostType
	const ptSelect = wizard.querySelector('.ys-wizard-post-type')
	if (ptSelect && defaultPostType) ptSelect.value = defaultPostType
}

/**
 * Show a specific step panel and update progress indicators.
 */
function wizardShowStep(wizard, step, direction = 'none') {
	const panels  = wizard.querySelector('.ys-wizard-panels')
	const current = panels?.querySelector('.ys-wizard-panel:not(.ys-hidden)') ?? null
	const next    = wizard.querySelector(`.ys-wizard-panel[data-step="${step}"]`)

	if (!next) return

	// Update dataset + progress indicators immediately.
	wizard.dataset.currentStep = step
	wizard.querySelectorAll('.ys-wizard-step-indicator').forEach(dot => {
		const dotStep = +dot.dataset.step
		dot.classList.toggle('ys-wizard-step-indicator--active', dotStep === step)
		dot.classList.toggle('ys-wizard-step-indicator--done', dotStep < step)
	})

	// Skip animation if: no wrapper, no outgoing panel, same panel, or no direction given.
	if (!panels || !current || current === next || direction === 'none') {
		wizard.querySelectorAll('.ys-wizard-panel').forEach(p => p.classList.toggle('ys-hidden', p !== next))
		return
	}

	// Guard against rapid clicks during animation.
	if (wizard.dataset.animating) return
	wizard.dataset.animating = '1'

	// Freeze wrapper height so the container doesn't resize mid-transition.
	panels.style.height   = current.offsetHeight + 'px'
	panels.style.overflow = 'hidden'
	panels.style.position = 'relative'

	// Pin both panels absolutely so they occupy the same space.
	const pin = (panel, x) => {
		panel.style.position  = 'absolute'
		panel.style.top       = '0'
		panel.style.left      = '0'
		panel.style.width     = '100%'
		panel.style.transform = `translateX(${x}%)`
	}

	next.classList.remove('ys-hidden')
	pin(current, 0)
	pin(next, direction === 'forward' ? 100 : -100)

	// Force reflow so initial transforms are painted before transition starts.
	next.getBoundingClientRect()

	const tr = `transform ${SLIDE_DURATION}ms ease`
	current.style.transition = tr
	next.style.transition    = tr

	current.style.transform = `translateX(${direction === 'forward' ? -100 : 100}%)`
	next.style.transform    = 'translateX(0%)'

	setTimeout(() => {
		const unpin = p => {
			p.style.position   = ''
			p.style.top        = ''
			p.style.left       = ''
			p.style.width      = ''
			p.style.transform  = ''
			p.style.transition = ''
		}
		unpin(current)
		unpin(next)
		current.classList.add('ys-hidden')
		panels.style.height   = ''
		panels.style.overflow = ''
		panels.style.position = ''
		delete wizard.dataset.animating
	}, SLIDE_DURATION + 20)
}

/**
 * Validate the current step. Returns true if valid.
 */
function wizardValidateStep(wizard, step) {
	if (step === 1) {
		const actionSelect = wizard.querySelector('.ys-wizard-action-select')
		if (!actionSelect?.value) {
			actionSelect?.closest('.ys-form-group')?.classList.add('ys-form-group--error')
			return false
		}
		actionSelect.closest('.ys-form-group')?.classList.remove('ys-form-group--error')
	}
	return true
}

/**
 * Advance to the next step.
 */
function wizardAdvance(wizard, fromStep) {
	if (!wizardValidateStep(wizard, fromStep)) return

	// After Step 1: show/hide post type wrapper in Step 3 based on action.
	if (fromStep === 1) {
		const action = wizard.querySelector('.ys-wizard-action-select')?.value ?? ''
		const isSyncNew = action === 'videos_sync_new' || action === 'playlists_sync_new' || action === 'channel_sync_new'
		wizard.querySelector('.ys-wizard-post-type-wrapper')?.classList.toggle('ys-hidden', !isSyncNew)
	}

	const next = fromStep + 1
	if (next > TOTAL_STEPS) return
	wizardShowStep(wizard, next, 'forward')
}

/**
 * Go back one step.
 */
function wizardBack(wizard, fromStep) {
	const prev = fromStep - 1
	if (prev < 1) return
	wizardShowStep(wizard, prev, 'back')
}

/**
 * Build the rule object from wizard inputs.
 *
 * Free rules carry only action, schedule (always 'once'), the per-run cap, and
 * the destination post type. Taxonomy, field mapping, conditions and metadata
 * updates are Pro and are not collected.
 */
function collectWizardRule(wizard) {
	const action    = wizard.querySelector('.ys-wizard-action-select')?.value ?? ''
	const schedule  = wizard.querySelector('.ys-wizard-schedule-select')?.value ?? 'once'
	const maxVideos = parseInt(wizard.querySelector('.ys-wizard-max-videos')?.value) || 0
	const postType  = wizard.querySelector('.ys-wizard-post-type')?.value ?? ''

	return {
		action,
		schedule,
		max_videos:            maxVideos,
		destination_post_type: postType,
	}
}

/**
 * Submit the wizard via AJAX and insert the resulting accordion card.
 */
function wizardSubmit(wizard) {
	const chIndex = parseInt(wizard.dataset.channelIndex) || 0
	const rule    = collectWizardRule(wizard)

	const finishBtn = wizard.querySelector('.ys-wizard-finish')
	if (finishBtn) finishBtn.setAttribute('disabled', 'disabled')

	const errEl = wizard.querySelector('[data-step="3"] .ys-wizard-error')

	// Build FormData — jQuery serializes nested objects correctly.
	const data = {
		action:        'yousync_add_rule',
		nonce:         youSync.addRuleNonce,
		channel_index: chIndex,
		rule:          rule,
	}

	jQuery.ajax({
		url:      youSync.ajaxUrl,
		method:   'POST',
		data:     data,
		dataType: 'json',
	}).done(function(response) {
		if (!response.success) {
			if (errEl) {
				errEl.textContent = response.data || 'An error occurred. Please try again.'
				errEl.classList.remove('ys-hidden')
			}
			if (finishBtn) finishBtn.removeAttribute('disabled')
			return
		}

		// Insert the accordion card into the rules container.
		const channelGroup = wizard.closest('.ys-channel')
		const rulesContainer = channelGroup?.querySelector('.ys-rules')
		if (rulesContainer && response.data.html) {
			rulesContainer.insertAdjacentHTML('beforeend', response.data.html)
			const newRule = rulesContainer.lastElementChild
			if (newRule) {
				applyRuleActionVisibility(newRule)
				updateRuleDynamicLabels(newRule)
				updateRuleLabel(newRule)
				newRule.scrollIntoView({ behavior: 'smooth', block: 'start' })
			}
		}

		// Hide wizard, restore button.
		wizardClose(wizard)

	}).fail(function() {
		if (errEl) {
			errEl.textContent = 'A network error occurred. Please try again.'
			errEl.classList.remove('ys-hidden')
		}
		if (finishBtn) finishBtn.removeAttribute('disabled')
	})
}

/**
 * Close the wizard and restore the "Add sync rule" button.
 */
function wizardClose(wizard) {
	wizard.classList.add('ys-hidden')
}

// ---- Event delegation for all wizard interactions (channels page only) ----
if (youSync.isChannelsPage) {
delegationRoot.addEventListener('click', function(e) {

	// Cancel
	if (e.target.closest('.ys-wizard-cancel')) {
		const wizard = e.target.closest('.ys-wizard')
		if (wizard) wizardClose(wizard)
		return
	}

	// Next
	const nextBtn = e.target.closest('.ys-wizard-next')
	if (nextBtn) {
		const wizard = nextBtn.closest('.ys-wizard')
		if (wizard) wizardAdvance(wizard, +nextBtn.dataset.step)
		return
	}

	// Back
	const backBtn = e.target.closest('.ys-wizard-back')
	if (backBtn) {
		const wizard = backBtn.closest('.ys-wizard')
		if (wizard) wizardBack(wizard, +backBtn.dataset.step)
		return
	}

	// Finish (final step submit)
	if (e.target.closest('.ys-wizard-finish')) {
		const wizard = e.target.closest('.ys-wizard')
		if (wizard) wizardSubmit(wizard)
		return
	}
})

} // end if (youSync.isChannelsPage) wizard events

} // end if (delegationRoot)


/**
 * Channels page — Accordion toggle, Add Channel, Remove Channel
 */
;(function() {
	const container = document.getElementById('ys-channels')
	if (!container) return

	/**
	 * Ensure all remove buttons are enabled (deletion is always allowed).
	 */
	function updateRemoveButtons() {
		container.querySelectorAll('.ys-remove-channel').forEach(function(btn) {
			btn.disabled = false
			btn.removeAttribute('data-tooltip')
		})
	}

	/**
	 * Toggle error state on a Channel ID input based on whether it is empty.
	 */
	function updateChannelIdState(input) {
		input.classList.toggle('ys-error', input.value.trim() === '')
	}

	// Clear error state as the user types a Channel ID.
	container.addEventListener('input', function(e) {
		if (e.target.matches('input[name$="[youtube_id]"]')) {
			updateChannelIdState(e.target)
		}
	})

	// Validate Channel ID inputs on form submit.
	const channelsForm = container.closest('form')
	if (channelsForm) {
		channelsForm.noValidate = true
		channelsForm.addEventListener('submit', function(e) {
			let hasError = false
			container.querySelectorAll('input[name$="[youtube_id]"]').forEach(function(input) {
				updateChannelIdState(input)
				if (input.value.trim() === '') hasError = true
			})
			if (hasError) {
				e.preventDefault()
				container.querySelector('input[name$="[youtube_id]"].ys-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
			}
		}, true)
	}

	/**
	 * Toggle accordion expand/collapse on a channel group header.
	 */
	function chCollapseKey(group) {
		const ytInput = group.querySelector('input[name$="[youtube_id]"]')
		const ytId = ytInput ? ytInput.value.trim() : ''
		return ytId ? `ys_ch_open_${ytId}` : `ys_ch_open_idx_${group.dataset.channelIndex}`
	}

	function toggleAccordion(header) {
		const group = header.closest('.ys-channel')
		if (!group) return
		const isExpanded = header.getAttribute('aria-expanded') === 'true'
		header.setAttribute('aria-expanded', isExpanded ? 'false' : 'true')
		group.classList.toggle('ys-collapsed', isExpanded)
		try { localStorage.setItem(chCollapseKey(group), isExpanded ? '0' : '1') } catch (ex) {}
	}

	container.addEventListener('click', function(e) {
		const header = e.target.closest('.ys-channel-header')
		if (header && !e.target.closest('button')) {
			toggleAccordion(header)
		}
	})

	container.addEventListener('keydown', function(e) {
		if (e.key !== 'Enter' && e.key !== ' ') return
		const header = e.target.closest('.ys-channel-header')
		if (header) {
			e.preventDefault()
			toggleAccordion(header)
		}
	})

	/**
	 * Ensure every channel group has a footer with a remove button, or remove all footers.
	 */
	// Add Channel button
	const addBtn = document.getElementById('ys-add-channel')
	if (addBtn) {
		addBtn.addEventListener('click', function(e) {
			e.preventDefault()

			const groups = container.querySelectorAll('.ys-channel')
			const newIndex = groups.length

			// Clone the first channel group as a template
			const firstGroup = groups[0]
			const clone = firstGroup.cloneNode(true)

			// Ensure expanded state
			clone.classList.remove('ys-collapsed')
			const cloneHeader = clone.querySelector('.ys-channel-header')
			if (cloneHeader) cloneHeader.setAttribute('aria-expanded', 'true')

			// Update data-channel-index
			clone.dataset.channelIndex = newIndex

			// Update heading
			const heading = clone.querySelector('.ys-channel-header h2')
			if (heading) heading.textContent = 'Channel ' + (newIndex + 1)

			// Reset channel icon to placeholder letter
			const icon = clone.querySelector('.ys-channel-icon')
			if (icon) icon.innerHTML = 'C'

			// Clear all input values and update name attributes
			clone.querySelectorAll('input[type="text"]').forEach(input => {
				input.value = ''
				const name = input.getAttribute('name')
				if (name) input.setAttribute('name', name.replace(/channels\[\d+\]/, `channels[${newIndex}]`))
			})
			clone.querySelectorAll('textarea').forEach(textarea => {
				textarea.value = ''
			})
			clone.querySelectorAll('select').forEach(select => {
				select.selectedIndex = 0
				const name = select.getAttribute('name')
				if (name) select.setAttribute('name', name.replace(/channels\[\d+\]/, `channels[${newIndex}]`))
			})

			// Clear sync rules
			const syncRulesDiv = clone.querySelector('.ys-rules')
			if (syncRulesDiv) syncRulesDiv.innerHTML = ''

			// Clear field mapping rows in Settings tab and update data-name-prefix.
			const cloneFmRows = clone.querySelector('.ys-channel-field-mapping-rows')
			if (cloneFmRows) {
				cloneFmRows.innerHTML = ''
				cloneFmRows.dataset.namePrefix = `channels[${newIndex}][field_mapping]`
			}

			// Reset tab state to default (info) and clear any persisted tab for this index.
			clone.querySelectorAll('.ys-channel-tab-btn').forEach(b => {
				const on = b.dataset.tab === 'info'
				b.classList.toggle('ys-channel-tab-btn--active', on)
				b.setAttribute('aria-selected', String(on))
			})
			clone.querySelectorAll('.ys-channel-tab-panel').forEach(p => {
				p.classList.toggle('ys-hidden', p.dataset.panel !== 'info')
			})
			try { localStorage.removeItem(`ys_ch_tab_${newIndex}`) } catch (ex) {}

			// Update IDs to avoid duplicates
			clone.querySelectorAll('[id]').forEach(el => {
				const id = el.getAttribute('id')
				el.setAttribute('id', id.replace(/-\d+$/, `-${newIndex}`))
			})
			clone.querySelectorAll('[for]').forEach(el => {
				const forAttr = el.getAttribute('for')
				el.setAttribute('for', forAttr.replace(/-\d+$/, `-${newIndex}`))
			})

			// Mark as new channel (hides Sync/Settings/History tabs)
			clone.classList.add('ys-channel--new')

			// Remove disabled readonly info fields (title, subs, videos, description)
			const infoPanel = clone.querySelector('[data-panel="info"]')
			if (infoPanel) {
				infoPanel.querySelectorAll('.ys-mb-field').forEach(field => {
					if (field.querySelector('input[disabled], textarea[disabled]')) field.remove()
				})
			}

			// Clear history panel content and remove error badge
			const historyPanel = clone.querySelector('[data-panel="history"]')
			if (historyPanel) historyPanel.innerHTML = '<p class="ys-history-empty">No sync history yet.</p>'
			const historyBadge = clone.querySelector('.ys-history-badge')
			if (historyBadge) historyBadge.remove()
			container.appendChild(clone)
			clone.scrollIntoView({ behavior: 'smooth', block: 'start' })
			updateRemoveButtons()

			// Auto-focus the Channel ID input on the new card.
			const newChannelIdInput = clone.querySelector('input[name$="[youtube_id]"]')
			if (newChannelIdInput) setTimeout(() => newChannelIdInput.focus(), 150)
		})
	}

	// Remove Channel — delegated
	container.addEventListener('click', function(e) {
		const btn = e.target.closest('.ys-remove-channel')
		if (!btn) return
		e.preventDefault()

		const group = btn.closest('.ys-channel')
		if (!group) return

		if (!window.confirm('Are you sure you want to delete this channel? All its sync rules will be removed.')) return

		// Capture a clone before removal in case this is the last channel
		const clone = group.cloneNode(true)
		const isLast = container.querySelectorAll('.ys-channel').length === 1

		group.remove()

		if (isLast) {
			// Reset the clone to a fresh blank channel at index 0
			clone.classList.remove('ys-collapsed')
				clone.dataset.channelIndex = 0

				const cloneHeader = clone.querySelector('.ys-channel-header')
				if (cloneHeader) cloneHeader.setAttribute('aria-expanded', 'true')

				const cloneHeading = clone.querySelector('.ys-channel-header h2')
				if (cloneHeading) cloneHeading.textContent = 'Channel 1'

				const icon = clone.querySelector('.ys-channel-icon')
				if (icon) icon.innerHTML = 'C'

				clone.querySelectorAll('input[type="text"]').forEach(input => {
					input.value = ''
					const name = input.getAttribute('name')
					if (name) input.setAttribute('name', name.replace(/channels\[\d+\]/, 'channels[0]'))
				})

				clone.querySelectorAll('textarea').forEach(textarea => {
					textarea.value = ''
				})

				clone.querySelectorAll('select').forEach(select => {
					select.selectedIndex = 0
					const name = select.getAttribute('name')
					if (name) select.setAttribute('name', name.replace(/channels\[\d+\]/, 'channels[0]'))
				})

				const syncRulesDiv = clone.querySelector('.ys-rules')
				if (syncRulesDiv) syncRulesDiv.innerHTML = ''

				// Clear FM rows and reset data-name-prefix.
				const isLastFmRows = clone.querySelector('.ys-channel-field-mapping-rows')
				if (isLastFmRows) {
					isLastFmRows.innerHTML = ''
					isLastFmRows.dataset.namePrefix = 'channels[0][field_mapping]'
				}

				// Reset tab to default.
				clone.querySelectorAll('.ys-channel-tab-btn').forEach(b => {
					const on = b.dataset.tab === 'info'
					b.classList.toggle('ys-channel-tab-btn--active', on)
					b.setAttribute('aria-selected', String(on))
				})
				clone.querySelectorAll('.ys-channel-tab-panel').forEach(p => {
					p.classList.toggle('ys-hidden', p.dataset.panel !== 'info')
				})

				clone.querySelectorAll('[id]').forEach(el => {
					const id = el.getAttribute('id')
					el.setAttribute('id', id.replace(/-\d+$/, '-0'))
				})
				clone.querySelectorAll('[for]').forEach(el => {
					const forAttr = el.getAttribute('for')
					el.setAttribute('for', forAttr.replace(/-\d+$/, '-0'))
				})

				// Mark as new channel (hides Sync/Settings/History tabs)
				clone.classList.add('ys-channel--new')

				// Remove disabled readonly info fields (title, subs, videos, description)
				const infoPanel2 = clone.querySelector('[data-panel="info"]')
				if (infoPanel2) {
					infoPanel2.querySelectorAll('.ys-mb-field').forEach(field => {
						if (field.querySelector('input[disabled], textarea[disabled]')) field.remove()
					})
				}

				// Clear history panel content and remove error badge
				const historyPanel2 = clone.querySelector('[data-panel="history"]')
				if (historyPanel2) historyPanel2.innerHTML = '<p class="ys-history-empty">No sync history yet.</p>'
				const historyBadge2 = clone.querySelector('.ys-history-badge')
				if (historyBadge2) historyBadge2.remove()
				container.appendChild(clone)
				updateRemoveButtons()
				return
			}

			// Reindex remaining channels
			const remaining = container.querySelectorAll('.ys-channel')
			remaining.forEach((g, i) => {
				g.dataset.channelIndex = i
				const heading = g.querySelector('.ys-channel-header h2')
				// Only update generic headings, not channel names
				if (heading && heading.textContent.match(/^Channel \d+$/)) {
					heading.textContent = 'Channel ' + (i + 1)
				}

				// Update name attributes
				g.querySelectorAll('[name]').forEach(el => {
					const name = el.getAttribute('name')
					el.setAttribute('name', name.replace(/channels\[\d+\]/, `channels[${i}]`))
				})

				// Update data-name-prefix on FM rows container.
				const gFmRows = g.querySelector('.ys-channel-field-mapping-rows')
				if (gFmRows) gFmRows.dataset.namePrefix = `channels[${i}][field_mapping]`
			})

			updateRemoveButtons()
	})

	// Restore persisted collapse state on load.
	container.querySelectorAll('.ys-channel').forEach(group => {
		try {
			const stored = localStorage.getItem(chCollapseKey(group))
			if (stored === '0') {
				group.classList.add('ys-collapsed')
				const h = group.querySelector('.ys-channel-header')
				if (h) h.setAttribute('aria-expanded', 'false')
			}
		} catch (ex) {}
	})
})()

// ============================================================
// Channel card vertical tabs
// ============================================================
;(function () {
	const container = document.getElementById('ys-channels')
	if (!container) return

	const DEFAULT_TAB = 'info'
	const storageKey  = (idx) => `ys_ch_tab_${idx}`

	function activateTab(card, tab) {
		card.querySelectorAll('.ys-channel-tab-btn').forEach(b => {
			const on = b.dataset.tab === tab
			b.classList.toggle('ys-channel-tab-btn--active', on)
			b.setAttribute('aria-selected', String(on))
		})
		card.querySelectorAll('.ys-channel-tab-panel').forEach(p => {
			p.classList.toggle('ys-hidden', p.dataset.panel !== tab)
		})
		if (tab === 'history') {
			const badge = card.querySelector('.ys-channel-tab-btn[data-tab="history"] .ys-history-badge')
			if (badge) {
				badge.remove()
				const youtubeId = card.dataset.youtubeId
				if (youtubeId && youSync.markHistoryReadNonce) {
					const fd = new FormData()
					fd.append('action', 'yousync_mark_history_read')
					fd.append('nonce', youSync.markHistoryReadNonce)
					fd.append('youtube_id', youtubeId)
					navigator.sendBeacon(youSync.ajaxUrl, fd)
				}
			}
		}
	}

	// Restore persisted active tab on load.
	container.querySelectorAll('.ys-channel').forEach(card => {
		const saved = localStorage.getItem(storageKey(card.dataset.channelIndex)) || DEFAULT_TAB
		activateTab(card, saved)
	})

	// Delegate tab clicks.
	container.addEventListener('click', e => {
		const btn = e.target.closest('.ys-channel-tab-btn')
		if (!btn) return
		const card = btn.closest('.ys-channel')
		const tab  = btn.dataset.tab
		activateTab(card, tab)
		try { localStorage.setItem(storageKey(card.dataset.channelIndex), tab) } catch (ex) {}
	})

})()


/**
 * Unsaved changes warning — warn before navigating away with pending edits.
 */
;(function() {
	const form = document.querySelector('#ys-channels')?.closest('form')
	if (!form) return

	let isDirty = false

	// Track value changes
	form.addEventListener('input', function() { isDirty = true })
	form.addEventListener('change', function() { isDirty = true })

	// Track structural changes (channels/rules added or removed).
	// Ignore mutations triggered by the sync-progress polling (programmatic updates).
	const observer = new MutationObserver(function() { if (!window._ysSyncUpdate) isDirty = true })
	observer.observe(form, { childList: true, subtree: true })

	// Clear on submit so the save redirect doesn't trigger the warning
	form.addEventListener('submit', function() {
		isDirty = false
		observer.disconnect()
	})

	window.addEventListener('beforeunload', function(e) {
		if (!isDirty) return
		e.preventDefault()
		e.returnValue = ''
	})
})()

/**
 * Infinity icon for max-videos inputs.
 * Shows the icon when value is 0 or empty (= unlimited); hides on click and focuses input.
 */
;(function () {
	function checkUnlimited(input) {
		const icon = input.parentNode.querySelector('.ys-unlimited-icon')
		if (!icon) return
		if (!input.value || input.value === '0') {
			icon.classList.remove('ys-hidden')
		}
	}

	document.addEventListener('input', function (e) {
		if (e.target.classList.contains('ys-max-videos-input')) {
			const icon = e.target.parentNode.querySelector('.ys-unlimited-icon')
			if (!icon) return
			if (e.target.value && e.target.value !== '0') {
				icon.classList.add('ys-hidden')
			} else {
				icon.classList.remove('ys-hidden')
			}
		}
	})

	document.addEventListener('blur', function (e) {
		if (e.target.classList.contains('ys-max-videos-input')) {
			checkUnlimited(e.target)
		}
	}, true)

	document.addEventListener('click', function (e) {
		const icon = e.target.closest('.ys-unlimited-icon')
		if (!icon) return
		icon.style.display = 'none'
		const input = icon.parentNode.querySelector('.ys-max-videos-input')
		if (input) {
			input.value = ''
			input.focus()
		}
	})
}())

/**
 * Sync progress polling.
 * On page load, finds any .ys-rule--syncing cards and polls the server
 * every 2.5s for progress. Updates the badge text and dismisses the overlay
 * when the sync completes.
 */
;(function () {
	const container = document.getElementById('ys-channels')
	if (!container) return

	const POLL_MS   = 2500
	const activePolls = new Map() // key: "ch_rule" → intervalId

	// Start polling any rules already syncing at page load.
	container.querySelectorAll('.ys-rule--syncing').forEach(function (ruleEl) {
		startPollingRule(ruleEl)
	})

	function startPollingRule(ruleEl) {
		const ruleIndex = ruleEl.dataset.ruleIndex
		const chIndex   = ruleEl.dataset.chIndex
		if (chIndex === undefined || ruleIndex === undefined) return

		const key = chIndex + '_' + ruleIndex
		if (activePolls.has(key)) return

		poll(chIndex, ruleIndex, ruleEl, key)
		const id = setInterval(function () {
			poll(chIndex, ruleIndex, ruleEl, key)
		}, POLL_MS)
		activePolls.set(key, id)
	}

	function stopPolling(key) {
		if (activePolls.has(key)) {
			clearInterval(activePolls.get(key))
			activePolls.delete(key)
		}
	}

	function poll(chIndex, ruleIndex, ruleEl, key) {
		const body = new URLSearchParams({
			action:     'yousync_sync_progress',
			nonce:      youSync.syncProgressNonce,
			ch_index:   chIndex,
			rule_index: ruleIndex,
		})

		fetch(youSync.ajaxUrl, {
			method:  'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:    body.toString(),
		})
		.then(function (r) { return r.json() })
		.then(function (res) {
			if (!res.success) { stopPolling(key); return }

			const data = res.data
			if (data.status === 'syncing') {
				updateBadge(ruleEl, data.current, data.total)
			} else {
				stopPolling(key)
				onSyncDone(ruleEl, data)
			}
		})
		.catch(function () { stopPolling(key) })
	}

	function updateBadge(ruleEl, current, total) {
		const progress = ruleEl.querySelector('.ys-syncing-progress')
		if (!progress) return
		progress.textContent = total > 0 ? (' ' + current + ' of ' + total) : ''
	}

	function onSyncDone(ruleEl, data) {
		window._ysSyncUpdate = true

		// Show final progress count in the overlay before removing it.
		if (data.total > 0) {
			updateBadge(ruleEl, data.synced, data.total)
		}

		function finish() {
			// Remove overlay and syncing state.
			ruleEl.classList.remove('ys-rule--syncing')
			const overlay = ruleEl.querySelector('.ys-syncing-overlay')
			if (overlay) overlay.remove()

			// If rule was auto-disabled (once schedule), reflect that in the UI.
			if (data.enabled === false) {
				const toggle = ruleEl.querySelector('.ys-rule-toggle')
				if (toggle) toggle.checked = false
				const headingWrap = ruleEl.querySelector('.ys-rule-heading-wrap')
				if (headingWrap && !headingWrap.querySelector('.ys-rule-disabled-notice')) {
					const notice = document.createElement('p')
					notice.className = 'ys-rule-disabled-notice'
					notice.textContent = "This rule is disabled and won't run until re-enabled."
					headingWrap.appendChild(notice)
				}
			}

			setTimeout(function () { window._ysSyncUpdate = false }, 0)

			// Update "Last Sync" in the schedule tooltip if present.
			if (data.last_synced_label) {
				const tooltip  = ruleEl.querySelector('.ys-schedule-tooltip')
				if (tooltip) {
					let lastSyncItem = Array.from(tooltip.querySelectorAll('.ys-schedule-item')).find(function (el) {
						return el.textContent.indexOf('Last Sync') !== -1
					})
					if (lastSyncItem) {
						const spans = lastSyncItem.querySelectorAll('span')
						if (spans[1]) spans[1].textContent = data.last_synced_label
					} else {
						// Insert "Last Sync" item before the first item.
						const item = document.createElement('span')
						item.className = 'ys-schedule-item'
						item.innerHTML = '<span>Last Sync:</span><span>' + data.last_synced_label + '</span>'
						tooltip.insertBefore(item, tooltip.firstChild)
					}
					// Make sure the schedule button is visible.
					const scheduleBtn = ruleEl.querySelector('.ys-schedule')
					if (scheduleBtn) scheduleBtn.hidden = false
				}
			}
		}

		// Brief display of final count before dismissing the overlay.
		if (data.total > 0) {
			setTimeout(finish, 1200)
		} else {
			finish()
		}
	}
}())

;(function () {
	document.addEventListener('click', function (e) {
		const btn = e.target.closest('.ys-history-entry-toggle')
		if (!btn) return
		const entry  = btn.closest('.ys-history-entry')
		const errors = entry && entry.querySelector('.ys-history-entry-errors')
		if (!errors) return
		const expanded = btn.getAttribute('aria-expanded') === 'true'
		errors.classList.toggle('ys-hidden', expanded)
		btn.setAttribute('aria-expanded', String(!expanded))
		const icon = btn.querySelector('.material-icons-outlined')
		if (icon) icon.textContent = expanded ? 'expand_more' : 'expand_less'
	})
})()



// ============================================================
// Help tooltips — click to open, click outside to close
// ============================================================
;(function () {
	// <label> elements containing a help button forward clicks on the label text
	// to the button. Block that by calling preventDefault() in the capture phase
	// whenever the click target is not the button itself.
	document.addEventListener('click', function (e) {
		const label = e.target.closest('label')
		if (label && label.querySelector('.ys-help-btn') && !e.target.closest('.ys-help-btn')) {
			e.preventDefault()
		}
	}, true)

	document.addEventListener('click', function (e) {
		const btn  = e.target.closest('.ys-help-btn')
		const wrap = btn ? btn.closest('.ys-help-wrap') : null
		const wasOpen = wrap ? wrap.classList.contains('ys-help-wrap--open') : false

		document.querySelectorAll('.ys-help-wrap--open').forEach(function (w) {
			w.classList.remove('ys-help-wrap--open')
		})

		if (wrap && !wasOpen) {
			wrap.classList.add('ys-help-wrap--open')
		}
	})
})()
