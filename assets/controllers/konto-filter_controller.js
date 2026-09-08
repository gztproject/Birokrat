import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['classSelect', 'categorySelect', 'table'];

    async onClassChange() {
        const form = this.element;
        const data = new FormData();
        data.set(this.classSelectTarget.name, this.classSelectTarget.value);
        const response = await fetch(form.action, { method: form.method, body: data });
        const html = await response.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const replacement = doc.getElementById(this.categorySelectTarget.id);
        if (replacement) {
            this.categorySelectTarget.replaceWith(replacement);
        }
    }

    async onCategoryChange() {
        const form = this.element;
        const data = new FormData();
        data.set(this.categorySelectTarget.name, this.categorySelectTarget.value);
        const response = await fetch(form.action, { method: form.method, body: data });
        const html = await response.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const replacement = doc.getElementById('konto_table');
        const current = document.getElementById('konto_table');
        if (replacement && current) {
            current.replaceWith(replacement);
        }
    }
}
