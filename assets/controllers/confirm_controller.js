import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

export default class extends Controller {
    static values = { result: { type: String, default: '' } };

    connect() {
        this.pendingForm = null;
    }

    intercept(event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirmation')) {
            return;
        }
        if (this.resultValue === 'yes') {
            this.resultValue = '';
            return;
        }
        event.preventDefault();
        this.pendingForm = form;
        Modal.getOrCreateInstance(this.element).show();
    }

    confirm() {
        if (!this.pendingForm) {
            return;
        }
        this.resultValue = 'yes';
        this.pendingForm.querySelectorAll('[type="submit"]').forEach((button) => {
            button.setAttribute('disabled', 'disabled');
        });
        this.pendingForm.submit();
    }
}
