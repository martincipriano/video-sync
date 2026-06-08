const channelsContainer = document.getElementById('ys-channels')
const singleSyncRules  = document.getElementById('ys-rules')
const delegationRoot   = channelsContainer || singleSyncRules

if (delegationRoot) {

/**
 * Update the rule label span from the selected action + schedule
 */
const scheduleSuffixes = {
	once:    'immediately after saving',
	hourly:  'every hour',
	daily:   'every day',
	weekly:  'every week',
	monthly: 'every month',
}

function updateRuleLabel(rule) {
	const label = rule.querySelector('.ys-rule-heading')
	if (!label) return

	const actionSelect   = rule.querySelector('.ys-action')
	const scheduleSelect = rule.querySelector('.ys-sync-schedule')
	const customInput    = rule.querySelector('.ys-custom-sync-schedule')

	const selectedAction = actionSelect?.selectedOptions[0]
	const hasAction = selectedAction && selectedAction.value

	if (!hasAction) {
		label.textContent = 'Please select an action.'
		rule.classList.add('ys-rule--no-action')
		return
	}

	rule.classList.remove('ys-rule--no-action')

	const actionText = selectedAction.textContent.trim().replace(/\s*\(Pro Only\)\s*$/, '')
	const scheduleValue = scheduleSelect?.value || 'once'
	const suffix = scheduleValue === 'custom'
		? `every ${customInput?.value || 1} hours`
		: (scheduleSuffixes[scheduleValue] || scheduleValue)

	label.textContent = actionText + ' ' + suffix + '.'
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

		// Update conditions container data-rule-index
		const conditionsContainer = rule.querySelector('.ys-conditions')
		if (conditionsContainer) {
			conditionsContainer.setAttribute('data-rule-index', newIndex)
		}

	})
}

/**
 * Reindex all conditions within a specific rule to ensure sequential numbering (0, 1, 2...)
 */
function reindexConditions(rule) {
	const conditions = rule.querySelectorAll('.ys-condition')

	conditions.forEach((condition, newConditionIndex) => {
		// Update all name attributes
		condition.querySelectorAll('[name*="[conditions]"]').forEach(element => {
			const name = element.getAttribute('name')
			const match = name.match(/\[conditions\]\[(\d+)\]/)
			if (match) {
				const oldConditionIndex = match[1]
				element.setAttribute('name', name.replace(
					`[conditions][${oldConditionIndex}]`,
					`[conditions][${newConditionIndex}]`
				))
			}
		})

		// Update all id attributes
		condition.querySelectorAll('[id]').forEach(element => {
			const id = element.getAttribute('id')
			const match = id.match(/conditions-(\d+)-/)
			if (match) {
				const oldConditionIndex = match[1]
				element.setAttribute('id', id.replace(
					`conditions-${oldConditionIndex}-`,
					`conditions-${newConditionIndex}-`
				))
			}
		})

		// Update all for attributes
		condition.querySelectorAll('[for]').forEach(element => {
			const forAttr = element.getAttribute('for')
			const match = forAttr.match(/conditions-(\d+)-/)
			if (match) {
				const oldConditionIndex = match[1]
				element.setAttribute('for', forAttr.replace(
					`conditions-${oldConditionIndex}-`,
					`conditions-${newConditionIndex}-`
				))
			}
		})
	})
}

/**
 * Reindex [destination_taxonomy_terms][N] segments after row removal.
 */
function reindexTaxonomyTerms(container) {
	container.querySelectorAll('.ys-taxonomy-term-row').forEach((row, i) => {
		row.querySelectorAll('[name*="[destination_taxonomy_terms]"]').forEach(el => {
			el.setAttribute('name', el.getAttribute('name')
				.replace(/\[destination_taxonomy_terms\]\[\d+\]/, `[destination_taxonomy_terms][${i}]`))
		})
	})
}


/**
 * Script to show/hide custom sync schedule number input and update rule label
 */
delegationRoot.addEventListener('change', function(e) {
	if (e.target.classList.contains('ys-rule-toggle')) {
		const notice = e.target.closest('.ys-rule')?.querySelector('.ys-rule-disabled-notice')
		if (notice) notice.classList.toggle('ys-hidden', e.target.checked)
	}
})

delegationRoot.addEventListener('change', function(e) {
	if (e.target.classList.contains('ys-sync-schedule')) {
		const schedule = e.target.value
		const rule = e.target.closest('.ys-rule')
		const wrapper = rule.querySelector('.ys-custom-schedule-wrapper')
		if (wrapper) {
			wrapper.classList.toggle('ys-hidden', schedule !== 'custom')
		}
		updateRuleLabel(rule)
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
	const isUpdate  = action.includes('_update_')
	const postTypeWrapper = rule.querySelector('.ys-post-type-wrapper')
	const postTypeSelect  = rule.querySelector('[name*="[destination_post_type]"]')
	if (postTypeWrapper) {
		postTypeWrapper.classList.toggle('ys-hidden', !isSyncNew)
	}
	if (postTypeSelect) {
		postTypeSelect.disabled = !isSyncNew
	}
	const taxWrapper = rule.querySelector('.ys-taxonomy-terms-wrapper')
	if (taxWrapper) {
		if (!isSyncNew) {
			taxWrapper.classList.add('ys-hidden')
		} else {
			const postType = postTypeSelect?.value ?? ''
			taxWrapper.classList.toggle('ys-hidden', !hasTaxonomiesForPostType(postType))
		}
	}
	rule.querySelector('.ys-conditions-wrapper')?.classList.toggle('ys-hidden', !action || action.startsWith('channel_'))
	rule.querySelector('.ys-items-per-run-wrapper')?.classList.toggle('ys-hidden', !!action && action.startsWith('channel_'))
	rule.querySelector('.ys-field-mapping-wrapper')?.classList.toggle('ys-hidden', isUpdate)
}

// Initialize post-type visibility on load for all existing rules
delegationRoot.querySelectorAll('.ys-rule').forEach(applyRuleActionVisibility)

/**
 * Insert one blank metadata field row into a container.
 *
 * @param {Element} container  The .ys-specific-metadata-rows element.
 * @param {string}  resource   'video', 'playlist', or 'channel'.
 * @param {string}  [nameAttr] Optional name attribute for form submission. Omit for wizard rows.
 */
function addMetadataFieldRow(container, resource, nameAttr) {
	if (!container) return
	const options = youSync.syncRule[resource]?.metadataOptions ?? ''
	const html = (youSync.tmplMetadataRow ?? '')
		.replace('{{OPTIONS}}', `<option value="">\u2014 Select detail \u2014</option>${options}`)
		.replace('{{NAME}}', nameAttr ? `name="${nameAttr}"` : '')
	container.insertAdjacentHTML('beforeend', html)
}

/**
 * Update specific metadata options based on the rule action selected
 */
delegationRoot.addEventListener('change', function(e) {

	if (e.target.classList.contains('ys-action')) {
		const syncRule = e.target.closest('.ys-rule')
		const formGroup = syncRule.querySelector('.ys-specific-metadata-wrapper') || syncRule.querySelector('.ys-action-metadata-wrapper')
		const value    = e.target.value
		const resource = e.target.selectedOptions[0]?.dataset.resource ?? ''
		const isSyncNew = value === 'videos_sync_new' || value === 'playlists_sync_new' || value === 'channel_sync_new'

		if (formGroup) {
			if (value.includes('update_specific')) {
				formGroup.classList.remove('ys-hidden')
				const rowsContainer = formGroup.querySelector('.ys-specific-metadata-rows')
				if (rowsContainer) {
					// Clear rows if resource changed (video → playlist etc.)
					if (rowsContainer.dataset.resource && rowsContainer.dataset.resource !== resource) {
						rowsContainer.innerHTML = ''
					}
					rowsContainer.dataset.resource = resource
				}
			} else {
				// Clear stale specific metadata when switching to a non-update_specific action
				const rowsContainer = formGroup.querySelector('.ys-specific-metadata-rows')
				if (rowsContainer) rowsContainer.innerHTML = ''
				formGroup.classList.add('ys-hidden')
			}
		}

		// Clear destination post type and taxonomy terms when switching away from sync_new
		if (!isSyncNew) {
			const postTypeSelect = syncRule.querySelector('[name*="[destination_post_type]"]')
			if (postTypeSelect) postTypeSelect.value = ''
			const termsContainer = syncRule.querySelector('.ys-taxonomy-terms')
			if (termsContainer) termsContainer.innerHTML = ''
		}

		// Show post type (and taxonomy terms) only for sync_new actions
		applyRuleActionVisibility(syncRule)
		updateRuleDynamicLabels(syncRule)
		// Update field mapping source options and button label to match the new resource.
		updateRuleFMSources(syncRule, resource)
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
		const action          = e.target.value
		const isSyncNew        = action === 'videos_sync_new' || action === 'playlists_sync_new' || action === 'channel_sync_new'
		const isUpdate         = action.includes('_update_')
		const isUpdateSpecific = action.includes('update_specific')
		const isChannelAction = action.startsWith('channel_')
		wizard.querySelector('.ys-wizard-post-type-wrapper')?.classList.toggle('ys-hidden', !isSyncNew)
		wizard.querySelector('.ys-items-per-run-wrapper')?.classList.toggle('ys-hidden', isChannelAction)
		wizard.dataset.isUpdate        = isUpdate ? '1' : ''
		wizard.dataset.isChannelAction = isChannelAction ? '1' : ''
		updateWizardProgressIndicators(wizard)

		// Update field mapping step 4 when the resource type changes.
		const prevResource = wizard.dataset.resource ?? ''
		wizard.dataset.resource = resource
		if (resource !== prevResource) {
			// Clear FM rows — sources differ between video and playlist.
			const fmRows = wizard.querySelector('.ys-wizard-field-mapping-rows')
			if (fmRows) fmRows.innerHTML = ''
			// Update mapping label and button label.
			const fmLabels = getFMLabels(resource)
			const mappingLabelEl = wizard.querySelector('[data-step="4"] .ys-fm-mapping-label')
			if (mappingLabelEl) mappingLabelEl.textContent = fmLabels.mapping
			const addFmBtn = wizard.querySelector('[data-step="4"] .ys-add-field-mapping-row')
			if (addFmBtn) {
				for (const node of [...addFmBtn.childNodes]) {
					if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
						node.textContent = fmLabels.button
						break
					}
				}
			}
		}

		// Show/hide specific metadata repeater in step 2.
		const smWrapper = wizard.querySelector('.ys-wizard-specific-metadata-wrapper')
		if (smWrapper) {
			smWrapper.classList.toggle('ys-hidden', !isUpdateSpecific)
			const smRows = smWrapper.querySelector('.ys-wizard-specific-metadata-rows')
			if (smRows) {
				if (!isUpdateSpecific) {
					smRows.innerHTML = ''
				} else {
					smRows.dataset.resource = resource
				}
			}
		}
	}
})

/**
 * Add metadata field row
 */
delegationRoot.addEventListener('click', function(e) {
	if (!e.target.closest('.ys-add-metadata-field')) return
	e.preventDefault()

	const wizard = e.target.closest('.ys-wizard')
	if (wizard) {
		const container = wizard.querySelector('.ys-wizard-specific-metadata-rows')
		const resource  = container?.dataset.resource ?? 'video'
		addMetadataFieldRow(container, resource, null)
		return
	}

	const rule      = e.target.closest('.ys-rule')
	const container = rule?.querySelector('.ys-specific-metadata-rows')
	const resource  = rule?.querySelector('.ys-action')?.selectedOptions[0]?.dataset.resource ?? ''
	const nameBase  = (rule?.querySelector('.ys-action')?.getAttribute('name') ?? '').replace(/\[action\]$/, '')
	addMetadataFieldRow(container, resource, nameBase + '[specific_metadata][]')
})

/**
 * Remove metadata field row
 */
delegationRoot.addEventListener('click', function(e) {
	if (!e.target.closest('.ys-remove-metadata-field')) return
	e.preventDefault()
	const row = e.target.closest('.ys-specific-metadata-row')
	const container = row?.closest('.ys-specific-metadata-rows')
	if (container && container.querySelectorAll('.ys-specific-metadata-row').length === 1) {
		container.innerHTML = ''
	} else {
		row?.remove()
	}
})

/**
 * Update conditions based on the selected action
 */
delegationRoot.addEventListener('change', function(e) {

	if (e.target.classList.contains('ys-action')) {
		const syncRule = e.target.closest('.ys-rule')
		updateRuleLabel(syncRule)
		const conditions				= syncRule.querySelector('.ys-conditions')
		const selectedAction		= e.target.selectedOptions[0]
		const selectedResource	= selectedAction.dataset.resource

		/**
		 * Only reset condition fields when the resource type changes
		 * E.g., switching from "Sync new videos" to "Sync new playlists" (different resources) resets conditions
		 * But switching from "Sync new videos" to "Update all videos" (same resource) preserves conditions
		 * This prevents users from losing their work when toggling between actions of the same resource
		 */
		if (conditions && selectedResource != conditions.dataset.resource) {
			const conditionGroups	= syncRule.querySelectorAll('.ys-condition')
			const conditionField = syncRule.querySelector('.ys-condition-field')
			const operatorField = syncRule.querySelector('.ys-condition-operator')
			const valueField = syncRule.querySelector('.ys-condition-value')

			/**
			 * Store the resource in a data attribute
			 * This will be used later for comparison
			 */
			conditions.dataset.resource = selectedResource

			/**
			 * Keep only the first condition when resource changes
			 * Other conditions are removed because their field/operator/value selections are invalid for the new resource type
			 */
			conditionGroups.forEach((conditionGroup, index) => {
				if (index != 0) {
					conditionGroup.remove()
				}
			})

			/**
			 * Update the condition field if it exists
			 */
			if (conditionField) {
				conditionField.innerHTML = youSync.syncRule[selectedResource].fieldOptions
			}

			/**
			 * Update the operator if it exists
			 */
			if (operatorField) {
				operatorField.innerHTML = ''
				operatorField.disabled = true
			}

			if (valueField) {
				valueField.insertAdjacentHTML('beforebegin', youSync.values.text)
				valueField.remove()
			}
		}

	}
})

/**
 * Update the conditions's operator and value inputs based on the selected field
 */
delegationRoot.addEventListener('change', function(e) {
	if (e.target.classList.contains('ys-condition-field')) {
		const field = e.target.value
		const condition = e.target.closest('.ys-condition')
		const operatorSelect = condition.querySelector('.ys-condition-operator')
		const valueInput = condition.querySelector('.ys-condition-value')

		if (!field) {
			operatorSelect.innerHTML = ''
			operatorSelect.value = ''
			operatorSelect.disabled = true
			return
		}

		// Extract rule and condition indices — guard against wizard context (no .ys-rule ancestor).
		const rule = condition.closest('.ys-rule')
		const ruleIndex = rule ? rule.getAttribute('data-rule-index') : 'wiz'

		// Extract condition index from the existing input's name attribute
		const nameAttr = valueInput?.getAttribute('name') ?? ''
		const conditionMatch = nameAttr.match(/\[conditions\]\[(\d+)\]/)
		const conditionIndex = conditionMatch ? conditionMatch[1] : '0'

		// Get the data-type of the selected field
		const selectedOption = e.target.querySelector(`option[value="${field}"]`)
		const fieldType = selectedOption ? selectedOption.getAttribute('data-type') : ''

		// On channels page, replace {{CHANNEL_INDEX}} with the actual channel index
		const channelGroup = rule
			? rule.closest('.ys-channel')
			: condition.closest('.ys-wizard')?.closest('.ys-channel')
		const channelIndex = channelGroup ? channelGroup.dataset.channelIndex : null

		function prepareTemplate(tpl) {
			let result = tpl.replace('disabled', '')
				.replaceAll('{{RULE_INDEX}}', ruleIndex)
				.replaceAll('{{CONDITION_INDEX}}', conditionIndex)
			if (channelIndex !== null) {
				result = result.replaceAll('{{CHANNEL_INDEX}}', channelIndex)
			}
			return result
		}

		// Populate operator optgroups based on field type
		if (fieldType === 'text' && youSync.operators.text) {
			operatorSelect.innerHTML = youSync.operators.text
			valueInput.insertAdjacentHTML('beforebegin', prepareTemplate(youSync.values.text))
			valueInput.remove()
		} else if (fieldType === 'number' && youSync.operators.number) {
			operatorSelect.innerHTML = youSync.operators.number
			valueInput.insertAdjacentHTML('beforebegin', prepareTemplate(youSync.values.number))
			valueInput.remove()
		} else if (fieldType === 'date' && youSync.operators.date) {
			operatorSelect.innerHTML = youSync.operators.date
			valueInput.insertAdjacentHTML('beforebegin', prepareTemplate(youSync.values.date))
			valueInput.remove()
		} else {
			operatorSelect.innerHTML = ''
			operatorSelect.value = ''
		}

		operatorSelect.disabled = false
	}
})

/**
 * Insert one blank condition row into a rule.
 */
function addConditionRow(rule) {
	const ruleIndex           = rule.getAttribute('data-rule-index')
	const conditionsContainer = rule.querySelector('.ys-conditions')

	// Determine the next condition index
	let maxIndex = -1
	conditionsContainer.querySelectorAll('.ys-condition').forEach(condition => {
		const nameAttr = condition.querySelector('[name*="[conditions]"]')
		if (nameAttr) {
			const match = nameAttr.getAttribute('name').match(/\[conditions\]\[(\d+)\]/)
			if (match) {
				const index = parseInt(match[1])
				if (index > maxIndex) maxIndex = index
			}
		}
	})

	let newConditionHTML = youSync.syncRule.condition
		.replaceAll('{{RULE_INDEX}}',       ruleIndex)
		.replaceAll('{{CONDITION_INDEX}}',  maxIndex + 1)

	const channelGroup = rule.closest('.ys-channel')
	if (channelGroup) {
		newConditionHTML = newConditionHTML.replaceAll('{{CHANNEL_INDEX}}', channelGroup.dataset.channelIndex)
	}

	conditionsContainer.insertAdjacentHTML('beforeend', newConditionHTML)

	// Populate field options based on the currently selected action
	const action = rule.querySelector('.ys-action')?.value ?? ''
	let fieldOptions = ''
	if (['channel_update_specific', 'channel_update_all'].includes(action)) {
		fieldOptions = youSync.syncRule.channel.fieldOptions
	} else if (['playlists_sync_new', 'playlists_update_all', 'playlists_update_specific_all'].includes(action)) {
		fieldOptions = youSync.syncRule.playlist.fieldOptions
	} else if (['videos_sync_new', 'videos_update_all', 'videos_update_specific_all'].includes(action)) {
		fieldOptions = youSync.syncRule.video.fieldOptions
	}

	const fieldSelect = conditionsContainer.lastElementChild.querySelector('.ys-condition-field')
	if (fieldSelect && fieldOptions) {
		fieldSelect.innerHTML = fieldOptions
	}
}

/**
 * Handle adding new conditions
 */
delegationRoot.addEventListener('click', function(e) {
	if (e.target.closest('.ys-add-condition')) {
		e.preventDefault()
		if (e.target.closest('.ys-conditions-locked')) return
		const wizard = e.target.closest('.ys-wizard')
		if (wizard) {
			addWizardConditionRow(wizard)
		} else {
			addConditionRow(e.target.closest('.ys-rule'))
		}
	}
})

/**
 * Handle removal of conditions
 */
delegationRoot.addEventListener('click', function(e) {
	if (e.target.closest('.ys-remove-condition')) {
		e.preventDefault()
		if (e.target.closest('.ys-conditions-locked')) return

		const condition = e.target.closest('.ys-condition')
		const conditionsContainer = condition.closest('.ys-conditions')
		const rule = condition.closest('.ys-rule')

		const totalConditions = conditionsContainer.querySelectorAll('.ys-condition').length

		// If this is the last condition, clear the entire container
		if (totalConditions === 1) {
			conditionsContainer.innerHTML = ''
		} else {
			condition.remove()
			// Reindex remaining conditions to ensure sequential numbering (not needed in wizard).
			if (rule) reindexConditions(rule)
		}
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

	const action      = rule.querySelector('.ys-action')?.value || ''
	const schedule    = rule.querySelector('.ys-sync-schedule')?.value || ''
	const customHours = parseInt(rule.querySelector('.ys-custom-sync-schedule')?.value) || 24
	const rulesContainer = rule.closest('.ys-rules')
	const videoCount  = parseInt(rulesContainer?.dataset.videoCount) || 0

	const videoActions     = ['videos_sync_new', 'videos_update_all', 'videos_update_specific_all']
	const cheapActions     = ['channel_update_all', 'channel_update_specific']
	const playlistsActions = ['playlists_sync_new', 'playlists_update_all', 'playlists_update_specific_all']

	let perRun = null

	if (videoActions.includes(action)) {
		const batches = Math.ceil(Math.max(1, videoCount) / 50)
		perRun = 1 + batches * 2
	} else if (cheapActions.includes(action)) {
		perRun = 1
	} else if (playlistsActions.includes(action)) {
		el.textContent = 'Approx. 1 unit per 50 playlists'
		el.classList.remove('ys-hidden')
		return
	} else {
		el.textContent = ''
		el.classList.add('ys-hidden')
		return
	}

	let text = `Approx. ${perRun} unit${perRun !== 1 ? 's' : ''} per run`

	const multipliers = { hourly: 24, daily: 1, weekly: 1/7, monthly: 1/30 }
	const runsPerDay  = schedule === 'custom' ? 24 / customHours : (multipliers[schedule] ?? null)

	if (runsPerDay && runsPerDay >= 1) {
		text += ` · Approx. ${Math.round(perRun * runsPerDay)} units/day`
	}

	el.textContent = text
	el.classList.remove('ys-hidden')
}

delegationRoot.querySelectorAll('.ys-rule').forEach(updateQuotaEstimate)


delegationRoot.addEventListener('change', function(e) {
	if (
		e.target.classList.contains('ys-action') ||
		e.target.classList.contains('ys-sync-schedule') ||
		e.target.classList.contains('ys-custom-sync-schedule')
	) {
		updateQuotaEstimate(e.target.closest('.ys-rule'))
	}
})

delegationRoot.addEventListener('input', function(e) {
	if (e.target.classList.contains('ys-custom-sync-schedule')) {
		updateRuleLabel(e.target.closest('.ys-rule'))
	}
})

/**
 * Resolve taxonomy options HTML for the currently selected post type in a rule.
 */
function getTaxonomyOptionsForRule(rule) {
	const postType = rule.querySelector('[name*="[destination_post_type]"]')?.value ?? ''
	return (postType && youSync.taxonomyOptionsByPostType?.[postType])
		? youSync.taxonomyOptionsByPostType[postType]
		: youSync.taxonomyOptions
}

/**
 * Return true when the given post type has at least one public taxonomy attached.
 * Returns true when no post type is selected (show all).
 */
function hasTaxonomiesForPostType(postType) {
	if (!postType) return false
	const opts = youSync.taxonomyOptionsByPostType?.[postType] ?? ''
	return /<option value="[^"]+">/.test(opts)
}

/**
 * Insert one blank taxonomy+term row into a rule.
 */
function addTaxonomyTermRow(rule) {
	const container = rule.querySelector('.ys-taxonomy-terms')
	const ttIndex   = container.querySelectorAll('.ys-taxonomy-term-row').length
	const ruleIndex  = rule.dataset.ruleIndex
	const refName    = rule.querySelector('[name*="[destination_post_type]"]')?.getAttribute('name') ?? ''
	const namePrefix = refName
		.replace(/\[destination_post_type\]$/, '')
		.replace(new RegExp(`\\[${ruleIndex}\\]$`), '')
	const html = youSync.taxonomyTermRow
		.replaceAll('{{RULE_INDEX}}', ruleIndex)
		.replaceAll('{{TT_INDEX}}',   ttIndex)
		.replaceAll('{{NAME_PREFIX}}', namePrefix)
		.replaceAll('{{TAXONOMY_OPTIONS}}', getTaxonomyOptionsForRule(rule))
	container.insertAdjacentHTML('beforeend', html)
}

/**
 * Add taxonomy term row
 */
delegationRoot.addEventListener('click', function(e) {
	if (!e.target.closest('.ys-add-taxonomy-term')) return
	e.preventDefault()
	addTaxonomyTermRow(e.target.closest('.ys-rule'))
})

/**
 * Post Type change — show/hide taxonomy terms wrapper; seed one initial row when revealed.
 */
delegationRoot.addEventListener('change', function(e) {
	if (!e.target.getAttribute('name')?.includes('[destination_post_type]')) return
	const rule     = e.target.closest('.ys-rule')
	const postType = e.target.value
	const hasTax   = hasTaxonomiesForPostType(postType)
	const wrapper  = rule.querySelector('.ys-taxonomy-terms-wrapper')

	if (wrapper) {
		wrapper.classList.toggle('ys-hidden', !hasTax)
	}

	// Clear any existing rows (post type changed, options are no longer valid)
	const termsContainer = rule.querySelector('.ys-taxonomy-terms')
	if (termsContainer) termsContainer.innerHTML = ''

})

/**
 * Remove taxonomy term row
 */
delegationRoot.addEventListener('click', function(e) {
	if (!e.target.closest('.ys-remove-taxonomy-term')) return
	e.preventDefault()
	const row = e.target.closest('.ys-taxonomy-term-row')
	const container = row.closest('.ys-taxonomy-terms')
	const rule = row.closest('.ys-rule')
	if (container.querySelectorAll('.ys-taxonomy-term-row').length === 1) {
		container.innerHTML = ''
	} else {
		row.remove()
		reindexTaxonomyTerms(container)
	}
})

/**
 * Taxonomy select change — fetch terms via AJAX and populate term select
 */
delegationRoot.addEventListener('change', function(e) {
	if (!e.target.classList.contains('ys-taxonomy-select')) return
	const row = e.target.closest('.ys-taxonomy-term-row')
	const rule = row.closest('.ys-rule')
	const termSelect = row.querySelector('.ys-term-select')
	const taxonomy = e.target.value
	const wrapper = row.querySelector('.ys-term-select-wrapper')

	if (!taxonomy) {
		termSelect.innerHTML = '<option value="">&mdash; Select term &mdash;</option>'
		termSelect.disabled = true
		return
	}

	fetch(`${youSync.ajaxUrl}?action=yousync_get_terms&taxonomy=${encodeURIComponent(taxonomy)}&nonce=${youSync.ajaxNonce}`)
		.then(r => r.json())
		.then(({ success, data }) => {
			if (!success) { termSelect.innerHTML = '<option value="">&mdash; Select term &mdash;</option>'; termSelect.disabled = true; return }
			termSelect.innerHTML = '<option value="">&mdash; Select term &mdash;</option>' + data.map(t => `<option value="${t.id}">${t.name}</option>`).join('')
			termSelect.disabled = false
		})
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
	const isUpdate        = wizard.dataset.isUpdate === '1'
	const isChannelAction = wizard.dataset.isChannelAction === '1'

	let visibleSteps
	if (isUpdate && isChannelAction) {
		visibleSteps = [1, 2]
	} else if (isUpdate) {
		visibleSteps = [1, 2, 5]
	} else if (isChannelAction) {
		visibleSteps = [1, 2, 3, 4]
	} else {
		visibleSteps = [1, 2, 3, 4, 5]
	}

	let displayNum = 1
	wizard.querySelectorAll('.ys-wizard-step-indicator').forEach(indicator => {
		const step    = +indicator.dataset.step
		const visible = visibleSteps.includes(step)
		indicator.classList.toggle('ys-hidden', !visible)
		const prevLine = indicator.previousElementSibling
		if (prevLine?.classList.contains('ys-wizard-progress-line')) {
			prevLine.classList.toggle('ys-hidden', !visible)
		}
		if (visible) {
			indicator.textContent = displayNum++
		}
	})
}

/**
 * Reset wizard to step 1 and clear all skipped-step flags.
 */
function wizardReset(wizard) {
	wizard.removeAttribute('data-step3-skipped')
	wizard.removeAttribute('data-step4-skipped')
	wizard.removeAttribute('data-step5-skipped')
	wizard.dataset.isUpdate        = ''
	wizard.dataset.isChannelAction = ''
	wizard.dataset.resource        = 'video'
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

	// Restore default FM rows from the hidden template (channel settings defaults).
	const fmRows = wizard.querySelector('.ys-wizard-field-mapping-rows')
	const fmTemplate = wizard.querySelector('.ys-wizard-default-fm-template')
	if (fmRows) fmRows.innerHTML = fmTemplate ? fmTemplate.innerHTML : ''

	// Restore default taxonomy terms from the hidden template.
	const taxTemplate   = wizard.querySelector('.ys-wizard-default-taxonomy-template')
	const taxContainer  = wizard.querySelector('.ys-wizard-taxonomy-terms')
	const taxWrapper    = wizard.querySelector('.ys-wizard-taxonomy-terms-wrapper')
	if (taxContainer) taxContainer.innerHTML = taxTemplate ? taxTemplate.innerHTML : ''
	if (taxWrapper) taxWrapper.classList.toggle('ys-hidden', !defaultPostType || !hasTaxonomiesForPostType(defaultPostType))
	const fmLabels = getFMLabels('video')
	const mappingLabelEl = wizard.querySelector('[data-step="4"] .ys-fm-mapping-label')
	if (mappingLabelEl) mappingLabelEl.textContent = fmLabels.mapping
	const addFmBtn = wizard.querySelector('[data-step="4"] .ys-add-field-mapping-row')
	if (addFmBtn) {
		for (const node of [...addFmBtn.childNodes]) {
			if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
				node.textContent = fmLabels.button
				break
			}
		}
	}
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
 * Advance to the next step, auto-skipping locked steps.
 */
function wizardAdvance(wizard, fromStep) {
	if (!wizardValidateStep(wizard, fromStep)) return

	let next = fromStep + 1

	// After Step 1: show/hide post type wrapper in Step 3 based on action.
	if (fromStep === 1) {
		const action = wizard.querySelector('.ys-wizard-action-select')?.value ?? ''
		const isSyncNew = action === 'videos_sync_new' || action === 'playlists_sync_new' || action === 'channel_sync_new'
		wizard.querySelector('.ys-wizard-post-type-wrapper')?.classList.toggle('ys-hidden', !isSyncNew)
	}

	// Skip destination (3) and field mapping (4) for update actions — irrelevant.
	if (wizard.dataset.isUpdate === '1' && (next === 3 || next === 4)) {
		next = 5
	}

	// Skip conditions (5) for channel actions — submit directly.
	if (wizard.dataset.isChannelAction === '1' && next === 5) {
		wizardSubmit(wizard)
		return
	}

	if (next > TOTAL_STEPS) return
	wizardShowStep(wizard, next, 'forward')
}

/**
 * Go back one step, accounting for auto-skipped steps.
 */
function wizardBack(wizard, fromStep) {
	let prev = fromStep - 1

	// Skip field mapping (4) and destination (3) going backwards for update actions.
	if (wizard.dataset.isUpdate === '1' && (prev === 4 || prev === 3)) {
		prev = 2
	}

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
	// Clear condition rows — they carry named form inputs that would otherwise be
	// submitted as a ghost sync rule entry when the user clicks "Save Changes".
	const conds = wizard.querySelector('.ys-wizard-conditions')
	if (conds) conds.innerHTML = ''
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

	// Skip
	const skipBtn = e.target.closest('.ys-wizard-skip')
	if (skipBtn) {
		const wizard = skipBtn.closest('.ys-wizard')
		if (!wizard) return
		const step = +skipBtn.closest('.ys-wizard-panel').dataset.step
		if (step === 3) wizard.dataset.step3Skipped = '1'
		if (step === 4) wizard.dataset.step4Skipped = '1'
		if (step === 5) wizard.dataset.step5Skipped = '1'
		if (step < TOTAL_STEPS) {
			wizardAdvance(wizard, step)
		}
		return
	}

	// Finish (final step submit)
	if (e.target.closest('.ys-wizard-finish')) {
		const wizard = e.target.closest('.ys-wizard')
		if (wizard) wizardSubmit(wizard)
		return
	}

	// Add field mapping row (wizard or defaults page)
	if (e.target.closest('.ys-wizard .ys-add-field-mapping-row')) {
		const wizard = e.target.closest('.ys-wizard')
		const rows = wizard?.querySelector('.ys-wizard-field-mapping-rows')
		const resource = wizard?.dataset.resource ?? 'video'
		const sourceOpts = youSync.fieldMappingSources?.[resource] ?? youSync.fieldMappingSources?.video ?? ''
		if (rows) addFieldMappingRow(rows, sourceOpts)
		return
	}

	// Remove field mapping row
	if (e.target.closest('.ys-wizard .ys-remove-field-mapping-row')) {
		const row = e.target.closest('.ys-field-mapping-row')
		row?.remove()
		return
	}

	// Add taxonomy term row in wizard
	if (e.target.closest('.ys-wizard .ys-wizard-add-taxonomy-term')) {
		const panel = e.target.closest('.ys-wizard-panel')
		const container = panel?.querySelector('.ys-wizard-taxonomy-terms')
		if (container) addWizardTaxonomyRow(container)
		return
	}

	// Remove taxonomy term row in wizard (the generic handler in delegationRoot covers it
	// but we ensure .ys-taxonomy-terms emptying works here too).
	if (e.target.closest('.ys-wizard .ys-remove-taxonomy-term')) {
		const row = e.target.closest('.ys-taxonomy-term-row')
		const container = row?.closest('.ys-taxonomy-terms')
		if (!row || !container) return
		if (container.querySelectorAll('.ys-taxonomy-term-row').length === 1) {
			container.innerHTML = ''
		} else {
			row.remove()
		}
		return
	}
})

// Post type change in wizard: show/hide taxonomy terms wrapper + clear existing rows.
delegationRoot.addEventListener('change', function(e) {
	if (!e.target.classList.contains('ys-wizard-post-type')) return
	const panel   = e.target.closest('.ys-wizard-panel')
	const postType = e.target.value
	const hasTax   = hasTaxonomiesForPostType(postType)
	const wrapper  = panel?.querySelector('.ys-wizard-taxonomy-terms-wrapper')
	if (wrapper) wrapper.classList.toggle('ys-hidden', !hasTax)
	const termsContainer = panel?.querySelector('.ys-wizard-taxonomy-terms')
	if (termsContainer) termsContainer.innerHTML = ''
})

// Schedule select change in wizard: show/hide custom hours input.
delegationRoot.addEventListener('change', function(e) {
	if (!e.target.classList.contains('ys-wizard-schedule-select')) return
	const panel   = e.target.closest('.ys-wizard-panel')
	const wrapper = panel?.querySelector('.ys-wizard-custom-schedule-wrapper')
	if (wrapper) wrapper.classList.toggle('ys-hidden', e.target.value !== 'custom')
})

// Field mapping target type toggle (post field vs meta key) — wizard.
delegationRoot.addEventListener('change', function(e) {
	if (!e.target.classList.contains('ys-fm-target-type')) return
	const row     = e.target.closest('.ys-field-mapping-row')
	const wpField = row?.querySelector('.ys-fm-wp-field')
	const metaKey = row?.querySelector('.ys-fm-meta-key')
	if (!row) return
	if (e.target.value === 'meta_key') {
		wpField?.classList.add('ys-hidden')
		metaKey?.classList.remove('ys-hidden')
	} else {
		metaKey?.classList.add('ys-hidden')
		wpField?.classList.remove('ys-hidden')
	}
})

/**
 * Add a blank taxonomy term row to a wizard container.
 */
function addWizardConditionRow(wizard) {
	const condContainer = wizard.querySelector('.ys-wizard-conditions')
	if (!condContainer) return
	const count = condContainer.querySelectorAll('.ys-condition').length
	let html = youSync.syncRule.condition
		.replaceAll('{{RULE_INDEX}}', 'wiz')
		.replaceAll('{{CONDITION_INDEX}}', count)
		.replaceAll('{{CHANNEL_INDEX}}', wizard.dataset.channelIndex ?? '0')
	condContainer.insertAdjacentHTML('beforeend', html)
	const action = wizard.querySelector('.ys-wizard-action-select')?.value ?? ''
	let fieldOptions = ''
	if (['channel_update_specific', 'channel_update_all'].includes(action)) {
		fieldOptions = youSync.syncRule.channel.fieldOptions
	} else if (['playlists_sync_new', 'playlists_update_all', 'playlists_update_specific_all'].includes(action)) {
		fieldOptions = youSync.syncRule.playlist.fieldOptions
	} else if (['videos_sync_new', 'videos_update_all', 'videos_update_specific_all'].includes(action)) {
		fieldOptions = youSync.syncRule.video.fieldOptions
	}
	const fieldSelect = condContainer.lastElementChild.querySelector('.ys-condition-field')
	if (fieldSelect && fieldOptions) fieldSelect.innerHTML = fieldOptions
}

function addWizardTaxonomyRow(container) {
	const row = container.closest('.ys-wizard-panel')
	const postType = row?.querySelector('.ys-wizard-post-type')?.value ?? ''
	const taxOptions = postType && youSync.taxonomyOptionsByPostType?.[postType]
		? youSync.taxonomyOptionsByPostType[postType]
		: youSync.taxonomyOptions
	const html = (youSync.tmplWizardTaxonomyRow ?? '').replace('{{TAX_OPTIONS}}', taxOptions)
	container.insertAdjacentHTML('beforeend', html)
}

/**
 * Add a blank field mapping row to a container.
 *
 * @param {Element} container       The .ys-field-mapping-rows element.
 * @param {string}  [sourceOptions] HTML option strings for the source select. Defaults to video sources.
 */
function addFieldMappingRow(container, sourceOptions) {
	const opts = sourceOptions ?? youSync.fieldMappingSources?.video ?? ''
	const html = (youSync.tmplWizardFMRow ?? '').replace('{{OPTIONS}}', opts)
	container.insertAdjacentHTML('beforeend', html)
}

} // end if (youSync.isChannelsPage) wizard events

/**
 * Reindex [field_mapping][N] name segments after a row is removed from a sync rule.
 */
function reindexRuleFieldMapping(rows) {
	rows.querySelectorAll('.ys-field-mapping-row').forEach((row, i) => {
		row.querySelectorAll('[name*="[field_mapping]["]').forEach(el => {
			el.setAttribute('name', el.getAttribute('name').replace(
				/\[field_mapping\]\[\d+\]/,
				`[field_mapping][${i}]`
			))
		})
	})
}

/**
 * Update FM source selects and button label in a sync rule to match the current resource.
 *
 * @param {Element} rule     The .ys-rule element.
 * @param {string}  resource 'video', 'playlist', or 'channel'.
 */
function updateRuleFMSources(rule, resource) {
	const sourceOpts = youSync.fieldMappingSources?.[resource] ?? youSync.fieldMappingSources?.video ?? ''
	rule.querySelectorAll('.ys-rule-fm-source').forEach(select => {
		const currentVal = select.value
		select.innerHTML = `<option value="">\u2014 Source \u2014</option>${sourceOpts}`
		if (currentVal && select.querySelector(`option[value="${CSS.escape(currentVal)}"]`)) {
			select.value = currentVal
		}
	})
	const fmLabel = getFMLabels(resource)
	const mappingLabelEl = rule.querySelector('.ys-fm-mapping-label')
	if (mappingLabelEl) mappingLabelEl.textContent = fmLabel.mapping
	const addBtn = rule.querySelector('.ys-rule-add-field-mapping-row')
	if (addBtn) {
		for (const node of [...addBtn.childNodes]) {
			if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
				node.textContent = fmLabel.button
				break
			}
		}
	}
}

/**
 * Return the FM mapping label and button label for a given resource.
 */
function getFMLabels(resource) {
	const mapping = resource === 'playlist' ? 'Map playlist details to post metadata'
		: resource === 'channel'  ? 'Map channel details to post metadata'
		: 'Map video details to post metadata'
	const button = resource === 'playlist' ? 'Add playlist detail mapping'
		: resource === 'channel'  ? 'Add channel detail mapping'
		: 'Add video detail mapping'
	return { mapping, button }
}

/**
 * Insert a blank field mapping row into a sync rule (non-wizard).
 */
function addRuleFieldMappingRow(rule) {
	const rows = rule.querySelector('.ys-rule-field-mapping-rows')
	if (!rows) return
	const namePrefix = rows.dataset.namePrefix ?? ''
	const idx = rows.querySelectorAll('.ys-field-mapping-row').length
	const resource = rule.querySelector('.ys-action')?.selectedOptions[0]?.dataset.resource ?? 'video'
	const sourceOpts = youSync.fieldMappingSources?.[resource] ?? youSync.fieldMappingSources?.video ?? ''
	const html = (youSync.tmplRuleFMRow ?? '')
		.replaceAll('{{NAME_PREFIX}}', namePrefix)
		.replaceAll('{{IDX}}', idx)
		.replace('{{OPTIONS}}', sourceOpts)
	rows.insertAdjacentHTML('beforeend', html)
}

// Add field mapping row inside a sync rule (non-wizard).
delegationRoot.addEventListener('click', function(e) {
	if (!e.target.closest('.ys-rule .ys-add-field-mapping-row')) return
	e.preventDefault()
	addRuleFieldMappingRow(e.target.closest('.ys-rule'))
})

// Remove field mapping row inside a sync rule (non-wizard).
delegationRoot.addEventListener('click', function(e) {
	if (!e.target.closest('.ys-rule .ys-remove-field-mapping-row')) return
	e.preventDefault()
	const row = e.target.closest('.ys-field-mapping-row')
	const rows = row?.closest('.ys-rule-field-mapping-rows')
	if (!row || !rows) return
	row.remove()
	reindexRuleFieldMapping(rows)
})

// ============================================================
// Channel Settings — Default Post Type & Taxonomy Terms
// ============================================================

/**
 * Build and insert one blank taxonomy term row into a channel settings container.
 */
function addChannelTaxonomyTermRow(container) {
	const card = container.closest('.ys-channel')
	if (!card) return
	const ch  = card.dataset.channelIndex
	const idx = container.querySelectorAll('.ys-taxonomy-term-row').length
	const pt  = card.querySelector('.ys-channel-default-post-type')?.value ?? ''
	const taxOptions = (pt && youSync.taxonomyOptionsByPostType?.[pt])
		? youSync.taxonomyOptionsByPostType[pt]
		: youSync.taxonomyOptions
	const html = (youSync.tmplChannelTaxRow ?? '')
		.replaceAll('{{CH}}', ch)
		.replaceAll('{{IDX}}', idx)
		.replace('{{TAX_OPTIONS}}', taxOptions)
	container.insertAdjacentHTML('beforeend', html)
}

if (channelsContainer) {

	// Post type change: show/hide taxonomy wrapper and clear existing rows.
	channelsContainer.addEventListener('change', function(e) {
		const sel = e.target.closest('.ys-channel-default-post-type')
		if (!sel) return
		const card    = sel.closest('.ys-channel')
		const wrapper = card?.querySelector('.ys-channel-taxonomy-terms-wrapper')
		const hasTax  = hasTaxonomiesForPostType(sel.value)
		if (wrapper) wrapper.classList.toggle('ys-hidden', !hasTax)
		const termsContainer = card?.querySelector('.ys-channel-taxonomy-terms')
		if (termsContainer) termsContainer.innerHTML = ''
	})

	// Add taxonomy term row in channel Settings tab.
	channelsContainer.addEventListener('click', function(e) {
		const btn = e.target.closest('.ys-channel-add-taxonomy-term')
		if (!btn) return
		e.preventDefault()
		const taxContainer = btn.closest('.ys-channel-taxonomy-terms-wrapper')?.querySelector('.ys-channel-taxonomy-terms')
		if (taxContainer) addChannelTaxonomyTermRow(taxContainer)
	})

	// Remove taxonomy term row in channel Settings tab.
	channelsContainer.addEventListener('click', function(e) {
		const btn = e.target.closest('.ys-channel-taxonomy-terms .ys-remove-taxonomy-term')
		if (!btn) return
		e.preventDefault()
		const row            = btn.closest('.ys-taxonomy-term-row')
		const termsContainer = row?.closest('.ys-channel-taxonomy-terms')
		if (!row || !termsContainer) return
		if (termsContainer.querySelectorAll('.ys-taxonomy-term-row').length === 1) {
			termsContainer.innerHTML = ''
		} else {
			row.remove()
			termsContainer.querySelectorAll('.ys-taxonomy-term-row').forEach((r, i) => {
				r.querySelectorAll('[name*="[default_taxonomy_terms]["]').forEach(el => {
					el.setAttribute('name', el.getAttribute('name').replace(
						/\[default_taxonomy_terms\]\[\d+\]/,
						`[default_taxonomy_terms][${i}]`
					))
				})
			})
		}
	})

}

} // end if (delegationRoot)

// ============================================================
// Defaults page — field mapping repeater + taxonomy terms
// ============================================================
;(function() {
	const wrap = document.querySelector('.ys-defaults-taxonomy-terms')?.closest('form')
		|| document.querySelector('.ys-field-mapping-table')?.closest('form')
	if (!wrap || !youSync.isDefaultsPage) return

	const body = document.body

	// Add field mapping row
	body.addEventListener('click', function(e) {
		if (!e.target.closest('.ys-add-field-mapping-row')) return
		e.preventDefault()
		const rows = e.target.closest('.ys-field-mapping-table')?.querySelector('.ys-field-mapping-rows')
		if (!rows) return
		const idx = rows.querySelectorAll('.ys-field-mapping-row').length
		const sourceOpts = youSync.fieldMappingSources?.video ?? ''
		const html = (youSync.tmplDefaultsFMRow ?? '')
			.replace('{{IDX}}', idx)
			.replace('{{IDX}}', idx)
			.replace('{{OPTIONS}}', sourceOpts)
		rows.insertAdjacentHTML('beforeend', html)
	})

	// Remove field mapping row (reindex names)
	body.addEventListener('click', function(e) {
		if (!e.target.closest('.ys-remove-field-mapping-row')) return
		const row  = e.target.closest('.ys-field-mapping-row')
		const rows = row?.closest('.ys-field-mapping-rows')
		if (!row || !rows) return
		row.remove()
		// Reindex remaining rows.
		rows.querySelectorAll('.ys-field-mapping-row').forEach((r, i) => {
			r.querySelectorAll('[name*="field_mapping["]').forEach(el => {
				el.setAttribute('name', el.getAttribute('name').replace(/field_mapping\[\d+\]/, `field_mapping[${i}]`))
			})
		})
	})

	// Post type change on defaults page: show/hide taxonomy row.
	const postTypeSelect = document.getElementById('ys-defaults-post-type')
	if (postTypeSelect) {
		postTypeSelect.addEventListener('change', function() {
			const pt = this.value
			const taxOpts = pt ? (youSync.taxonomyOptionsByPostType?.[pt] ?? '') : ''
			const hasTax = /<option value="[^"]+">/.test(taxOpts)
			document.getElementById('ys-defaults-taxonomy-row')?.classList.toggle('ys-hidden', !hasTax)
			const container = document.querySelector('.ys-defaults-taxonomy-terms')
			if (container) container.innerHTML = ''
		})
	}

	// "Add taxonomy term" on defaults page.
	body.addEventListener('click', function(e) {
		if (!e.target.closest('.ys-defaults-add-taxonomy-term')) return
		e.preventDefault()
		const container = document.querySelector('.ys-defaults-taxonomy-terms')
		if (!container) return
		const pt = document.getElementById('ys-defaults-post-type')?.value ?? ''
		const taxOptions = (pt && youSync.taxonomyOptionsByPostType?.[pt])
			? youSync.taxonomyOptionsByPostType[pt]
			: youSync.taxonomyOptions
		const idx = container.querySelectorAll('.ys-taxonomy-term-row').length
		const html = `<div class="ys-taxonomy-term-row">
			<select name="destination_taxonomy_terms[${idx}][taxonomy]" class="ys-select ys-taxonomy-select">
				${taxOptions}
			</select>
			<div class="ys-term-select-wrapper">
				<select name="destination_taxonomy_terms[${idx}][term_ids][]" class="ys-select ys-term-select" disabled>
					<option value="">&mdash; Select term &mdash;</option>
				</select>
			</div>
			<button type="button" class="ys-remove-taxonomy-term" aria-label="Remove"></button>
		</div>`
		container.insertAdjacentHTML('beforeend', html)
	})

	// Remove taxonomy term on defaults page.
	body.addEventListener('click', function(e) {
		if (!e.target.closest('.ys-remove-taxonomy-term')) return
		const row = e.target.closest('.ys-taxonomy-term-row')
		const container = row?.closest('.ys-defaults-taxonomy-terms')
		if (!row || !container) return
		if (container.querySelectorAll('.ys-taxonomy-term-row').length === 1) {
			container.innerHTML = ''
		} else {
			row.remove()
			// Reindex name attributes.
			container.querySelectorAll('.ys-taxonomy-term-row').forEach((r, i) => {
				r.querySelectorAll('[name*="destination_taxonomy_terms["]').forEach(el => {
					el.setAttribute('name', el.getAttribute('name').replace(/destination_taxonomy_terms\[\d+\]/, `destination_taxonomy_terms[${i}]`))
				})
			})
		}
	})

	// Taxonomy select change on defaults page: fetch terms via AJAX.
	body.addEventListener('change', function(e) {
		if (!e.target.classList.contains('ys-taxonomy-select')) return
		const row = e.target.closest('.ys-taxonomy-term-row')
		if (!row) return
		const termSelect = row.querySelector('.ys-term-select')
		const taxonomy   = e.target.value
		if (!taxonomy) {
			if (termSelect) { termSelect.innerHTML = '<option value="">&mdash; Select term &mdash;</option>'; termSelect.disabled = true }
			return
		}
		fetch(`${youSync.ajaxUrl}?action=yousync_get_terms&taxonomy=${encodeURIComponent(taxonomy)}&nonce=${youSync.ajaxNonce}`)
			.then(r => r.json())
			.then(({ success, data }) => {
				if (!success || !termSelect) return
				termSelect.innerHTML = '<option value="">&mdash; Select term &mdash;</option>' +
					data.map(t => `<option value="${t.id}">${t.name}</option>`).join('')
				termSelect.disabled = false
			})
	})
})()

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

	// Add field mapping row in channel Settings tab.
	container.addEventListener('click', e => {
		const btn = e.target.closest('.ys-channel-add-field-mapping-row')
		if (!btn) return
		e.preventDefault()
		const rows = btn.closest('.ys-field-mapping-wrapper')?.querySelector('.ys-channel-field-mapping-rows')
		if (!rows) return
		const namePrefix = rows.dataset.namePrefix ?? ''
		const idx        = rows.querySelectorAll('.ys-field-mapping-row').length
		const sourceOpts = youSync.fieldMappingSources?.video ?? ''
		rows.insertAdjacentHTML('beforeend',
			`<div class="ys-field-mapping-row">` +
			`<select class="ys-select" name="${namePrefix}[${idx}][source]">` +
			`<option value="">\u2014 Source \u2014</option>${sourceOpts}</select>` +
			`<input type="text" class="ys-text ys-fm-meta-key" name="${namePrefix}[${idx}][target]" placeholder="e.g. _yousync_duration">` +
			`<button type="button" class="ys-remove-field-mapping-row" aria-label="Remove"></button>` +
			`</div>`
		)
	})

	// Remove field mapping row in channel Settings tab (reindex afterwards).
	container.addEventListener('click', e => {
		const btn = e.target.closest('.ys-channel-field-mapping-rows .ys-remove-field-mapping-row')
		if (!btn) return
		e.preventDefault()
		const row  = btn.closest('.ys-field-mapping-row')
		const rows = row?.closest('.ys-channel-field-mapping-rows')
		if (!row || !rows) return
		row.remove()
		rows.querySelectorAll('.ys-field-mapping-row').forEach((r, i) => {
			r.querySelectorAll('[name*="[field_mapping]["]').forEach(el => {
				el.setAttribute('name', el.getAttribute('name').replace(
					/\[field_mapping\]\[\d+\]/,
					`[field_mapping][${i}]`
				))
			})
		})
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
// Pro feature tooltip — locked condition elements
// ============================================================
;(function () {
	const tip = document.createElement('div')
	tip.id = 'ys-pro-tip'
	document.body.appendChild(tip)

	let timer

	function showProTip(target, message) {
		const rect = target.getBoundingClientRect()
		tip.textContent = message
		tip.style.left = (rect.left + rect.width / 2) + 'px'
		tip.style.top  = rect.top + 'px'
		tip.classList.add('ys-pro-tip--visible')
		clearTimeout(timer)
		timer = setTimeout(() => tip.classList.remove('ys-pro-tip--visible'), 2000)
	}

	// Each entry describes one locked Pro section: which elements trigger the tooltip,
	// which overlay class to skip when hit-testing beneath the click, and the message.
	const lockedSections = [
		{
			triggers:     ['.ys-condition-locked-overlay', '.ys-remove-condition-locked', '.ys-add-condition-locked'],
			overlayClass: 'ys-condition-locked-overlay',
			message:      'Filter conditions (Pro)',
		},
		{
			triggers:     ['.ys-fm-locked-overlay', '.ys-remove-field-mapping-row-locked', '.ys-add-field-mapping-row-locked'],
			overlayClass: 'ys-fm-locked-overlay',
			message:      'Field mapping (Pro)',
		},
		{
			triggers:     ['.ys-tax-locked-overlay', '.ys-remove-taxonomy-term-locked', '.ys-add-taxonomy-term-locked'],
			overlayClass: 'ys-tax-locked-overlay',
			message:      'Taxonomy assignment (Pro)',
		},
	]

	document.addEventListener('click', function (e) {
		for (var i = 0; i < lockedSections.length; i++) {
			var section = lockedSections[i]
			var matched = section.triggers.find(function (sel) { return e.target.closest(sel) })
			if (!matched) continue

			e.preventDefault()
			e.stopImmediatePropagation()

			// When the click lands on an overlay, find the element beneath it so the
			// tooltip is positioned above the specific input that was clicked.
			var stack = document.elementsFromPoint(e.clientX, e.clientY)
			var overlayClass = section.overlayClass
			var target = stack.find(function (el) {
				return !el.classList.contains(overlayClass)
			}) || e.target
			showProTip(target, section.message)
			return
		}
	}, true)
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
