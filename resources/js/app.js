// Fetch pagination in place so the other article section and the active scroll area remain untouched.
const paginationRequests = new Map();

async function updateArticleSection(sectionKey, destination, updateHistory = true) {
    const section = document.querySelector(`[data-article-section="${sectionKey}"]`);
    if (!section) {
        return false;
    }

    paginationRequests.get(sectionKey)?.abort();
    const controller = new AbortController();
    paginationRequests.set(sectionKey, controller);

    try {
        const response = await fetch(destination, {
            headers: {
                Accept: 'text/html',
                'X-Pagination-Section': sectionKey,
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        });

        if (!response.ok) {
            return false;
        }

        const documentFragment = new DOMParser().parseFromString(await response.text(), 'text/html');
        const replacement = documentFragment.querySelector(`[data-article-section="${sectionKey}"]`);

        if (!replacement || paginationRequests.get(sectionKey) !== controller) {
            return false;
        }

        section.replaceWith(replacement);
        if (updateHistory) {
            window.history.pushState({}, '', destination);
        }
        return true;
    } catch (error) {
        if (error.name !== 'AbortError') {
            console.error('Could not load article page.', error);
        }

        return false;
    } finally {
        if (paginationRequests.get(sectionKey) === controller) {
            paginationRequests.delete(sectionKey);
        }
    }
}

document.addEventListener('click', (event) => {
    const link = event.target instanceof Element ? event.target.closest('a[data-pagination-section]') : null;

    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    const destination = new URL(link.href, window.location.href);
    if (destination.origin !== window.location.origin) {
        return;
    }

    event.preventDefault();
    updateArticleSection(link.dataset.paginationSection, destination.href);
});

window.addEventListener('popstate', () => {
    const currentUrl = new URL(window.location.href);

    document.querySelectorAll('[data-article-section]').forEach((section) => {
        const pageName = section.dataset.pageName;
        const pageInUrl = currentUrl.searchParams.get(pageName) || '1';

        if (pageInUrl !== section.dataset.currentPage) {
            updateArticleSection(section.dataset.articleSection, currentUrl.href, false);
        }
    });
});

function showArticleDeleteNotice(message, isError = false) {
    const notice = document.querySelector('[data-article-delete-notice]');
    if (!notice) {
        return;
    }

    notice.hidden = false;
    notice.textContent = message;
    notice.setAttribute('role', isError ? 'alert' : 'status');
    notice.setAttribute('aria-live', isError ? 'assertive' : 'polite');
    notice.className = isError
        ? 'fixed top-4 right-4 left-4 z-50 mx-auto max-w-md rounded-lg border border-studio-bad/40 bg-studio-bad-bg p-3 text-sm text-studio-bad shadow-xl'
        : 'fixed top-4 right-4 left-4 z-50 mx-auto max-w-md rounded-lg border border-studio-ok/30 bg-studio-live p-3 text-sm text-studio-ok shadow-xl';
}

document.addEventListener('submit', async (event) => {
    const form = event.target instanceof HTMLFormElement ? event.target : null;
    if (!form?.matches('[data-article-delete-form]')) {
        return;
    }

    event.preventDefault();

    try {
        const csrfToken = form.querySelector('input[name="_token"]')?.value;
        const response = await fetch(form.action, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
        });
        const result = await response.json().catch(() => null);

        if (!response.ok || !result?.sectionKey || !Number.isInteger(Number(result.currentPage))) {
            showArticleDeleteNotice('Artikeln kunde inte tas bort. Försök igen.', true);
            return;
        }

        try {
            localStorage.setItem('superblog-article-deleted', JSON.stringify({
                superblogId: result.superblogId,
                nonce: `${Date.now()}-${Math.random()}`,
            }));
        } catch (error) {
            console.warn('Could not notify open previews about the deleted article.', error);
        }

        const section = document.querySelector(`[data-article-section="${result.sectionKey}"]`);
        if (!section) {
            showArticleDeleteNotice('Artikeln har tagits bort permanent. Ladda om sidan för att uppdatera listan.');
            form.closest('dialog')?.close();
            return;
        }

        const destination = new URL(window.location.href);
        destination.searchParams.set(section.dataset.pageName, String(result.currentPage));
        window.history.replaceState({}, '', destination);
        form.closest('dialog')?.close();

        const sectionUpdated = await updateArticleSection(result.sectionKey, destination.href, false);
        showArticleDeleteNotice(sectionUpdated
            ? result.message
            : 'Artikeln har tagits bort, men listan kunde inte uppdateras. Ladda om sidan.', !sectionUpdated);
    } catch {
        showArticleDeleteNotice('Artikeln kunde inte tas bort. Kontrollera anslutningen och försök igen.', true);
    }
});

window.addEventListener('storage', (event) => {
    if (event.key !== 'superblog-article-deleted' || !event.newValue) {
        return;
    }

    const previewSuperblogId = document.body.dataset.adminPreviewSuperblogId;
    if (!previewSuperblogId) {
        return;
    }

    try {
        const change = JSON.parse(event.newValue);
        if (String(change.superblogId) === previewSuperblogId) {
            window.location.reload();
        }
    } catch {
        // Ignore malformed storage events; they do not affect the current page.
    }
});

// Article editing stays in the preview; only explicitly permitted blocks become editable on double-click.
document.querySelectorAll('[data-article-editor]').forEach((form) => {
    const title = form.querySelector('[data-editor-title]');
    const titleField = form.querySelector('[data-article-title-field]');
    const body = form.querySelector('[data-editor-body]');
    const contentField = form.querySelector('[data-article-content-field]');
    const sizeSelect = form.querySelector('[data-article-text-size]');
    const backlinkPreview = form.querySelector('[data-editor-backlinks-preview]');
    let savedRange = null;

    if (!title || !titleField || !body || !contentField) {
        return;
    }

    const syncBacklinks = () => {
        if (!backlinkPreview) {
            return;
        }

        const brandUrl = form.querySelector('[data-editor-link-url="brand"]')?.value.trim() ?? '';
        const brandText = form.querySelector('[data-editor-link-anchor="brand"]')?.value.trim() ?? '';
        const productUrl = form.querySelector('[data-editor-link-url="product"]')?.value.trim() ?? '';
        const productText = form.querySelector('[data-editor-link-anchor="product"]')?.value.trim() ?? '';

        const updateLink = (kind, url, text) => {
            const link = form.querySelector(`[data-editor-${kind}-preview]`);
            let safeUrl = false;
            try {
                safeUrl = ['http:', 'https:'].includes(new URL(url).protocol);
            } catch {}

            link.hidden = !safeUrl || !text;
            if (safeUrl && text) {
                link.href = url;
                link.textContent = text;
            }

            return !link.hidden;
        };

        const hasBrand = updateLink('brand', brandUrl, brandText);
        const hasProduct = updateLink('product', productUrl, productText);
        const separator = form.querySelector('[data-editor-link-separator]');
        separator.hidden = !(hasBrand && hasProduct);
        backlinkPreview.hidden = !(hasBrand || hasProduct);
    };

    const syncFields = () => {
        titleField.value = title.textContent.replace(/\s+/g, ' ').trim();
        contentField.value = body.innerHTML;
        syncBacklinks();
        updateHeadingButton();
    };

    const updateHeadingButton = () => {
        const h3Button = form.querySelector('[data-article-add-block="h3"]');
        if (h3Button) {
            h3Button.disabled = !body.querySelector('h2');
        }
    };

    const editableBlock = (target) => target.closest('[data-editor-title], [data-editor-body] p, [data-editor-body] h2, [data-editor-body] h3, [data-editor-body] li');

    const moveCaretToPoint = (element, event) => {
        let range = null;
        if (document.caretRangeFromPoint) {
            range = document.caretRangeFromPoint(event.clientX, event.clientY);
        } else if (document.caretPositionFromPoint) {
            const position = document.caretPositionFromPoint(event.clientX, event.clientY);
            if (position) {
                range = document.createRange();
                range.setStart(position.offsetNode, position.offset);
                range.collapse(true);
            }
        }

        if (range && element.contains(range.startContainer)) {
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        }
    };

    form.addEventListener('dblclick', (event) => {
        const block = event.target instanceof Element ? editableBlock(event.target) : null;
        if (!block || !form.contains(block)) {
            return;
        }

        block.contentEditable = 'true';
        block.focus({ preventScroll: true });
        moveCaretToPoint(block, event);
    });

    document.addEventListener('selectionchange', () => {
        const selection = window.getSelection();
        if (selection?.rangeCount && body.contains(selection.anchorNode)) {
            savedRange = selection.getRangeAt(0).cloneRange();
        }
    });

    form.addEventListener('input', syncFields);
    form.querySelectorAll('[data-editor-link-url], [data-editor-link-anchor]').forEach((input) => {
        input.addEventListener('input', syncBacklinks);
    });
    form.addEventListener('focusout', (event) => {
        const block = event.target instanceof Element ? event.target.closest('[contenteditable="true"]') : null;
        if (!block || !form.contains(block)) {
            return;
        }

        if (event.relatedTarget instanceof Element && event.relatedTarget.closest('[role="toolbar"]')) {
            return;
        }

        window.setTimeout(() => {
            if (!block.contains(document.activeElement)) {
                block.contentEditable = 'false';
                syncFields();
            }
        }, 0);
    });

    form.addEventListener('keydown', (event) => {
        const block = event.target instanceof Element ? event.target.closest('[contenteditable="true"]') : null;
        if (!block || !form.contains(block) || event.key !== 'Enter' || block === title || block.matches('li')) {
            return;
        }

        event.preventDefault();
        const selection = window.getSelection();
        const range = selection?.rangeCount ? selection.getRangeAt(0) : null;
        const nextParagraph = document.createElement('p');
        nextParagraph.dataset.editableBlock = '';

        if (range && block.contains(range.endContainer)) {
            const remainder = range.cloneRange();
            remainder.selectNodeContents(block);
            remainder.setStart(range.endContainer, range.endOffset);
            nextParagraph.append(remainder.extractContents());
        }

        block.after(nextParagraph);
        nextParagraph.contentEditable = 'true';
        nextParagraph.focus();
        syncFields();
    });

    form.querySelectorAll('[data-article-command]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            const selection = window.getSelection();
            if (!selection?.rangeCount || !body.contains(selection.anchorNode)) {
                return;
            }

            if (button.dataset.articleCommand === 'createLink') {
                const href = window.prompt('Enter a link URL (http, https, mailto or a relative path):');
                if (!href) {
                    return;
                }

                let protocol = '';
                try {
                    protocol = new URL(href, window.location.origin).protocol;
                } catch {
                    window.alert('Enter a valid link URL.');
                    return;
                }

                if (!['http:', 'https:', 'mailto:'].includes(protocol)) {
                    window.alert('Only http, https, mailto and relative links are allowed.');
                    return;
                }

                document.execCommand('createLink', false, href);
            } else {
                document.execCommand(button.dataset.articleCommand, false);

                const sourceTag = button.dataset.articleCommand === 'bold' ? 'b' : 'i';
                const semanticTag = button.dataset.articleCommand === 'bold' ? 'strong' : 'em';
                body.querySelectorAll(sourceTag).forEach((source) => {
                    const replacement = document.createElement(semanticTag);
                    replacement.append(...source.childNodes);
                    source.replaceWith(replacement);
                });
            }

            syncFields();
        });
    });

    sizeSelect?.addEventListener('change', () => {
        const selection = window.getSelection();
        if (!savedRange || !selection || !body.contains(savedRange.commonAncestorContainer)) {
            return;
        }

        selection.removeAllRanges();
        selection.addRange(savedRange);

        if (!selection.isCollapsed) {
            const span = document.createElement('span');
            span.dataset.textSize = sizeSelect.value;
            span.append(selection.getRangeAt(0).extractContents());
            selection.getRangeAt(0).insertNode(span);
            selection.removeAllRanges();
        }

        syncFields();
    });

    form.querySelectorAll('[data-article-add-block]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            const tag = button.dataset.articleAddBlock;
            if (tag === 'h3' && !body.querySelector('h2')) {
                return;
            }

            const block = document.createElement(tag);
            block.dataset.editableBlock = '';

            if (tag === 'ul' || tag === 'ol') {
                const item = document.createElement('li');
                item.dataset.editableBlock = '';
                item.dataset.placeholder = 'List item';
                block.append(item);
            } else {
                block.dataset.placeholder = tag === 'h2' ? 'Main section heading' : tag === 'h3' ? 'Subsection heading' : 'Paragraph';
            }

            const selection = window.getSelection();
            const anchor = selection?.anchorNode;
            const selectedBlock = anchor instanceof Element
                ? anchor.closest('p, h2, h3, ul, ol')
                : anchor?.parentElement?.closest('p, h2, h3, ul, ol');
            if (selectedBlock?.parentElement === body) {
                selectedBlock.after(block);
            } else {
                body.append(block);
            }

            const editable = block.matches('ul, ol') ? block.querySelector('li') : block;
            editable.contentEditable = 'true';
            editable.focus();
            updateHeadingButton();
            syncFields();
        });
    });

    form.addEventListener('submit', syncFields);
    updateHeadingButton();
    syncFields();
});

// Links with data-open-dialog open that <dialog> when the page has one; otherwise they stay normal links.
document.addEventListener('click', (event) => {
    const closer = event.target instanceof Element ? event.target.closest('[data-close-dialog]') : null;
    if (closer) {
        event.preventDefault();
        closer.closest('dialog')?.close();
        return;
    }

    const opener = event.target.closest('[data-open-dialog]');
    const dialog = opener && document.getElementById(opener.dataset.openDialog);

    if (dialog instanceof HTMLDialogElement) {
        event.preventDefault();
        dialog.showModal();
    }
});

// A slow submit (e.g. fetching a feed) shows data-pending-text and blocks a second click until the page reloads.
document.addEventListener('submit', (event) => {
    const button = event.submitter;

    if (button?.dataset.pendingText) {
        button.disabled = true;
        button.textContent = button.dataset.pendingText;
    }
});

document.querySelectorAll('dialog').forEach((dialog) => {
    // The content sits in an inner wrapper, so a click whose target is the dialog itself landed on the backdrop.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog || event.target.closest('[data-close-dialog]')) {
            dialog.close();
        }
    });

    // Reopens a modal after a failed submit so its validation errors are visible.
    if (dialog.hasAttribute('data-open-on-load')) {
        dialog.showModal();
    }
});

// Suggests a domain from the name ("Dagens Budbärare" -> dagensbudbarare.se) until the domain is edited by hand.
document.querySelectorAll('form').forEach((form) => {
    const source = form.querySelector('[data-domain-source]');
    const target = form.querySelector('[data-domain-target]');

    if (!source || !target) {
        return;
    }

    // A prefilled value (e.g. old input after an error) counts as the user's own choice.
    let edited = target.value !== '';

    target.addEventListener('input', () => {
        edited = true;
    });

    source.addEventListener('input', () => {
        if (edited) {
            return;
        }

        // NFD splits å/ä/ö into a base letter plus a mark, so removing the marks leaves a/a/o.
        const slug = source.value
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]/g, '');

        target.value = slug ? `${slug}.se` : '';
    });
});

// Dator/Mobil in the builder preview: mobile is 390 px, desktop is drawn 1240 px wide and scaled to fit (never under 30 %).
const previewPane = document.querySelector('[data-preview-pane]');
const previewDevices = document.querySelector('[data-preview-devices]');
const previewFrame = document.querySelector('[data-preview-frame]');
const previewIframe = previewFrame?.querySelector('iframe');

// fit: desktop is scaled down to the pane; mobile is always shown at full size.
const DEVICES = {
    desktop: { width: 1240, label: 'Dator', fit: true },
    mobile: { width: 390, label: '390 px', fit: false },
};

if (previewPane && previewDevices) {
    const label = document.querySelector('[data-preview-label]');
    const buttons = previewDevices.querySelectorAll('[data-preview-device]');

    // sessionStorage keeps the choice across the reload on save and tab change; it can throw (e.g. blocked storage), so desktop is the fallback.
    let device = 'desktop';
    try {
        device = sessionStorage.getItem('preview-device') === 'mobile' ? 'mobile' : 'desktop';
    } catch {}

    const render = () => {
        const current = DEVICES[device];
        // clientWidth includes the pane's 2 × 20 px padding, so 40 px is subtracted.
        const scale = current.fit ? Math.min(1, Math.max(0.3, (previewPane.clientWidth - 40) / current.width)) : 1;

        // transform shrinks only the look, so the iframe keeps a 1240 px viewport; the frame gets the scaled size and clips the rest.
        previewFrame.style.width = `${current.width * scale}px`;
        previewIframe.style.width = `${current.width}px`;
        previewIframe.style.height = `${previewFrame.clientHeight / scale}px`;
        previewIframe.style.transform = `scale(${scale})`;

        label.textContent = current.label;
        buttons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.previewDevice === device)));
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            device = button.dataset.previewDevice;
            try {
                sessionStorage.setItem('preview-device', device);
            } catch {}
            render();
        });
    });

    new ResizeObserver(render).observe(previewPane);
    previewDevices.hidden = false;
}

// Live preview without saving: the typed name and the picked theme are copied into the iframe (same origin).
// Without JS the iframe simply shows the saved values.
if (previewIframe) {
    const nameInput = document.querySelector('[data-live-name-source]');
    const themeRadios = document.querySelectorAll('input[type="radio"][name="theme"]');

    const sync = () => {
        const doc = previewIframe.contentDocument;

        // Before the iframe has loaded this is an empty document; the load listener below runs sync again.
        if (!doc?.documentElement) {
            return;
        }

        if (nameInput) {
            doc.querySelectorAll('[data-live-name]').forEach((element) => {
                // The iframe was rendered with the saved name, so the first text seen is kept as the fallback for an empty field.
                element.dataset.savedName ??= element.textContent;
                element.textContent = nameInput.value.trim() === '' ? element.dataset.savedName : nameInput.value;
            });
        }

        // Only the attribute changes; the colors come from the [data-theme] rules in app.css, which the iframe loads for every theme.
        const checked = [...themeRadios].find((radio) => radio.checked);
        if (checked) {
            doc.documentElement.dataset.theme = checked.value;
        }
    };

    // Also covers unsaved values that are already in the form, e.g. old input after a validation error.
    previewIframe.addEventListener('load', sync);
    nameInput?.addEventListener('input', sync);
    themeRadios.forEach((radio) => radio.addEventListener('change', sync));
    sync();
}

// Admin color mode: switch in place and save in the background, so unsaved fields (e.g. in the builder) survive.
// Only pages rendered by layouts.admin have the switch; the preview iframe and the magazines load this file too
// but have no switch, so nothing here runs there.
const modeForms = document.querySelectorAll('[data-studio-mode-form]');

if (modeForms.length > 0) {
    // Shows the page in the given mode and marks that mode's button as pressed in every switch.
    const applyMode = (mode) => {
        if (mode === 'light') {
            document.documentElement.dataset.studioMode = 'light';
        } else {
            delete document.documentElement.dataset.studioMode;
        }

        document.querySelectorAll('[data-studio-mode-option]').forEach((button) => {
            button.setAttribute('aria-pressed', button.value === mode ? 'true' : 'false');
        });
    };

    const showModeError = () => {
        const notice = document.createElement('div');
        notice.setAttribute('role', 'alert');
        notice.className = 'fixed top-4 right-4 left-4 z-50 mx-auto max-w-md rounded-lg border border-studio-bad/40 bg-studio-bad-bg p-3 text-sm text-studio-bad shadow-xl';
        notice.textContent = 'Valet kunde inte sparas. Läget gäller tills sidan laddas om.';
        document.body.append(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    document.addEventListener('submit', async (event) => {
        const form = event.target instanceof HTMLFormElement ? event.target : null;
        if (!form?.matches('[data-studio-mode-form]')) {
            return;
        }

        // The pressed button carries the mode. Without a known mode the form posts the normal way.
        const mode = event.submitter?.value;
        if (mode !== 'light' && mode !== 'dark') {
            return;
        }

        event.preventDefault();
        // FormData leaves out the submitting button, so its value is added by hand.
        const body = new FormData(form);
        body.set('mode', mode);
        applyMode(mode);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });

            if (!response.ok) {
                showModeError();
            }
        } catch {
            showModeError();
        }
    });
}
