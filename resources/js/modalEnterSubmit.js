const MODAL_SELECTOR = [
    '.mo',
    '.modal',
    '[role="dialog"]',
    '[role="alertdialog"]',
    'dialog[open]',
].join(', ');

const PRIMARY_BUTTON_SELECTORS = [
    '[data-modal-primary]',
    '.modal-actions .btn-p',
    '.add-modal-actions .btn-p',
    '.reviewer-modal-actions .btn-p',
    '.modal-foot .btn-p',
    '.form-modal-footer .primary',
    'footer .primary',
    '.action.primary',
    '.btn.btn-p',
];

const isVisible = (element) => element.getClientRects().length > 0;

const isEnabledButton = (element) =>
    element instanceof HTMLButtonElement
    && !element.disabled
    && element.getAttribute('aria-disabled') !== 'true'
    && isVisible(element);

const shouldKeepNativeEnterBehavior = (target) => {
    if (!(target instanceof HTMLElement)) {
        return true;
    }

    if (
        target instanceof HTMLTextAreaElement
        || target instanceof HTMLSelectElement
        || target.isContentEditable
        || target.closest('[contenteditable="true"]')
        || target.closest('button, a[href]')
    ) {
        return true;
    }

    if (target instanceof HTMLInputElement) {
        return ['button', 'checkbox', 'file', 'radio', 'range', 'reset', 'submit']
            .includes(target.type);
    }

    return false;
};

const findPrimaryButton = (target, modal) => {
    let scope = target.parentElement;

    while (scope && modal.contains(scope)) {
        for (const selector of PRIMARY_BUTTON_SELECTORS) {
            const buttons = Array.from(scope.querySelectorAll(selector)).filter(isEnabledButton);

            if (buttons.length === 1) {
                return buttons[0];
            }
        }

        if (scope === modal) break;
        scope = scope.parentElement;
    }

    return null;
};

const handleModalEnter = (event) => {
    if (
        event.key !== 'Enter'
        || event.defaultPrevented
        || event.repeat
        || event.altKey
        || event.ctrlKey
        || event.metaKey
        || event.shiftKey
        || shouldKeepNativeEnterBehavior(event.target)
    ) {
        return;
    }

    const target = event.target;
    const modal = target instanceof Element ? target.closest(MODAL_SELECTOR) : null;

    if (!modal || !isVisible(modal)) {
        return;
    }

    const form = target instanceof Element ? target.closest('form') : null;
    if (form && form.querySelector('button[type="submit"]:not(:disabled), input[type="submit"]:not(:disabled)')) {
        return;
    }

    const primaryButton = findPrimaryButton(target, modal);
    if (!primaryButton) {
        return;
    }

    event.preventDefault();
    primaryButton.click();
};

export const installModalEnterSubmit = () => {
    document.addEventListener('keydown', handleModalEnter);
};
