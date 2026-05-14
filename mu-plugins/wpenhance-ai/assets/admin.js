/* WPEnhance AI – admin meta box interactions */

// ─── Feature button click ────────────────────────────────────────────────────

document.addEventListener('click', async (event) => {

    const button = event.target.closest('.wpenhance-ai-action');

    if (!button) return;

    const featureKey = button.dataset.feature;
    const postId     = button.dataset.postId;
    const panel      = button.closest('.wpenhance-ai-panel');
    const result     = panel.querySelector('.wpenhance-ai-result');
    const params     = collectParams(panel, featureKey);

    button.disabled = true;

    await runFeature(featureKey, postId, params, result);

    button.disabled = false;
});

// ─── Force-refresh button click ──────────────────────────────────────────────

document.addEventListener('click', async (event) => {

    const button = event.target.closest('.wpenhance-ai-refresh');

    if (!button) return;

    const featureKey = button.dataset.feature;
    const postId     = button.dataset.postId;
    const panel      = button.closest('.wpenhance-ai-panel');
    const result     = panel.querySelector('.wpenhance-ai-result');
    const params     = collectParams(panel, featureKey);

    params.force_refresh = true;

    button.disabled      = true;
    button.textContent   = '↺ Refreshing…';

    await runFeature(featureKey, postId, params, result);

    // Button is inside the result area and will have been replaced by
    // the re-render above, so no need to reset its state.
});

// ─── "Apply to Editor" button ────────────────────────────────────────────────

document.addEventListener('click', async (event) => {

    const button = event.target.closest('.wpenhance-ai-apply');

    if (!button) return;

    const panel    = button.closest('.wpenhance-ai-panel');
    const result   = button.closest('.wpenhance-ai-content-result');
    const textarea = panel.querySelector('.wpenhance-ai-textarea');

    if (!textarea) return;

    // Apply translated content to the editor.
    if (window.wp?.data) {

        wp.data
            .dispatch('core/editor')
            .editPost({ content: textarea.value });

    } else {

        // Classic editor fallback.
        const classicEditor = document.querySelector('#content');
        if (classicEditor) classicEditor.value = textarea.value;
    }

    // If footnotes were translated, persist them via the REST endpoint.
    const footnotesJson = result?.dataset.footnotes || '';
    const postId        = panel.querySelector('.wpenhance-ai-action')?.dataset.postId;

    if (footnotesJson && postId) {

        try {

            await fetch(
                `${WPEnhanceAI.restUrl}/footnotes/${postId}`,
                {
                    method:  'POST',
                    headers: {
                        'X-WP-Nonce':   WPEnhanceAI.nonce,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ footnotes: footnotesJson }),
                }
            );

        } catch (_) {
            // Content was already applied — footnote failure is non-fatal.
            console.warn('WPEnhance AI: could not save translated footnotes.');
        }
    }

    button.textContent = 'Applied ✓';
    button.disabled    = true;
});

// ─── "Copy" button ───────────────────────────────────────────────────────────

document.addEventListener('click', async (event) => {

    const button = event.target.closest('.wpenhance-ai-copy');

    if (!button) return;

    const panel    = button.closest('.wpenhance-ai-panel');
    const textarea = panel.querySelector('.wpenhance-ai-textarea');

    if (!textarea) return;

    try {

        await navigator.clipboard.writeText(textarea.value);

        button.textContent = 'Copied ✓';

        setTimeout(() => { button.textContent = 'Copy'; }, 2000);

    } catch (_) {

        // Fallback for browsers that restrict clipboard access.
        textarea.select();
        document.execCommand('copy');

        button.textContent = 'Copied ✓';

        setTimeout(() => { button.textContent = 'Copy'; }, 2000);
    }
});

// ─── Core fetch + render ──────────────────────────────────────────────────────

/**
 * Call a feature endpoint and render the result into the given container.
 * Shared by the main action button and the force-refresh button.
 */
async function runFeature(featureKey, postId, params, resultEl) {

    resultEl.innerHTML = '<p class="wpenhance-ai-status">Generating…</p>';

    try {

        const response = await fetch(
            `${WPEnhanceAI.restUrl}/feature/${featureKey}/${postId}`,
            {
                method:  'POST',
                headers: {
                    'X-WP-Nonce':   WPEnhanceAI.nonce,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(params),
            }
        );

        const data = await response.json();

        if (!data.success || !data.output) {

            const msg = data.error || 'Generation failed.';
            resultEl.innerHTML = `<p class="wpenhance-ai-error">${escapeHtml(msg)}</p>`;
            return;
        }

        if (data.type === 'content') {

            renderContentResult(resultEl, data, featureKey, postId);

        } else {

            const cachedBadge = data.cached
                ? ' <span class="wpenhance-ai-cached-badge">cached</span>'
                : '';

            const refreshRow = data.cached
                ? renderRefreshRow(featureKey, postId)
                : '';

            resultEl.innerHTML = `
                <p class="wpenhance-ai-result-meta">${cachedBadge}</p>
                <textarea
                    class="wpenhance-ai-textarea"
                    rows="4"
                >${escapeHtml(data.output)}</textarea>
                ${refreshRow}`;
        }

    } catch (_) {

        resultEl.innerHTML = '<p class="wpenhance-ai-error">Request failed.</p>';
    }
}

// ─── Render helpers ───────────────────────────────────────────────────────────

/**
 * Render the result area for full-content outputs (translations, generated content…).
 *
 * Builds the meta summary line dynamically based on which feature produced
 * the result, so new content-type features don't require JS changes.
 */
function renderContentResult(container, data, featureKey, postId) {

    const footnotesAttr = data.footnotes
        ? ` data-footnotes="${escapeAttr(data.footnotes)}"`
        : '';

    const cachedBadge = data.cached
        ? ' <span class="wpenhance-ai-cached-badge">cached</span>'
        : '';

    // ── Meta summary line (feature-specific) ──────────────────────────────────
    let metaSummary;

    if (featureKey === 'translation' && data.language) {

        metaSummary = `Translated to: <strong>${escapeHtml(data.language)}</strong>`;

    } else if (featureKey === 'content-generator') {

        const parts = [];
        if (data.content_type) parts.push(escapeHtml(data.content_type));
        if (data.tone)         parts.push(escapeHtml(data.tone) + ' tone');
        metaSummary = parts.length
            ? parts.join(' · ')
            : 'Content generated';

    } else {

        metaSummary = 'Content ready';
    }

    // ── Footnotes note (translation only) ─────────────────────────────────────
    const footnotesNote = data.footnotes
        ? `<p class="wpenhance-ai-result-meta">
               ${countFootnotes(data.footnotes)} footnote(s) translated — applied together with content.
           </p>`
        : '';

    const refreshRow = data.cached
        ? renderRefreshRow(featureKey, postId)
        : '';

    container.innerHTML = `
        <div class="wpenhance-ai-content-result"${footnotesAttr}>
            <p class="wpenhance-ai-result-meta">
                ${metaSummary}${cachedBadge}
            </p>
            ${footnotesNote}
            <textarea
                class="wpenhance-ai-textarea wpenhance-ai-textarea--large"
                rows="10"
            >${escapeHtml(data.output)}</textarea>
            <div class="wpenhance-ai-btn-row">
                <button
                    type="button"
                    class="button button-primary wpenhance-ai-apply"
                >Apply to Editor</button>
                <button
                    type="button"
                    class="button button-secondary wpenhance-ai-copy"
                >Copy</button>
            </div>
            ${refreshRow}
        </div>`;
}

/**
 * Render the force-refresh button with its explanatory hint.
 * Only shown when the current result came from the cache.
 */
function renderRefreshRow(featureKey, postId) {

    return `
        <div class="wpenhance-ai-refresh-row">
            <button
                type="button"
                class="wpenhance-ai-refresh"
                data-feature="${escapeAttr(featureKey)}"
                data-post-id="${escapeAttr(postId)}"
            >↺ Refresh</button>
            <span class="wpenhance-ai-refresh-hint">
                Re-generates and updates the cached result.
            </span>
        </div>`;
}

// ─── Utilities ────────────────────────────────────────────────────────────────

/**
 * Collect the values of any extra UI fields belonging to a feature.
 * Handles both <select> and <textarea> input fields.
 */
function collectParams(panel, featureKey) {

    const params = {};

    panel
        .querySelectorAll(
            `.wpenhance-ai-select[data-feature-ref="${featureKey}"],` +
            `.wpenhance-ai-input-textarea[data-feature-ref="${featureKey}"]`
        )
        .forEach((field) => {
            params[field.dataset.field] = field.value;
        });

    return params;
}

/**
 * Count entries in a JSON-encoded footnotes array.
 */
function countFootnotes(json) {

    try {
        return JSON.parse(json).length;
    } catch (_) {
        return 0;
    }
}

/**
 * Escape a value for safe use inside an HTML attribute.
 */
function escapeAttr(value) {

    return String(value)
        .replace(/&/g,  '&amp;')
        .replace(/"/g,  '&quot;')
        .replace(/'/g,  '&#39;')
        .replace(/</g,  '&lt;')
        .replace(/>/g,  '&gt;');
}

/**
 * Safely escape a string for use inside innerHTML.
 */
function escapeHtml(value) {

    const div     = document.createElement('div');
    div.innerText = String(value);

    return div.innerHTML;
}
