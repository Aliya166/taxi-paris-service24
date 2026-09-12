import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'modal',
        'title',
        'message',
        'confirmButton',
        'cancelButton'
    ];

    connect() {
        this.pendingForm = null;
        this.lastFocusedElement = null;

        this.handleKeydown = this.handleKeydown.bind(this);
    }

    open(event) {
        event.preventDefault();

        const form = event.currentTarget;

        this.pendingForm = form;
        this.lastFocusedElement = document.activeElement;

        const title =
            form.dataset.confirmTitle ||
            'Confirmer cette action';

        const message =
            form.dataset.confirmMessage ||
            'Voulez-vous vraiment continuer ?';

        const buttonLabel =
            form.dataset.confirmButton ||
            'Confirmer';

        this.titleTarget.textContent = title;
        this.messageTarget.textContent = message;
        this.confirmButtonTarget.textContent = buttonLabel;

        this.modalTarget.hidden = false;

        document.body.classList.add('modal-open');

        document.addEventListener(
            'keydown',
            this.handleKeydown
        );

        window.requestAnimationFrame(() => {
            this.cancelButtonTarget.focus();
        });
    }

    close() {
        if (this.modalTarget.hidden) {
            return;
        }

        this.modalTarget.hidden = true;

        document.body.classList.remove('modal-open');

        document.removeEventListener(
            'keydown',
            this.handleKeydown
        );

        if (this.lastFocusedElement) {
            this.lastFocusedElement.focus();
        }

        this.pendingForm = null;
    }

    confirm() {
        if (!this.pendingForm) {
            return;
        }

        const form = this.pendingForm;

        this.pendingForm = null;

        this.modalTarget.hidden = true;

        document.body.classList.remove('modal-open');

        document.removeEventListener(
            'keydown',
            this.handleKeydown
        );

        form.submit();
    }

    backdrop(event) {
        if (event.target === this.modalTarget) {
            this.close();
        }
    }

    handleKeydown(event) {
        if (event.key === 'Escape') {
            this.close();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusableElements =
            this.modalTarget.querySelectorAll(
                'button:not([disabled]), a[href]'
            );

        if (focusableElements.length === 0) {
            return;
        }

        const firstElement = focusableElements[0];
        const lastElement =
            focusableElements[
                focusableElements.length - 1
            ];

        if (
            event.shiftKey &&
            document.activeElement === firstElement
        ) {
            event.preventDefault();
            lastElement.focus();
        }

        if (
            !event.shiftKey &&
            document.activeElement === lastElement
        ) {
            event.preventDefault();
            firstElement.focus();
        }
    }
}