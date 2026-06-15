// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Theme compatibility shim for AI Course Assist placement.
 *
 * Some themes (e.g. edash) do not render the standard [role="main"]
 * element on all pages. Moodle core's aiplacement_courseassist JS
 * calls document.querySelector('[role="main"]').innerText without
 * a null check, causing a TypeError that prevents AI actions from
 * being sent to the provider.
 *
 * This module runs early and adds role="main" to the primary content
 * area if it's missing, ensuring the AI placement JS works correctly.
 *
 * @module     aiprovider_pollinations/theme_compat
 * @copyright  2026 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Ensure the page has an element with role="main".
 *
 * Falls back to common theme content selectors.
 */
const ensureMainRegion = () => {
    // Already exists — nothing to do.
    if (document.querySelector('[role="main"]')) {
        return;
    }

    // Try common content area selectors used by Moodle themes.
    const fallbacks = [
        '#region-main',
        '#page-content',
        '[data-region="page-content"]',
        '#maincontent',
        'main',
        '#content',
        '.course-content',
    ];

    for (const selector of fallbacks) {
        const el = document.querySelector(selector);
        if (el) {
            el.setAttribute('role', 'main');
            return;
        }
    }

    // Last resort: create a hidden element with role="main" and populate
    // it with visible page text so getTextContent() returns something useful.
    const bodyText = document.body
        ? (document.body.innerText || document.body.textContent || '')
        : '';

    if (bodyText.trim().length > 0) {
        const shim = document.createElement('div');
        shim.setAttribute('role', 'main');
        shim.style.cssText = 'position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);';
        shim.textContent = bodyText.substring(0, 50000); // Limit to 50K chars.
        if (document.body) {
            document.body.appendChild(shim);
        }
    }
};

/**
 * Initialise the compatibility shim.
 */
export const init = () => {
    // Run on DOMContentLoaded (the placement JS also waits for this).
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensureMainRegion);
    } else {
        ensureMainRegion();
    }

    // Also run after a small delay to catch themes that render content
    // dynamically after DOMContentLoaded.
    setTimeout(ensureMainRegion, 500);
};
