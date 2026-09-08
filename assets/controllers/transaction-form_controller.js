import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['preset', 'debit', 'credit', 'description'];

    applyPreset() {
        const option = this.presetTarget.selectedOptions[0];
        if (!option) {
            return;
        }
        const debit = option.dataset.debit;
        const credit = option.dataset.credit;
        const description = option.dataset.description;
        [...this.debitTarget.options].forEach((item) => {
            item.selected = item.text.includes(debit);
        });
        [...this.creditTarget.options].forEach((item) => {
            item.selected = item.text.includes(credit);
        });
        this.descriptionTarget.value = description;
    }
}
