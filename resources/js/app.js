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

// The editor keeps semantic blocks in the DOM and serializes plain-text runs to the versioned document.
document.querySelectorAll('[data-article-editor]').forEach((form) => {
    const title = form.querySelector('[data-editor-title]');
    const titleField = form.querySelector('[data-article-title-field]');
    const body = form.querySelector('[data-editor-body]');
    const documentField = form.querySelector('[data-article-document-field]');
    const contentField = form.querySelector('[data-article-content-field]');
    const sizeSelect = form.querySelector('[data-article-text-size]');
    const backlinkPreview = form.querySelector('[data-editor-backlinks-preview]');
    const insertLinkButtons = form.querySelectorAll('[data-article-insert-link]');
    const linkNotice = form.querySelector('[data-article-link-notice]');
    const imageInput = form.querySelector('[data-article-image-input]');
    const removeImageButton = form.querySelector('[data-remove-image]');
    const imageAltText = form.querySelector('[data-image-alt-text]');
    const imageDecorative = form.querySelector('[data-image-decorative]');
    const removeImageField = form.querySelector('[data-remove-image-field]');
    const saveState = form.querySelector('[data-save-state]');
    const legacyMode = form.dataset.editorMode === 'legacy';
    let savedRange = null;
    let dirty = false;
    let submitting = false;
    let lastFocusedBlock = null;

    if (!title || !titleField || !body || (!documentField && !contentField)) {
        return;
    }

    const syncImageAltRequirement = () => {
        const hasImage = Boolean(imageInput?.files?.length || (form.dataset.originalImageId && removeImageField?.value !== '1'));
        const isRequired = hasImage && !imageDecorative?.checked;
        if (imageAltText) imageAltText.required = isRequired;
        const marker = form.querySelector('[data-image-alt-required-marker]');
        if (marker) marker.hidden = !isRequired;
    };

    const syncRemoveImageButton = () => {
        if (!removeImageButton) return;
        removeImageButton.hidden = !imageInput?.files?.length && (!form.dataset.originalImageId || removeImageField?.value === '1');
    };

    const syncLinkRequirements = () => {
        ['brand'].forEach((type) => {
            const url = form.querySelector(`[data-editor-link-url="${type}"]`);
            const anchor = form.querySelector(`[data-editor-link-anchor="${type}"]`);
            const urlRequired = Boolean(anchor?.value.trim());
            const anchorRequired = Boolean(url?.value.trim());
            if (url) url.required = urlRequired;
            if (anchor) anchor.required = anchorRequired;
            const urlMarker = form.querySelector(`[data-required-marker="${type}-url"]`);
            const anchorMarker = form.querySelector(`[data-required-marker="${type}-anchor"]`);
            if (urlMarker) urlMarker.hidden = !urlRequired;
            if (anchorMarker) anchorMarker.hidden = !anchorRequired;
        });
    };

    syncImageAltRequirement();
    syncRemoveImageButton();
    syncLinkRequirements();

    const newId = () => globalThis.crypto?.randomUUID?.() ?? 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
        const random = Math.random() * 16 | 0;
        return (character === 'x' ? random : (random & 0x3 | 0x8)).toString(16);
    });

    const setDirty = () => {
        dirty = true;
        if (saveState) {
            saveState.textContent = 'Osparade ändringar';
        }
    };

    const syncBacklinks = () => {
        if (!backlinkPreview) {
            return;
        }

        const brandUrl = form.querySelector('[data-editor-link-url="brand"]')?.value.trim() ?? '';
        const brandText = form.querySelector('[data-editor-link-anchor="brand"]')?.value.trim() ?? '';
        let hasBrand = false;
        try {
            hasBrand = ['http:', 'https:'].includes(new URL(brandUrl).protocol) && Boolean(brandText);
        } catch {}
        const brandRow = form.querySelector('[data-editor-brand-row]');
        const brandPreviewLink = form.querySelector('[data-editor-brand-preview]');
        if (brandRow) brandRow.hidden = !hasBrand;
        if (hasBrand && brandPreviewLink) {
            brandPreviewLink.href = brandUrl;
            brandPreviewLink.textContent = brandText;
        }
        backlinkPreview.hidden = !hasBrand;
    };

    const syncInsertLinkButton = () => {
        const hasText = Array.from(body.querySelectorAll('[contenteditable="true"]'))
            .some((editable) => editable.textContent.trim() !== '');
        insertLinkButtons.forEach((button) => { button.disabled = !hasText; });
    };

    syncBacklinks();
    syncInsertLinkButton();

    const updateHeadingButton = () => {
        const blocks = Array.from(body.querySelectorAll('[data-editor-block]'));
        const selected = currentTextBlock();
        const insertionIndex = selected?.parentElement === body ? blocks.indexOf(selected) + 1 : blocks.length;
        const hasH2 = legacyMode
            ? body.querySelector('h2') !== null
            : blocks.slice(0, insertionIndex).some((block) => block.dataset.blockType === 'heading' && block.dataset.level === '2');
        const h3Button = form.querySelector('[data-article-add-block="h3"]');
        if (h3Button) h3Button.disabled = !hasH2;
    };

    const articleStructureIsValid = (blocks) => {
        const introductions = blocks.filter((block) => block.dataset.blockType === 'introduction');
        if (introductions.length !== 1 || blocks[0] !== introductions[0]) return false;

        let hasH2 = false;
        for (const block of blocks) {
            if (block.dataset.blockType !== 'heading') continue;
            if (block.dataset.level === '2') hasH2 = true;
            if (block.dataset.level === '3' && !hasH2) return false;
        }
        return true;
    };

    const sameRun = (left, right) => left.text !== '' && left.href === right.href && left.size === right.size
        && JSON.stringify(left.marks) === JSON.stringify(right.marks);

    const readRuns = (root) => {
        const runs = [];
        const visit = (node, marks = [], size = 'normal', href = undefined) => {
            if (node.nodeType === Node.TEXT_NODE) {
                const text = node.nodeValue ?? '';
                if (!text) return;
                const run = { text, marks: [...new Set(marks)], size };
                if (href !== undefined) run.href = href;
                const previous = runs.at(-1);
                if (previous && sameRun(previous, run)) previous.text += run.text;
                else runs.push(run);
                return;
            }
            if (!(node instanceof Element)) return;
            const tag = node.tagName.toLowerCase();
            if (tag === 'br') {
                visit(document.createTextNode('\n'), marks, size, href);
                return;
            }
            const nextMarks = [...marks];
            if (tag === 'b' || tag === 'strong') nextMarks.push('bold');
            if (tag === 'i' || tag === 'em') nextMarks.push('italic');
            const nextSize = node.matches('span[data-text-size]') && ['small', 'normal', 'large'].includes(node.dataset.textSize)
                ? node.dataset.textSize : size;
            const nextHref = tag === 'a' ? node.getAttribute('href') ?? undefined : href;
            node.childNodes.forEach((child) => visit(child, nextMarks, nextSize, nextHref));
        };
        root.childNodes.forEach((node) => visit(node));
        return runs;
    };

    const readDocument = () => {
        const blocks = [];
        body.querySelectorAll(':scope > [data-editor-block]').forEach((wrapper) => {
            const block = { id: wrapper.dataset.blockId || newId(), type: wrapper.dataset.blockType };
            wrapper.dataset.blockId = block.id;
            if (['introduction', 'paragraph'].includes(block.type)) {
                block.runs = readRuns(wrapper.querySelector('[data-editor-text]'));
            } else if (block.type === 'heading') {
                block.level = Number(wrapper.dataset.level);
                block.runs = readRuns(wrapper.querySelector('[data-editor-text]'));
            } else if (block.type === 'list') {
                const list = wrapper.querySelector('[data-editor-list]');
                block.style = list?.tagName === 'OL' ? 'numbered' : 'bullet';
                block.items = Array.from(list?.querySelectorAll(':scope > li') ?? []).map((item) => {
                    if (!item.dataset.listItemId) item.dataset.listItemId = newId();
                    return { id: item.dataset.listItemId, runs: readRuns(item) };
                });
            } else if (block.type === 'image') {
                block.media_id = Number(wrapper.dataset.mediaId || 0);
                block.caption = wrapper.querySelector('[data-editor-caption]')?.textContent.trim() ?? '';
            } else {
                return;
            }
            blocks.push(block);
        });
        return { version: 1, blocks };
    };

    const syncFields = () => {
        titleField.value = title.textContent.replace(/\s+/g, ' ').trim();
        if (documentField) documentField.value = JSON.stringify(readDocument());
        if (contentField) contentField.value = body.innerHTML;
        const introduction = body.querySelector('[data-block-type="introduction"] [data-editor-text]');
        const introductionProxy = form.querySelector('[data-introduction-required-proxy]');
        if (introductionProxy) introductionProxy.value = introduction?.textContent.trim() ? 'present' : '';
        syncBacklinks();
        syncInsertLinkButton();
        updateHeadingButton();
    };

    const blockButtons = () => `<div class="article-editor-block-controls" role="group" aria-label="Blockkontroller">
        <button type="button" data-block-move="up" aria-label="Flytta block upp" title="Flytta upp">↑</button>
        <button type="button" data-block-move="down" aria-label="Flytta block ned" title="Flytta ned">↓</button>
        <button type="button" class="article-editor-block-remove" data-block-remove aria-label="Ta bort block" title="Ta bort block">X</button>
    </div>`;

    const makeWrapper = (type, level = null) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'article-editor-block group';
        wrapper.dataset.editorBlock = '';
        wrapper.dataset.blockId = newId();
        wrapper.dataset.blockType = type;
        if (type === 'introduction') {
            wrapper.innerHTML = '<span class="article-editor-required-mark" aria-hidden="true">*</span>';
        } else {
            wrapper.innerHTML = blockButtons();
        }

        if (type === 'paragraph' || type === 'introduction' || type === 'heading') {
            const tag = type === 'heading' ? `h${level}` : 'p';
            if (type === 'heading') wrapper.dataset.level = String(level);
            const text = document.createElement(tag);
            text.dataset.editorText = '';
            text.dataset.placeholder = type === 'introduction' ? 'Skriv en inledning…' : type === 'heading' ? 'Skriv en rubrik…' : 'Skriv ett stycke…';
            text.contentEditable = 'true';
            text.spellcheck = true;
            if (type === 'introduction') text.setAttribute('aria-label', 'Inledning (obligatorisk)');
            wrapper.append(text);
        } else if (type === 'list') {
            const list = document.createElement(level === 'numbered' ? 'ol' : 'ul');
            list.dataset.editorList = '';
            const item = document.createElement('li');
            item.dataset.listItemId = newId();
            item.dataset.placeholder = 'Skriv en punkt…';
            item.contentEditable = 'true';
            item.spellcheck = true;
            list.append(item);
            wrapper.append(list);
        } else if (type === 'image') {
            wrapper.dataset.mediaId = String(form.dataset.originalImageId || 0);
            wrapper.innerHTML += `<figure data-editor-image-block><div data-image-placeholder class="rounded-lg border border-dashed border-studio-line p-8 text-center text-sm text-studio-muted">Välj en bild för att visa den här.</div><figcaption data-editor-caption data-placeholder="Lägg till en valfri bildtext…" contenteditable="true" spellcheck="true"></figcaption></figure>`;
        }

        return wrapper;
    };

    const currentTextBlock = () => {
        const selection = window.getSelection();
        const node = selection?.anchorNode;
        const element = node instanceof Element ? node : node?.parentElement;
        return element?.closest('[data-editor-block]') ?? lastFocusedBlock;
    };

    const insertAfterFocus = (wrapper) => {
        const selected = currentTextBlock();
        if (selected?.parentElement === body) selected.after(wrapper);
        else body.append(wrapper);
    };

    const focusAtEnd = (element) => {
        element.focus();
        const range = document.createRange();
        range.selectNodeContents(element);
        range.collapse(false);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
    };

    const updateBlockControls = () => {
        const blocks = Array.from(body.querySelectorAll(':scope > [data-editor-block]'));
        body.querySelectorAll('[data-block-move]').forEach((button) => {
            const wrapper = button.closest('[data-editor-block]');
            const index = blocks.indexOf(wrapper);
            const nextIndex = index + (button.dataset.blockMove === 'up' ? -1 : 1);
            if (index < 0 || nextIndex < 0 || nextIndex >= blocks.length) {
                button.disabled = true;
                return;
            }
            const candidate = [...blocks];
            [candidate[index], candidate[nextIndex]] = [candidate[nextIndex], candidate[index]];
            button.disabled = !articleStructureIsValid(candidate);
        });
        body.querySelectorAll('[data-block-remove]').forEach((button) => {
            const wrapper = button.closest('[data-editor-block]');
            const candidate = blocks.filter((block) => block !== wrapper);
            const breaksArticleStructure = !articleStructureIsValid(candidate);
            button.disabled = breaksArticleStructure;
            button.title = breaksArticleStructure ? 'Behåll introduktionen först och ta bort underrubriker före rubriker' : 'Ta bort block';
            button.setAttribute('aria-label', breaksArticleStructure
                ? 'Den här ändringen skulle bryta artikelns introduktions- eller rubrikordning'
                : 'Ta bort block');
        });
        updateHeadingButton();
    };

    const updateImagePreview = (file) => {
        if (!file) return;
        const url = URL.createObjectURL(file);
        const imageBlock = body.querySelector('[data-editor-block][data-block-type="image"]');
        if (imageBlock) {
            imageBlock.dataset.mediaId = String(form.dataset.originalImageId || 0);
            const placeholder = imageBlock.querySelector('[data-image-placeholder]');
            if (placeholder) placeholder.outerHTML = `<img data-editor-image alt="" class="mx-auto h-auto max-h-[32rem] max-w-full rounded-md">`;
            const image = imageBlock.querySelector('[data-editor-image]');
            image.src = url;
            image.alt = form.querySelector('[data-image-alt-text]')?.value ?? '';
        } else {
            let legacyPreview = form.querySelector('[data-legacy-image-preview]');
            if (!legacyPreview) {
                legacyPreview = document.createElement('figure');
                legacyPreview.className = 'mt-8';
                legacyPreview.dataset.legacyImagePreview = '';
                body.before(legacyPreview);
            }
            let legacyImage = legacyPreview.querySelector('img');
            if (!legacyImage) {
                legacyImage = document.createElement('img');
                legacyImage.className = 'mx-auto h-auto max-h-[32rem] max-w-full rounded-md';
                legacyPreview.append(legacyImage);
            }
            legacyImage.src = url;
            legacyImage.alt = form.querySelector('[data-image-alt-text]')?.value ?? '';
        }
    };

    const formatSelection = (command) => {
        const selection = window.getSelection();
        if (!selection?.rangeCount || selection.isCollapsed || !body.contains(selection.anchorNode)) return;
        document.execCommand(command, false);
        const oldTag = command === 'bold' ? 'B' : 'I';
        const newTag = command === 'bold' ? 'STRONG' : 'EM';
        body.querySelectorAll(oldTag.toLowerCase()).forEach((old) => {
            const replacement = document.createElement(newTag.toLowerCase());
            replacement.append(...old.childNodes);
            old.replaceWith(replacement);
        });
        setDirty();
        syncFields();
    };

    const editableFor = (node) => {
        const element = node instanceof Element ? node : node?.parentElement;
        return element?.closest('[contenteditable="true"]') ?? null;
    };

    const selectedRange = () => {
        const selection = window.getSelection();
        let range = selection?.rangeCount ? selection.getRangeAt(0).cloneRange() : null;
        const rangeEditable = range && body.contains(range.commonAncestorContainer)
            ? editableFor(range.startContainer)
            : null;
        if (!rangeEditable || rangeEditable !== editableFor(range.endContainer) || !range.toString().trim()) return null;

        return range;
    };

    const showLinkNotice = (message) => {
        if (!linkNotice) return;
        linkNotice.textContent = message;
        linkNotice.hidden = false;
    };

    const insertLinkFromSelection = (type) => {
        const range = selectedRange();
        if (!range) {
            showLinkNotice('Markera först texten i artikeln som ska bli en länk.');
            return;
        }

        savedRange = range.cloneRange();
        const selectedAnchor = Array.from(body.querySelectorAll('a')).find((anchor) => range.intersectsNode(anchor));
        const productUrl = form.querySelector('[data-editor-link-url="product"]')?.value.trim() ?? '';
        const initialUrl = selectedAnchor?.getAttribute('href') ?? (type === 'product' ? productUrl : '');
        const promptText = type === 'product'
            ? 'Ange webbadressen till produkten eller tjänsten (http eller https):'
            : 'Ange en webbadress (http, https, mailto eller en relativ sökväg):';
        const enteredUrl = window.prompt(promptText, initialUrl);
        if (!enteredUrl) return;

        const href = enteredUrl.trim();
        if (href.length > 2048) {
            showLinkNotice('Länken får innehålla högst 2 048 tecken.');
            return;
        }
        if (href.startsWith('//')) {
            showLinkNotice('Webbadresser som börjar med // är inte tillåtna.');
            return;
        }

        let protocol = '';
        try { protocol = new URL(href, window.location.origin).protocol; } catch {}
        const allowedProtocols = type === 'product' ? ['http:', 'https:'] : ['http:', 'https:', 'mailto:'];
        if (!allowedProtocols.includes(protocol) || (type === 'product' && !/^https?:\/\//i.test(href))) {
            showLinkNotice(type === 'product' ? 'Produktlänkar måste börja med http eller https.' : 'Använd en http-, https-, mailto- eller relativ webbadress.');
            return;
        }

        if (insertSelectedLink(href, type)) linkNotice.hidden = true;
    };

    const insertSelectedLink = (href, type) => {
        const range = savedRange?.cloneRange() ?? null;
        if (!range || !body.contains(range.commonAncestorContainer) || !range.toString().trim()) {
            showLinkNotice('Markera först texten i artikeln som ska bli en länk.');
            return false;
        }

        const selectedText = range.toString().trim();
        if (type === 'product' && selectedText.length > 255) {
            showLinkNotice('En produkt- eller tjänstelänk får innehålla högst 255 tecken.');
            return false;
        }
        const productUrlField = form.querySelector('[data-editor-link-url="product"]');
        const productTextField = form.querySelector('[data-editor-link-anchor="product"]');
        const previousProductUrl = productUrlField?.value.trim() ?? '';
        const selectedProductAnchor = Array.from(body.querySelectorAll('a')).some((anchor) => range.intersectsNode(anchor)
            && (anchor.hasAttribute('data-article-product-link') || anchor.href === previousProductUrl));
        const fragment = range.extractContents();

        // A selected link is replaced rather than nested inside another anchor.
        fragment.querySelectorAll?.('a').forEach((anchor) => anchor.replaceWith(...anchor.childNodes));
        const anchor = document.createElement('a');
        anchor.href = href;
        if (type === 'product') {
            anchor.rel = 'sponsored';
            anchor.dataset.articleProductLink = '';
        }
        anchor.append(fragment);
        range.insertNode(anchor);

        if (type === 'product') {
            if (productUrlField) productUrlField.value = href;
            if (productTextField) productTextField.value = selectedText;
            body.querySelectorAll('a[data-article-product-link]').forEach((existing) => {
                if (existing !== anchor) existing.replaceWith(...existing.childNodes);
            });
            if (previousProductUrl && previousProductUrl !== href) {
                body.querySelectorAll('a').forEach((existing) => {
                    if (existing !== anchor && existing.href === previousProductUrl) existing.replaceWith(...existing.childNodes);
                });
            }
        } else if (selectedProductAnchor) {
            if (productUrlField) productUrlField.value = '';
            if (productTextField) productTextField.value = '';
        }

        const cursor = document.createRange();
        cursor.setStartAfter(anchor);
        cursor.collapse(true);
        const selection = window.getSelection();
        selection?.removeAllRanges();
        selection?.addRange(cursor);
        savedRange = cursor.cloneRange();
        lastFocusedBlock = anchor.closest('[data-editor-block]');

        setDirty();
        syncFields();
        return true;
    };

    insertLinkButtons.forEach((button) => {
        button.addEventListener('click', () => insertLinkFromSelection(button.dataset.articleInsertLink));
    });

    document.addEventListener('selectionchange', () => {
        const selection = window.getSelection();
        if (selection?.rangeCount && body.contains(selection.anchorNode)) {
            savedRange = selection.getRangeAt(0).cloneRange();
            lastFocusedBlock = currentTextBlock();
            if (selection.toString().trim() && linkNotice) linkNotice.hidden = true;
        }
    });

    form.addEventListener('focusin', (event) => {
        const editable = event.target instanceof Element ? event.target.closest('[contenteditable="true"]') : null;
        if (editable && body.contains(editable)) lastFocusedBlock = editable.closest('[data-editor-block]');
    });

    form.addEventListener('input', () => {
        setDirty();
        syncFields();
    });
    form.querySelectorAll('[data-editor-link-url], [data-editor-link-anchor], [data-image-alt-text], [data-image-decorative]').forEach((input) => {
        const updateValue = () => {
            setDirty();
            syncFields();
            syncImageAltRequirement();
            syncLinkRequirements();
            if (input.matches('[data-image-alt-text], [data-image-decorative]')) {
                const alt = form.querySelector('[data-image-decorative]')?.checked
                    ? ''
                    : form.querySelector('[data-image-alt-text]')?.value ?? '';
                form.querySelectorAll('[data-editor-image], [data-legacy-image-preview] img').forEach((image) => { image.alt = alt; });
            }
        };
        input.addEventListener('input', updateValue);
        input.addEventListener('change', updateValue);
    });

    form.addEventListener('paste', (event) => {
        if (!body.contains(event.target)) return;
        event.preventDefault();
        document.execCommand('insertText', false, event.clipboardData?.getData('text/plain') ?? '');
    });

    form.addEventListener('keydown', (event) => {
        const editable = event.target instanceof Element ? event.target.closest('[contenteditable="true"]') : null;
        if (!editable || !body.contains(editable)) return;
        const wrapper = editable.closest('[data-editor-block]');
        if (event.key === 'Enter' && editable === title) {
            event.preventDefault();
            return;
        }

        if (event.key === 'Enter' && !event.shiftKey && (editable.matches('[data-editor-text]') || (legacyMode && editable.matches('p, h2, h3')))) {
            event.preventDefault();
            document.execCommand('insertLineBreak', false);
            setDirty();
            syncFields();
            return;
        }

        if (event.key === 'Backspace' && wrapper && editable.matches('[data-editor-text]')) {
            const selection = window.getSelection();
            const range = selection?.rangeCount ? selection.getRangeAt(0) : null;
            if (range?.collapsed) {
                const before = range.cloneRange();
                before.selectNodeContents(editable);
                before.setEnd(range.startContainer, range.startOffset);
                if (before.toString() === '') {
                    const previous = wrapper.previousElementSibling;
                    if (previous?.matches('[data-editor-block]')) {
                        event.preventDefault();
                        const previousText = previous.querySelector('[data-editor-text]');
                        if (previousText) {
                            while (editable.firstChild) previousText.append(editable.firstChild);
                            wrapper.remove();
                            focusAtEnd(previousText);
                            setDirty();
                            syncFields();
                        }
                    }
                }
            }
        }
    });

    form.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const control = target?.closest('[data-block-move], [data-block-remove]');
        if (control && body.contains(control)) {
            event.preventDefault();
            const wrapper = control.closest('[data-editor-block]');
            const blocks = Array.from(body.querySelectorAll(':scope > [data-editor-block]'));
            if (control.matches('[data-block-remove]')) {
                if (wrapper.dataset.blockType === 'introduction') return;
                if (!articleStructureIsValid(blocks.filter((block) => block !== wrapper))) return;
                if (wrapper.dataset.blockType === 'image' && String(form.dataset.originalImageId || '') === String(wrapper.dataset.mediaId || '')) {
                    removeImageField.value = '1';
                }
                if (wrapper.dataset.blockType === 'image' && imageInput) imageInput.value = '';
                wrapper.remove();
            } else {
                const index = blocks.indexOf(wrapper);
                const nextIndex = index + (control.dataset.blockMove === 'up' ? -1 : 1);
                if (index < 0 || nextIndex < 0 || nextIndex >= blocks.length) return;
                const candidate = [...blocks];
                [candidate[index], candidate[nextIndex]] = [candidate[nextIndex], candidate[index]];
                if (!articleStructureIsValid(candidate)) return;
                if (control.dataset.blockMove === 'up') {
                    wrapper.parentElement.insertBefore(wrapper, wrapper.previousElementSibling);
                } else {
                    wrapper.parentElement.insertBefore(wrapper.nextElementSibling, wrapper);
                }
            }
            setDirty();
            updateBlockControls();
            syncFields();
            syncImageAltRequirement();
            syncRemoveImageButton();
            return;
        }

        if (target?.closest('[data-article-command]')) {
            event.preventDefault();
            formatSelection(target.closest('[data-article-command]').dataset.articleCommand);
        }
    });

    form.querySelectorAll('[data-article-command], [data-article-insert-link]').forEach((button) => button.addEventListener('mousedown', (event) => event.preventDefault()));

    sizeSelect?.addEventListener('change', () => {
        const selection = window.getSelection();
        if (!savedRange || !selection || !body.contains(savedRange.commonAncestorContainer) || savedRange.collapsed) return;
        selection.removeAllRanges();
        selection.addRange(savedRange);
        document.execCommand('fontSize', false, '7');
        body.querySelectorAll('font[size="7"]').forEach((font) => {
            const span = document.createElement('span');
            span.dataset.textSize = sizeSelect.value;
            span.append(...font.childNodes);
            font.replaceWith(span);
        });
        selection.removeAllRanges();
        setDirty();
        syncFields();
    });

    form.querySelectorAll('[data-article-add-block]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            const requested = button.dataset.articleAddBlock;
            if (requested === 'h3' && button.disabled) return;
            if (legacyMode) {
                const tag = requested === 'paragraph' ? 'p' : requested === 'bullet' ? 'ul' : requested === 'numbered' ? 'ol' : requested;
                const block = document.createElement(tag);
                if (tag === 'ul' || tag === 'ol') block.innerHTML = '<li></li>';
                body.append(block);
                const editable = block.matches('ul,ol') ? block.querySelector('li') : block;
                editable.contentEditable = 'true';
                editable.focus();
            } else {
                const type = ['bullet', 'numbered'].includes(requested) ? 'list' : requested === 'paragraph' ? 'paragraph' : 'heading';
                const wrapper = makeWrapper(type, type === 'heading' ? Number(requested.slice(1)) : requested);
                insertAfterFocus(wrapper);
                const editable = wrapper.querySelector('[data-editor-text], li');
                editable?.focus();
            }
            setDirty();
            updateBlockControls();
            syncFields();
        });
    });

    imageInput?.addEventListener('change', () => {
        const file = imageInput.files?.[0];
        if (!file) return;
        removeImageField.value = '0';
        if (!legacyMode && !body.querySelector('[data-block-type="image"]')) {
            const imageBlock = makeWrapper('image');
            insertAfterFocus(imageBlock);
        }
        updateImagePreview(file);
        syncImageAltRequirement();
        syncRemoveImageButton();
        setDirty();
        syncFields();
    });

    removeImageButton?.addEventListener('click', () => {
        const imageBlock = body.querySelector('[data-block-type="image"]');
        const preview = form.querySelector('[data-legacy-image-preview]');
        if (imageBlock) imageBlock.remove();
        if (preview) preview.remove();
        if (String(form.dataset.originalImageId || '') !== '') removeImageField.value = '1';
        if (imageInput) imageInput.value = '';
        syncImageAltRequirement();
        syncRemoveImageButton();
        setDirty();
        updateBlockControls();
        syncFields();
    });

    form.addEventListener('submit', () => {
        syncFields();
        submitting = true;
        if (saveState) saveState.textContent = 'Sparar…';
    });
    window.addEventListener('beforeunload', (event) => {
        if (dirty && !submitting) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    if (legacyMode) {
        body.querySelectorAll('p, h2, h3, li').forEach((block) => { block.contentEditable = 'true'; block.spellcheck = true; });
    }
    body.querySelectorAll('[data-editor-block]').forEach((wrapper) => {
        const listItems = wrapper.querySelectorAll('li');
        listItems.forEach((item) => { if (!item.dataset.listItemId) item.dataset.listItemId = newId(); });
    });
    updateBlockControls();
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
