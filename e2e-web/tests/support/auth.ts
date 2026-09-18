import type { Page } from '@playwright/test'

/**
 * Shared login helper for the web browser suite.
 *
 * Every one of the 68 source files in backend/tests/Browser carried its own
 * byte-identical copy of this function plus a hardcoded
 * `BASE_URL = 'http://localhost:8000'`. That constant is why the whole
 * suite would now fail at the first navigation regardless of anything else:
 * the dev server moved to 8001 when another service took 8000, and 68
 * copies of a constant is 68 places to miss.
 *
 * Paths here are relative — Playwright resolves them against the config's
 * baseURL, which reads E2E_WEB_BASE_URL.
 */

/** Seeded by BrowserTestFixtureSeeder for every fixture account. */
export const PASSWORD = 'Passw0rd!'

/** The Ganti Password specs need an account whose password they may change. */
export const CHANGE_PASSWORD_PASSWORD = 'OldPass123!'

export const LOGIN_PATH = '/login'

export async function login(page: Page, username: string, password: string = PASSWORD): Promise<void> {
  await page.goto(LOGIN_PATH)
  await page.locator('#username').fill(username)
  await page.locator('#password').fill(password)
  // `button[type="submit"]` matches three elements on this page; the login
  // button carries its own class, so target that instead.
  await page.locator('button.login-button').click()
  // 'domcontentloaded', not the default 'load': post-login pages pull
  // external resources (web fonts, the chatbot widget's API) whose `load`
  // event can take longer than the test timeout even though the login
  // itself succeeded and the page is fully usable.
  await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH), { waitUntil: 'domcontentloaded' })
}
