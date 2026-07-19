/**
 * Settings page tests.
 *
 * The Settings page lives at:
 *   /wp-admin/edit.php?post_type=buoyvs_videos&page=buoyvs_settings
 *
 * It has two sections:
 *  1. API Settings  – Google API Key text input
 *  2. Archive Pages – Checkboxes + slug inputs for Videos, Channels, Playlists
 */

import { test, expect } from '@playwright/test';
import { goToSettings } from './helpers.js';

// ---------------------------------------------------------------------------
// Page access
// ---------------------------------------------------------------------------

test.describe('Settings page access', () => {
  test('loads the settings page without errors', async ({ page }) => {
    await goToSettings(page);
    await expect(page).toHaveURL(/page=buoyvs_settings/);
    await expect(page.locator('h1, h2').filter({ hasText: /Buoy Video Sync/i }).first()).toBeVisible();
  });

  test('is reachable via the Buoy Video Sync admin menu', async ({ page }) => {
    await page.goto('/wp-admin/');
    // Buoy Video Sync registers under the Videos CPT menu — hover to reveal submenu then click Settings
    const videosMenu = page.locator('#menu-posts-buoyvs_videos');
    await videosMenu.hover();
    await videosMenu.getByRole('link', { name: 'Settings' }).click();
    await expect(page).toHaveURL(/buoyvs_settings/);
  });
});

// ---------------------------------------------------------------------------
// API Settings section
// ---------------------------------------------------------------------------

test.describe('API Settings', () => {
  test.beforeEach(async ({ page }) => {
    await goToSettings(page);
  });

  test('Google API Key field is visible', async ({ page }) => {
    await expect(page.locator('input[name="buoyvs_api_key"]')).toBeVisible();
  });

  test('Google API Key field accepts text input', async ({ page }) => {
    const input = page.locator('input[name="buoyvs_api_key"]');
    await input.fill('AIzaSyTestKey123');
    await expect(input).toHaveValue('AIzaSyTestKey123');
  });

  test('settings form can be submitted', async ({ page }) => {
    await page.locator('input[name="buoyvs_api_key"]').fill('AIzaSyTestKey123');
    await page.locator('input[type="submit"], button[type="submit"]').click();

    // WordPress redirects back to the settings page after save
    await page.waitForURL(/buoyvs_settings/);

    // Page should still show the settings form (save completed without crash)
    await expect(page.locator('input[name="buoyvs_api_key"]')).toBeVisible();
    // Any admin notice should be present (success or validation error from API key check)
    await expect(page.locator('.notice, #setting-error-settings_updated')).toBeVisible();
  });
});

// ---------------------------------------------------------------------------
// Archive Pages section
// ---------------------------------------------------------------------------

test.describe('Archive Pages', () => {
  test.beforeEach(async ({ page }) => {
    await goToSettings(page);
  });

  test('shows enabled/slug controls for Videos archive', async ({ page }) => {
    await expect(page.locator('input[name="buoyvs_active_archives[buoyvs-video][enabled]"]')).toBeVisible();
    await expect(page.locator('input[name="buoyvs_active_archives[buoyvs-video][slug]"]')).toBeVisible();
  });

  test('shows enabled/slug controls for Channels archive', async ({ page }) => {
    await expect(page.locator('input[name="buoyvs_active_archives[buoyvs-channel][enabled]"]')).toBeVisible();
    await expect(page.locator('input[name="buoyvs_active_archives[buoyvs-channel][slug]"]')).toBeVisible();
  });

  test('shows enabled/slug controls for Playlists archive', async ({ page }) => {
    await expect(page.locator('input[name="buoyvs_active_archives[buoyvs-playlist][enabled]"]')).toBeVisible();
    await expect(page.locator('input[name="buoyvs_active_archives[buoyvs-playlist][slug]"]')).toBeVisible();
  });

  test('archive slug field accepts custom slug text', async ({ page }) => {
    const slugInput = page.locator('input[name="buoyvs_active_archives[buoyvs-video][slug]"]');
    await slugInput.fill('my-videos');
    await expect(slugInput).toHaveValue('my-videos');
  });

  test('archive checkbox can be toggled', async ({ page }) => {
    const checkbox = page.locator('input[name="buoyvs_active_archives[buoyvs-video][enabled]"]');
    const wasChecked = await checkbox.isChecked();

    if (wasChecked) {
      await checkbox.uncheck();
      await expect(checkbox).not.toBeChecked();
    } else {
      await checkbox.check();
      await expect(checkbox).toBeChecked();
    }
  });
});
