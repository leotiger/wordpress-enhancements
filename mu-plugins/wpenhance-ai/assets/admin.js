/* WPEnhance AI – admin meta box interactions */

// ─── Conditional field visibility ────────────────────────────────────────────

/**
 * Show or hide field wrappers that carry data-condition-field / data-condition-value
 * attributes.  A wrapper is visible only when the referenced controller field
 * holds the expected value.  Runs once on DOMContentLoaded and again on every
 * change event on a controlling select.
 *
 * @param {Element} panel  .wpenhance-ai-panel root element.
 */
function initConditionalFields(panel) {

    panel.querySelectorAll('[data-condition-field]').forEach((wrapper) => {

        const condField = wrapper.dataset.conditionField;
        const condValue = wrapper.dataset.conditionValue;

        const controller = panel.querySelector(
            `[data-field="${condField}"]`
        );

        if (!controller) return;

        const sync = () => {
            wrapper.style.display = controller.value === condValue ? '' : 'none';
        };

        // Set initial state without transition flicker.
        sync();

        controller.addEventListener('change', sync);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.wpenhance-ai-panel').forEach(initConditionalFields);
});

// ─── Feature button click ────────────────────────────────────────────────────

document.addEventListener('click', async (event) => {

    const button = event.target.closest('.wpenhance-ai-action');

    if (!button) return;

    const featureKey = button.dataset.feature;
    const postId     = button.dataset.postId;
    const panel      = button.closest('.wpenhance-ai-panel');
    const result     = button.closest('.wpenhance-ai-feature-group').querySelector('.wpenhance-ai-result');
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
    const result     = button.closest('.wpenhance-ai-result');
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

    const panel           = button.closest('.wpenhance-ai-panel');
    const result          = button.closest('.wpenhance-ai-content-result');
    const textarea        = panel.querySelector('.wpenhance-ai-textarea');
    const translatedTitle = result?.dataset.translatedTitle || '';

    if (!textarea) return;

    // Clear any previous error and show an in-progress state.
    clearApplyError(button);
    button.disabled    = true;
    button.textContent = 'Applying…';

    try {

        // Gutenberg loads meta boxes inside an iframe — wp.data lives in the
        // parent window, not in this iframe context.  window.parent.wp.data
        // is the live editor store; dispatching to window.wp.data (the iframe)
        // is a no-op that leaves the editor unchanged.
        if (window.parent.wp?.data) {

            const payload = { content: textarea.value };
            if (translatedTitle) payload.title = translatedTitle;

            // Apply footnotes through the Gutenberg store so they are part of
            // the same save cycle as the content.  Writing directly to the DB
            // via a REST call would be overwritten the moment the user hits
            // Save, because Gutenberg flushes its own meta.footnotes on save.
            const footnotesJson = result?.dataset.footnotes || '';
            if (footnotesJson) payload.meta = { footnotes: footnotesJson };

            // Snapshot the editor's current content so we can verify the
            // dispatch actually took effect after it resolves.
            const editorSelect  = window.parent.wp.data.select('core/editor');
            const beforeContent = editorSelect.getEditedPostAttribute('content') ?? '';

            await window.parent.wp.data
                .dispatch('core/editor')
                .editPost(payload);

            // Verify: the store must now hold different content, or content
            // that already matched what we sent (idempotent re-apply).
            const afterContent  = editorSelect.getEditedPostAttribute('content') ?? '';
            const contentChanged = afterContent !== beforeContent;
            const contentMatches = afterContent.trim() === textarea.value.trim();

            if (!contentChanged && !contentMatches) {
                throw new Error('The editor did not accept the content — please try again.');
            }

        } else {

            // Classic editor: no iframe, #content and #title are in this document.
            const classicEditor = document.querySelector('#content');
            if (!classicEditor) throw new Error('Classic editor element not found.');

            classicEditor.value = textarea.value;

            if (translatedTitle) {
                const classicTitle = document.querySelector('#title');
                if (classicTitle) classicTitle.value = translatedTitle;
            }
        }

        button.textContent = 'Applied ✓';
        // Leave disabled — re-applying the same content is a no-op and confusing.

    } catch (err) {

        // Restore the button so the user can retry.
        button.textContent = 'Apply to Editor';
        button.disabled    = false;
        showApplyError(button, err.message || 'Apply failed — please try again.');
    }
});

/**
 * Show an inline error message beneath the Apply button's row.
 */
function showApplyError(button, message) {

    const btnRow = button.closest('.wpenhance-ai-btn-row');
    if (!btnRow) return;

    let notice = btnRow.querySelector('.wpenhance-ai-apply-error');

    if (!notice) {
        notice = document.createElement('span');
        notice.className = 'wpenhance-ai-apply-error';
        btnRow.insertAdjacentElement('afterend', notice);
    }

    notice.textContent = message;
}

/**
 * Remove any previously shown Apply error notice.
 */
function clearApplyError(button) {

    const btnRow = button.closest('.wpenhance-ai-btn-row');
    if (!btnRow) return;

    btnRow.nextElementSibling
        ?.classList.contains('wpenhance-ai-apply-error')
        && btnRow.nextElementSibling.remove();
}

// ─── "Copy" button ───────────────────────────────────────────────────────────

document.addEventListener('click', async (event) => {

    const button = event.target.closest('.wpenhance-ai-copy');

    if (!button) return;

    // Scope the textarea search to the nearest result container so it does
    // not accidentally grab a field textarea from another feature group.
    const resultContainer =
        button.closest('.wpenhance-ai-content-result') ||
        button.closest('.wpenhance-ai-result');

    const textarea = resultContainer
        ? resultContainer.querySelector('.wpenhance-ai-textarea')
        : button.closest('.wpenhance-ai-panel')?.querySelector('.wpenhance-ai-textarea');

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

        } else if (data.type === 'chunk') {

            renderChunkResult(resultEl, data);

        } else {

            renderTextResult(resultEl, data, featureKey, postId);
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

    const titleAttr = data.translated_title
        ? ` data-translated-title="${escapeAttr(data.translated_title)}"`
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
        <div class="wpenhance-ai-content-result"${footnotesAttr}${titleAttr}>
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
 * Render the result area for a chunk translation.
 *
 * No "Apply to Editor" button — the user copies and pastes the translated
 * snippet manually wherever it belongs (e.g. into a footnote field).
 */
function renderChunkResult(container, data) {

    const lang = data.language
        ? `Chunk translated to: <strong>${escapeHtml(data.language)}</strong>`
        : 'Chunk translated';

    container.innerHTML = `
        <div class="wpenhance-ai-content-result wpenhance-ai-chunk-result">
            <p class="wpenhance-ai-result-meta">${lang}</p>
            <p class="wpenhance-ai-chunk-hint">
                Copy the result and paste it wherever needed (e.g. directly into the footnote field).
            </p>
            <textarea
                class="wpenhance-ai-textarea wpenhance-ai-textarea--large"
                rows="8"
            >${escapeHtml(data.output)}</textarea>
            <div class="wpenhance-ai-btn-row">
                <button
                    type="button"
                    class="button button-secondary wpenhance-ai-copy"
                >Copy</button>
            </div>
        </div>`;
}

/**
 * Render the result area for short text outputs (meta descriptions, excerpts…).
 *
 * Always includes a Copy button.  For meta-description results an ⓘ info
 * button is also rendered; hovering/focusing it reveals a character-count
 * overlay with an SEO quality hint.
 */
function renderTextResult(container, data, featureKey, postId) {

    const text = data.output || '';

    const cachedBadge = data.cached
        ? ' <span class="wpenhance-ai-cached-badge">cached</span>'
        : '';

    const refreshRow = data.cached
        ? renderRefreshRow(featureKey, postId)
        : '';

    const infoHtml = featureKey === 'meta-description'
        ? buildMetaInfoOverlay(text.length)
        : '';

    container.innerHTML = `
        <p class="wpenhance-ai-result-meta">${cachedBadge}</p>
        <div class="wpenhance-ai-textarea-wrap">
            <textarea
                class="wpenhance-ai-textarea"
                rows="3"
            >${escapeHtml(text)}</textarea>
        </div>
        <div class="wpenhance-ai-result-bar">
            <button
                type="button"
                class="button button-secondary wpenhance-ai-copy"
            >Copy</button>
            ${infoHtml}
        </div>
        ${refreshRow}`;
}

/**
 * Build the ⓘ info button + character-count tooltip for meta descriptions.
 *
 * Quality thresholds:
 *   140–160 chars → green  (optimal SERP real estate)
 *   120–139 / 161–180 → amber  (borderline)
 *   < 120 or > 180      → red    (too short / too long)
 */
function buildMetaInfoOverlay(charCount) {

    let quality, qualityClass;

    if (charCount >= 140 && charCount <= 160) {

        quality      = `✓ Good length (${charCount} chars)`;
        qualityClass = '--good';

    } else if (charCount >= 120 && charCount <= 180) {

        quality      = `⚠ Borderline (${charCount} chars)`;
        qualityClass = '--warn';

    } else {

        quality      = charCount < 120
            ? `✗ Too short (${charCount} chars)`
            : `✗ Too long (${charCount} chars)`;
        qualityClass = '--bad';
    }

    return `
        <div class="wpenhance-ai-info-wrap">
            <button
                type="button"
                class="wpenhance-ai-info-btn"
                aria-label="SEO character-count info"
            >ⓘ</button>
            <div class="wpenhance-ai-info-overlay" role="tooltip">
                <span class="wpenhance-ai-info-quality ${qualityClass}">${quality}</span>
                <span class="wpenhance-ai-info-hint">Target: 140–160 chars for optimal SERP display</span>
            </div>
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
 *
 * For the translation feature, also reads the current footnotes value
 * from the Gutenberg editor meta store (window.parent.wp.data) so that
 * unsaved footnotes are captured even before the post is saved to the DB.
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

    // Pull footnotes from the live editor meta store so translation always
    // works against the current in-editor state, not the last-saved DB value.
    // The meta key is "footnotes" both in the Gutenberg editor store and in the DB.
    // Skip this in chunk mode — the user supplies the text to translate directly.
    if (featureKey === 'translation' && params.translate_mode !== 'chunk' && window.parent.wp?.data) {

        const meta = window.parent.wp.data
            .select('core/editor')
            ?.getEditedPostAttribute('meta');

        if (meta && typeof meta.footnotes === 'string' && meta.footnotes !== '') {
            params.footnotes_meta = meta.footnotes;
        }
    }

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
 *
 * Replaces only the five characters that are unsafe in HTML contexts.
 * Intentionally avoids the div.innerText / div.innerHTML trick because
 * that approach converts \n newlines to <br> elements — which corrupts
 * Gutenberg block markup where newlines between block comments and inner
 * HTML are structurally meaningful.
 */
function escapeHtml(value) {

    return String(value)
        .replace(/&/g,  '&amp;')
        .replace(/</g,  '&lt;')
        .replace(/>/g,  '&gt;')
        .replace(/"/g,  '&quot;')
        .replace(/'/g,  '&#39;');
}
