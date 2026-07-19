/**
 * Reindexing tests for Sync Rules and Conditions.
 *
 * These tests verify that `id`, `name`, and `data-rule-index` attributes on
 * rules and conditions are kept in sequential order (0, 1, 2 …) after items
 * are added or removed.
 *
 * Attribute patterns used by the plugin:
 *
 * Sync Rule (on .buoyvs-sync-rule):
 *   data-rule-index="{n}"
 *   name="sync_rules[{n}][enabled|schedule|custom_schedule|action|specific_metadata]"
 *   id="buoyvs-sync-schedule-{n}"
 *   id="buoyvs-custom-schedule-{n}"
 *   id="buoyvs-action-{n}"
 *   id="buoyvs-specific-metadata-{n}"
 *
 * Condition (inside .buoyvs-condition):
 *   id="sync-rules-{ruleN}-conditions-{condN}-field"
 *   id="sync-rules-{ruleN}-conditions-{condN}-operator"
 *   id="sync-rules-{ruleN}-conditions-{condN}-value"
 *   name="sync_rules[{ruleN}][conditions][{condN}][field|operator|value]"
 */

import { test, expect } from '@playwright/test';
import { goToChannels } from './helpers.js';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Add n sync rules by clicking the "Add sync rule" link n times. */
async function addRules(page, n) {
  for (let i = 0; i < n; i++) {
    await page.locator('#buoyvs-add-rule').click();
  }
}

/** Add n conditions to the given rule locator. */
async function addConditions(rule, n) {
  for (let i = 0; i < n; i++) {
    await rule.locator('.buoyvs-add-condition').click();
  }
}

// ---------------------------------------------------------------------------
// Rule index on creation
// ---------------------------------------------------------------------------

test.describe('Rule index on creation', () => {
  test.beforeEach(async ({ page }) => {
    await goToChannels(page);
  });

  test('first rule gets data-rule-index="0"', async ({ page }) => {
    await addRules(page, 1);
    const rule = page.locator('.buoyvs-sync-rule').nth(0);
    await expect(rule).toHaveAttribute('data-rule-index', '0');
  });

  test('second rule gets data-rule-index="1"', async ({ page }) => {
    await addRules(page, 2);
    await expect(page.locator('.buoyvs-sync-rule').nth(1)).toHaveAttribute('data-rule-index', '1');
  });

  test('first rule name attributes use index 0', async ({ page }) => {
    await addRules(page, 1);
    const rule = page.locator('.buoyvs-sync-rule').nth(0);

    await expect(rule.locator('.buoyvs-rule-toggle')).toHaveAttribute('name', 'sync_rules[0][enabled]');
    await expect(rule.locator('.buoyvs-sync-schedule')).toHaveAttribute('name', 'sync_rules[0][schedule]');
    await expect(rule.locator('.buoyvs-action')).toHaveAttribute('name', 'sync_rules[0][action]');
  });

  test('first rule id attributes use index 0', async ({ page }) => {
    await addRules(page, 1);
    const rule = page.locator('.buoyvs-sync-rule').nth(0);

    await expect(rule.locator('.buoyvs-sync-schedule')).toHaveAttribute('id', 'buoyvs-sync-schedule-0');
    await expect(rule.locator('.buoyvs-custom-sync-schedule')).toHaveAttribute('id', 'buoyvs-custom-schedule-0');
    await expect(rule.locator('.buoyvs-action')).toHaveAttribute('id', 'buoyvs-action-0');
  });

  test('second rule name attributes use index 1', async ({ page }) => {
    await addRules(page, 2);
    const rule = page.locator('.buoyvs-sync-rule').nth(1);

    await expect(rule.locator('.buoyvs-rule-toggle')).toHaveAttribute('name', 'sync_rules[1][enabled]');
    await expect(rule.locator('.buoyvs-sync-schedule')).toHaveAttribute('name', 'sync_rules[1][schedule]');
    await expect(rule.locator('.buoyvs-action')).toHaveAttribute('name', 'sync_rules[1][action]');
  });

  test('second rule id attributes use index 1', async ({ page }) => {
    await addRules(page, 2);
    const rule = page.locator('.buoyvs-sync-rule').nth(1);

    await expect(rule.locator('.buoyvs-sync-schedule')).toHaveAttribute('id', 'buoyvs-sync-schedule-1');
    await expect(rule.locator('.buoyvs-custom-sync-schedule')).toHaveAttribute('id', 'buoyvs-custom-schedule-1');
    await expect(rule.locator('.buoyvs-action')).toHaveAttribute('id', 'buoyvs-action-1');
  });
});

// ---------------------------------------------------------------------------
// Rule reindexing after removal
// ---------------------------------------------------------------------------

test.describe('Rule reindexing after removal', () => {
  test.beforeEach(async ({ page }) => {
    await goToChannels(page);
  });

  test('removing the first of two rules renumbers the survivor to index 0', async ({ page }) => {
    await addRules(page, 2);

    // Remove the first rule (index 0)
    await page.locator('.buoyvs-sync-rule').nth(0).locator('.buoyvs-remove-rule').click();
    await page.waitForTimeout(400);

    const survivor = page.locator('.buoyvs-sync-rule').nth(0);
    await expect(survivor).toHaveAttribute('data-rule-index', '0');
    await expect(survivor.locator('.buoyvs-rule-toggle')).toHaveAttribute('name', 'sync_rules[0][enabled]');
    await expect(survivor.locator('.buoyvs-sync-schedule')).toHaveAttribute('id', 'buoyvs-sync-schedule-0');
    await expect(survivor.locator('.buoyvs-action')).toHaveAttribute('id', 'buoyvs-action-0');
  });

  test('removing the middle of three rules produces sequential indices 0 and 1', async ({ page }) => {
    await addRules(page, 3);

    // Remove the middle rule (index 1)
    await page.locator('.buoyvs-sync-rule').nth(1).locator('.buoyvs-remove-rule').click();
    await page.waitForTimeout(400);

    const rules = page.locator('.buoyvs-sync-rule');
    await expect(rules).toHaveCount(2);

    // First survivor: was index 0, stays 0
    await expect(rules.nth(0)).toHaveAttribute('data-rule-index', '0');
    await expect(rules.nth(0).locator('.buoyvs-sync-schedule')).toHaveAttribute('name', 'sync_rules[0][schedule]');
    await expect(rules.nth(0).locator('.buoyvs-action')).toHaveAttribute('id', 'buoyvs-action-0');

    // Second survivor: was index 2, renumbered to 1
    await expect(rules.nth(1)).toHaveAttribute('data-rule-index', '1');
    await expect(rules.nth(1).locator('.buoyvs-sync-schedule')).toHaveAttribute('name', 'sync_rules[1][schedule]');
    await expect(rules.nth(1).locator('.buoyvs-action')).toHaveAttribute('id', 'buoyvs-action-1');
  });

  test('removing the last of three rules leaves sequential indices 0 and 1', async ({ page }) => {
    await addRules(page, 3);

    await page.locator('.buoyvs-sync-rule').nth(2).locator('.buoyvs-remove-rule').click();
    await page.waitForTimeout(400);

    const rules = page.locator('.buoyvs-sync-rule');
    await expect(rules).toHaveCount(2);
    await expect(rules.nth(0)).toHaveAttribute('data-rule-index', '0');
    await expect(rules.nth(1)).toHaveAttribute('data-rule-index', '1');
  });

  test('conditions container data-rule-index is updated when rule is reindexed', async ({ page }) => {
    await addRules(page, 2);

    // Remove rule 0 so rule 1 becomes rule 0
    await page.locator('.buoyvs-sync-rule').nth(0).locator('.buoyvs-remove-rule').click();
    await page.waitForTimeout(400);

    const conditionsContainer = page.locator('.buoyvs-sync-rule').nth(0).locator('.buoyvs-conditions');
    await expect(conditionsContainer).toHaveAttribute('data-rule-index', '0');
  });
});

// ---------------------------------------------------------------------------
// Condition index on creation
// ---------------------------------------------------------------------------

test.describe('Condition index on creation', () => {
  test.beforeEach(async ({ page }) => {
    await goToChannels(page);
    await addRules(page, 1);
    // Select a video action so field options are populated
    await page.locator('.buoyvs-sync-rule').nth(0).locator('.buoyvs-action').selectOption('videos_sync_new');
  });

  test('first condition gets index 0 in id attributes', async ({ page }) => {
    const rule = page.locator('.buoyvs-sync-rule').nth(0);
    await addConditions(rule, 1);

    const cond = rule.locator('.buoyvs-condition').nth(0);
    await expect(cond.locator('.buoyvs-condition-field')).toHaveAttribute(
      'id', 'sync-rules-0-conditions-0-field'
    );
    await expect(cond.locator('.buoyvs-condition-operator')).toHaveAttribute(
      'id', 'sync-rules-0-conditions-0-operator'
    );
  });

  test('first condition gets index 0 in name attributes', async ({ page }) => {
    const rule = page.locator('.buoyvs-sync-rule').nth(0);
    await addConditions(rule, 1);

    const cond = rule.locator('.buoyvs-condition').nth(0);
    await expect(cond.locator('.buoyvs-condition-field')).toHaveAttribute(
      'name', 'sync_rules[0][conditions][0][field]'
    );
    await expect(cond.locator('.buoyvs-condition-operator')).toHaveAttribute(
      'name', 'sync_rules[0][conditions][0][operator]'
    );
  });

  test('second condition gets index 1 in id attributes', async ({ page }) => {
    const rule = page.locator('.buoyvs-sync-rule').nth(0);
    await addConditions(rule, 2);

    const cond = rule.locator('.buoyvs-condition').nth(1);
    await expect(cond.locator('.buoyvs-condition-field')).toHaveAttribute(
      'id', 'sync-rules-0-conditions-1-field'
    );
  });

  test('second condition gets index 1 in name attributes', async ({ page }) => {
    const rule = page.locator('.buoyvs-sync-rule').nth(0);
    await addConditions(rule, 2);

    const cond = rule.locator('.buoyvs-condition').nth(1);
    await expect(cond.locator('.buoyvs-condition-field')).toHaveAttribute(
      'name', 'sync_rules[0][conditions][1][field]'
    );
  });

  test('condition indices reflect the parent rule index', async ({ page }) => {
    // Add a second rule and add a condition to it
    await addRules(page, 1);
    const rule1 = page.locator('.buoyvs-sync-rule').nth(1);
    await rule1.locator('.buoyvs-action').selectOption('videos_sync_new');
    await addConditions(rule1, 1);

    const cond = rule1.locator('.buoyvs-condition').nth(0);
    await expect(cond.locator('.buoyvs-condition-field')).toHaveAttribute(
      'id', 'sync-rules-1-conditions-0-field'
    );
    await expect(cond.locator('.buoyvs-condition-field')).toHaveAttribute(
      'name', 'sync_rules[1][conditions][0][field]'
    );
  });
});

// ---------------------------------------------------------------------------
// Condition reindexing after removal
// ---------------------------------------------------------------------------

test.describe('Condition reindexing after removal', () => {
  test.beforeEach(async ({ page }) => {
    await goToChannels(page);
    await addRules(page, 1);
    await page.locator('.buoyvs-sync-rule').nth(0).locator('.buoyvs-action').selectOption('videos_sync_new');
  });

  test('removing the first of two conditions renumbers the survivor to index 0', async ({ page }) => {
    const rule = page.locator('.buoyvs-sync-rule').nth(0);
    await addConditions(rule, 2);

    await rule.locator('.buoyvs-condition').nth(0).locator('.buoyvs-remove-condition').click();
    await page.waitForTimeout(400);

    const survivor = rule.locator('.buoyvs-condition').nth(0);
    await expect(survivor.locator('.buoyvs-condition-field')).toHaveAttribute(
      'id', 'sync-rules-0-conditions-0-field'
    );
    await expect(survivor.locator('.buoyvs-condition-field')).toHaveAttribute(
      'name', 'sync_rules[0][conditions][0][field]'
    );
    await expect(survivor.locator('.buoyvs-condition-operator')).toHaveAttribute(
      'id', 'sync-rules-0-conditions-0-operator'
    );
    await expect(survivor.locator('.buoyvs-condition-operator')).toHaveAttribute(
      'name', 'sync_rules[0][conditions][0][operator]'
    );
  });

  test('removing the middle of three conditions produces sequential indices 0 and 1', async ({ page }) => {
    const rule = page.locator('.buoyvs-sync-rule').nth(0);
    await addConditions(rule, 3);

    // Remove the middle condition (index 1)
    await rule.locator('.buoyvs-condition').nth(1).locator('.buoyvs-remove-condition').click();
    await page.waitForTimeout(400);

    const conditions = rule.locator('.buoyvs-condition');
    await expect(conditions).toHaveCount(2);

    // First survivor stays at index 0
    await expect(conditions.nth(0).locator('.buoyvs-condition-field')).toHaveAttribute(
      'id', 'sync-rules-0-conditions-0-field'
    );
    await expect(conditions.nth(0).locator('.buoyvs-condition-field')).toHaveAttribute(
      'name', 'sync_rules[0][conditions][0][field]'
    );

    // Second survivor: was index 2, renumbered to 1
    await expect(conditions.nth(1).locator('.buoyvs-condition-field')).toHaveAttribute(
      'id', 'sync-rules-0-conditions-1-field'
    );
    await expect(conditions.nth(1).locator('.buoyvs-condition-field')).toHaveAttribute(
      'name', 'sync_rules[0][conditions][1][field]'
    );
  });

  test('removing the last of three conditions leaves sequential indices 0 and 1', async ({ page }) => {
    const rule = page.locator('.buoyvs-sync-rule').nth(0);
    await addConditions(rule, 3);

    await rule.locator('.buoyvs-condition').nth(2).locator('.buoyvs-remove-condition').click();
    await page.waitForTimeout(400);

    const conditions = rule.locator('.buoyvs-condition');
    await expect(conditions).toHaveCount(2);

    await expect(conditions.nth(0).locator('.buoyvs-condition-field')).toHaveAttribute(
      'name', 'sync_rules[0][conditions][0][field]'
    );
    await expect(conditions.nth(1).locator('.buoyvs-condition-field')).toHaveAttribute(
      'name', 'sync_rules[0][conditions][1][field]'
    );
  });

  test('condition reindexing uses the correct parent rule index after rule reindexing', async ({ page }) => {
    // Add a second rule, add conditions to it, then remove the first rule.
    // The second rule (originally index 1) should become index 0, and its
    // conditions should reflect the new rule index in both id and name.
    await addRules(page, 1); // now 2 rules total

    const rule1 = page.locator('.buoyvs-sync-rule').nth(1);
    await rule1.locator('.buoyvs-action').selectOption('videos_sync_new');
    await addConditions(rule1, 2);

    // Remove the first rule (index 0)
    await page.locator('.buoyvs-sync-rule').nth(0).locator('.buoyvs-remove-rule').click();
    await page.waitForTimeout(400);

    // The survivor (originally rule 1) is now rule 0
    const newRule0 = page.locator('.buoyvs-sync-rule').nth(0);
    await expect(newRule0).toHaveAttribute('data-rule-index', '0');

    // Its conditions should now reference rule index 0
    const cond0 = newRule0.locator('.buoyvs-condition').nth(0);
    const cond1 = newRule0.locator('.buoyvs-condition').nth(1);

    await expect(cond0.locator('.buoyvs-condition-field')).toHaveAttribute(
      'id', 'sync-rules-0-conditions-0-field'
    );
    await expect(cond0.locator('.buoyvs-condition-field')).toHaveAttribute(
      'name', 'sync_rules[0][conditions][0][field]'
    );
    await expect(cond1.locator('.buoyvs-condition-field')).toHaveAttribute(
      'id', 'sync-rules-0-conditions-1-field'
    );
    await expect(cond1.locator('.buoyvs-condition-field')).toHaveAttribute(
      'name', 'sync_rules[0][conditions][1][field]'
    );
  });
});

// ---------------------------------------------------------------------------
// Value input name attribute
// ---------------------------------------------------------------------------

test.describe('Value input name attribute', () => {
  test.beforeEach(async ({ page }) => {
    await goToChannels(page);
    await addRules(page, 1);
    await page.locator('.buoyvs-sync-rule').nth(0).locator('.buoyvs-action').selectOption('videos_sync_new');
  });

  test('value input name uses correct rule and condition indices', async ({ page }) => {
    const rule = page.locator('.buoyvs-sync-rule').nth(0);
    await addConditions(rule, 1);

    // Select a field to trigger the value input to be replaced with the correct name
    await rule.locator('.buoyvs-condition').nth(0).locator('.buoyvs-condition-field').selectOption('title');

    const valueInput = rule.locator('.buoyvs-condition').nth(0).locator('.buoyvs-condition-value');
    await expect(valueInput).toHaveAttribute('name', 'sync_rules[0][conditions][0][value]');
  });

  test('value input id uses correct rule and condition indices', async ({ page }) => {
    const rule = page.locator('.buoyvs-sync-rule').nth(0);
    await addConditions(rule, 1);

    await rule.locator('.buoyvs-condition').nth(0).locator('.buoyvs-condition-field').selectOption('title');

    const valueInput = rule.locator('.buoyvs-condition').nth(0).locator('.buoyvs-condition-value');
    await expect(valueInput).toHaveAttribute(
      'id', 'sync-rules-0-conditions-0-value'
    );
  });

  test('second condition value input uses index 1', async ({ page }) => {
    const rule = page.locator('.buoyvs-sync-rule').nth(0);
    await addConditions(rule, 2);

    await rule.locator('.buoyvs-condition').nth(1).locator('.buoyvs-condition-field').selectOption('title');

    const valueInput = rule.locator('.buoyvs-condition').nth(1).locator('.buoyvs-condition-value');
    await expect(valueInput).toHaveAttribute('name', 'sync_rules[0][conditions][1][value]');
    await expect(valueInput).toHaveAttribute('id', 'sync-rules-0-conditions-1-value');
  });
});
