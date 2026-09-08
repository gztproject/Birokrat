import { Controller } from '@hotwired/stimulus';

function addDays(dateValue, days) {
    const date = new Date(`${dateValue}T00:00:00`);
    date.setDate(date.getDate() + Number(days || 0));
    return date.toISOString().slice(0, 10);
}

function daysBetween(fromValue, toValue) {
    if (!fromValue || !toValue) {
        return null;
    }
    const from = new Date(`${fromValue}T00:00:00`);
    const to = new Date(`${toValue}T00:00:00`);
    return Math.round((to - from) / 86400000);
}

export default class extends Controller {
    static targets = ['paidOnSpot', 'paymentMethod', 'dueInDaysWrap', 'dueDateWrap', 'dateOfIssue', 'dueDate', 'dueInDays'];

    connect() {
        if (this.hasPaidOnSpotTarget) {
            this.paidOnSpotTarget.checked = true;
            this.togglePaidOnSpot();
        }
    }

    togglePaidOnSpot() {
        const paid = this.hasPaidOnSpotTarget && this.paidOnSpotTarget.checked;
        this.paymentMethodTarget?.classList.toggle('d-none', !paid);
        this.dueInDaysWrapTarget?.classList.toggle('d-none', paid);
        this.dueDateWrapTarget?.classList.toggle('d-none', paid);
    }

    onIssueDateChange() {
        if (this.hasDueInDaysTarget && this.hasDueDateTarget && this.dateOfIssueTarget.value) {
            this.dueDateTarget.value = addDays(this.dateOfIssueTarget.value, this.dueInDaysTarget.value);
        }
    }

    onDueDateChange() {
        const days = daysBetween(this.dateOfIssueTarget.value, this.dueDateTarget.value);
        if (days !== null) {
            this.dueInDaysTarget.value = days;
        }
    }

    onDueInDaysChange() {
        if (this.dateOfIssueTarget.value) {
            this.dueDateTarget.value = addDays(this.dateOfIssueTarget.value, this.dueInDaysTarget.value);
        }
    }
}
